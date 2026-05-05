<?php

/*
 * ProfileController.php
 * Copyright (c) 2024 james@firefly-iii.org.
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\User;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Events\Security\User\UserChangedEmailAddress;
use FireflyIII\Repositories\User\UserRepositoryInterface;
use FireflyIII\Support\Facades\Preferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class ProfileController extends Controller
{
    private UserRepositoryInterface $userRepository;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->userRepository = app(UserRepositoryInterface::class);

            return $next($request);
        });
    }

    public function changePassword(Request $request): JsonResponse
    {
        if ('web' !== config('firefly.authentication_guard')) {
            return response()->json(['message' => 'external user management is enabled, password change is disabled'], 403);
        }

        $request->validate([
            'current_password'              => 'required|string',
            'new_password'                  => 'required|string|min:8',
            'new_password_confirmation'     => 'required|string|same:new_password',
        ]);

        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return response()->json(['message' => 'current password invalid'], 422);
        }

        $this->userRepository->changePassword(auth()->user(), $request->new_password);

        return response()->json(['message' => 'password updated']);
    }

    public function changeEmail(Request $request): JsonResponse
    {
        if ('web' !== config('firefly.authentication_guard')) {
            return response()->json(['message' => 'external user management is enabled, email change is disabled'], 403);
        }

        $request->validate([
            'new_email' => 'required|email',
            'password'  => 'required|string',
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json(['message' => 'current password invalid'], 422);
        }

        if ($request->new_email === auth()->user()->email) {
            return response()->json(['message' => 'email address is unchanged'], 422);
        }

        $existing = $this->userRepository->findByEmail($request->new_email);
        if (null !== $existing) {
            return response()->json(['message' => 'email address is already in use'], 422);
        }

        $oldEmail = auth()->user()->email;
        $this->userRepository->changeEmail(auth()->user(), $request->new_email);

        event(new UserChangedEmailAddress(auth()->user(), $request->new_email, $oldEmail));

        return response()->json(['message' => 'email change initiated']);
    }

    public function enableMfa(Request $request): JsonResponse
    {
        $request->validate([
            'secret'   => 'required|string',
            'code'     => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json(['message' => 'current password invalid'], 422);
        }

        Preferences::set('twoFactorAuthSecret', $request->secret);
        Preferences::set('twoFactorAuthEnabled', true);

        return response()->json(['message' => 'mfa enabled']);
    }

    public function disableMfa(Request $request): JsonResponse
    {
        Preferences::delete('twoFactorAuthSecret');
        Preferences::set('twoFactorAuthEnabled', false);

        return response()->json(['message' => 'mfa disabled']);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'password'              => 'required|string',
            'password_confirmation' => 'required|string|in:DELETE_ME',
        ]);

        if (!Hash::check($request->password, auth()->user()->password)) {
            return response()->json(['message' => 'current password invalid'], 422);
        }

        $user = auth()->user();
        Auth::logout();
        $this->userRepository->destroy($user);

        return response()->json(['message' => 'account deleted']);
    }
}
