<?php

/*
 * ProfileControllerTest.php
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

namespace Tests\integration\Api\User;

use FireflyIII\Models\GroupMembership;
use FireflyIII\Models\UserGroup;
use FireflyIII\Models\UserRole;
use FireflyIII\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Override;
use Tests\integration\TestCase;

/**
 * @internal
 *
 * @covers \FireflyIII\Api\V1\Controllers\User\ProfileController
 */
final class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    public function testChangePasswordWithValidCredentials(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson(route('api.v1.profile.change-password'), [
            'current_password'          => 'password',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'password updated']);
    }

    public function testChangePasswordWithWrongCurrentPassword(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson(route('api.v1.profile.change-password'), [
            'current_password'          => 'wrongpassword',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    public function testChangePasswordRequiresAuthentication(): void
    {
        $response = $this->postJson(route('api.v1.profile.change-password'), [
            'current_password'          => 'password',
            'new_password'              => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    public function testDisableMfa(): void
    {
        Passport::actingAs($this->user);

        $response = $this->deleteJson(route('api.v1.profile.mfa.disable'));

        $response->assertStatus(200);
        $response->assertJson(['message' => 'mfa disabled']);
    }

    public function testEnableMfaWithValidPassword(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson(route('api.v1.profile.mfa.enable'), [
            'secret'   => 'TESTSECRET123',
            'code'     => '123456',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'mfa enabled']);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $group      = UserGroup::create(['title' => 'profile-test@email.com']);
        $role       = UserRole::where('title', 'owner')->first();
        $this->user = User::create([
            'email'         => 'profile-test@email.com',
            'password'      => Hash::make('password'),
            'user_group_id' => $group->id,
        ]);

        GroupMembership::create([
            'user_id'      => $this->user->id,
            'user_group_id' => $group->id,
            'user_role_id'  => $role->id,
        ]);
    }
}
