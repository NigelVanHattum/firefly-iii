<?php

/*
 * BootControllerTest.php
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

namespace Tests\integration\Api\System;

use FireflyIII\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Override;
use Tests\integration\TestCase;

/**
 * @internal
 *
 * @covers \FireflyIII\Api\V1\Controllers\System\BootController
 */
final class BootControllerTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    public function testGivenAuthenticatedRequestReturnsBootData(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson(route('api.v1.system.boot'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'version',
                'api_version',
                'php_version',
                'os',
                'driver',
                'authentication_guard',
                'locale',
                'language',
                'timezone',
                'primary_currency',
                'currencies',
            ],
        ]);
    }

    public function testGivenUnauthenticatedRequestReturns401(): void
    {
        $response = $this->getJson(route('api.v1.system.boot'));

        $response->assertStatus(401);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createAuthenticatedUser();
    }
}
