<?php

/*
 * ConvertControllerTest.php
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

namespace Tests\integration\Api\Models\Transaction;

use FireflyIII\Models\TransactionType;
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
final class ConvertControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    public function testConvertRequiresAuthentication(): void
    {
        $transactionType = TransactionType::where('type', 'Withdrawal')->first();
        $response        = $this->postJson(route('api.v1.transactions.extra.convert', [
            'transactionGroup' => 1,
            'transactionType'  => strtolower($transactionType->type),
        ]));
        $response->assertStatus(401);
    }

    public function testConvertReturns404ForNonExistentGroup(): void
    {
        Passport::actingAs($this->user);
        $transactionType = TransactionType::where('type', 'Withdrawal')->first();
        $response        = $this->postJson(route('api.v1.transactions.extra.convert', [
            'transactionGroup' => 999999,
            'transactionType'  => strtolower($transactionType->type),
        ]));
        $response->assertStatus(404);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createAuthenticatedUser();
    }
}
