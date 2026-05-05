<?php

/*
 * SuggestControllerTest.php
 * Copyright (c) 2025 james@firefly-iii.org
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

namespace Tests\integration\Api\Models\Recurrence;

use FireflyIII\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Override;
use Tests\integration\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class SuggestControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function testSuggestWithFutureDate(): void
    {
        $response = $this->getJson(route('api.v1.recurrences.extra.suggest').'?date=2030-01-15');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'daily' => ['label', 'selected'],
        ]);
    }

    public function testSuggestWithPastDateAndPastFlag(): void
    {
        $response = $this->getJson(route('api.v1.recurrences.extra.suggest').'?date=2020-01-15&past=true');
        $response->assertStatus(200);
    }

    public function testSuggestWithNoDateDefaultsToToday(): void
    {
        $response = $this->getJson(route('api.v1.recurrences.extra.suggest'));
        $response->assertStatus(200);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createAuthenticatedUser();
        Passport::actingAs($this->user);
    }
}
