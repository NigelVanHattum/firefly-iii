<?php

/*
 * BootController.php
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

namespace FireflyIII\Api\V1\Controllers\System;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Repositories\Currency\CurrencyRepositoryInterface;
use FireflyIII\Support\Facades\Preferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class BootController extends Controller
{
    private CurrencyRepositoryInterface $currencyRepository;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->currencyRepository = app(CurrencyRepositoryInterface::class);
            $this->currencyRepository->setUser(auth()->user());

            return $next($request);
        });
    }

    public function boot(): JsonResponse
    {
        $search      = ['~', '#'];
        $replace     = ['\~', '# '];
        $phpVersion  = str_replace($search, $replace, PHP_VERSION);
        $phpOs       = str_replace($search, $replace, PHP_OS);
        $currentDriver = DB::getDriverName();

        $language    = Preferences::get('language', 'en_US')->data;
        $locale      = Preferences::get('locale', 'en_US')->data;
        $timezone    = Preferences::get('timezone', config('app.timezone'))->data;

        $start       = session('start');
        $end         = session('end');

        $currencies  = [];
        foreach ($this->currencyRepository->getAll() as $currency) {
            $currencies[] = [
                'id'             => $currency->id,
                'code'           => $currency->code,
                'name'           => $currency->name,
                'symbol'         => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
                'enabled'        => $currency->enabled,
            ];
        }

        $data        = [
            'version'              => config('firefly.version'),
            'api_version'          => config('firefly.version'),
            'php_version'          => $phpVersion,
            'os'                   => $phpOs,
            'driver'               => $currentDriver,
            'authentication_guard' => config('firefly.authentication_guard'),
            'locale'               => $locale,
            'language'             => $language,
            'timezone'             => $timezone,
            'date_range'           => [
                'start' => null !== $start ? $start->format('Y-m-d') : null,
                'end'   => null !== $end ? $end->format('Y-m-d') : null,
            ],
            'primary_currency'     => [
                'id'             => $this->primaryCurrency->id,
                'code'           => $this->primaryCurrency->code,
                'symbol'         => $this->primaryCurrency->symbol,
                'decimal_places' => $this->primaryCurrency->decimal_places,
            ],
            'currencies'           => $currencies,
        ];

        return response()->api(['data' => $data])->header('Content-Type', self::JSON_CONTENT_TYPE);
    }
}
