<?php

/*
 * RescanControllerTest.php
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

namespace Tests\integration\Api\Models\Bill;

use FireflyIII\Models\Bill;
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
final class RescanControllerTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    public function testRescanActiveInactiveBillReturns422(): void
    {
        Passport::actingAs($this->user);

        $bill                          = new Bill();
        $bill->user_id                 = $this->user->id;
        $bill->user_group_id           = $this->user->user_group_id;
        $bill->name                    = 'Test Inactive Bill';
        $bill->match                   = 'test inactive bill';
        $bill->amount_min              = '10.00';
        $bill->amount_max              = '20.00';
        $bill->date                    = now();
        $bill->repeat_freq             = 'monthly';
        $bill->skip                    = 0;
        $bill->automatch               = true;
        $bill->active                  = false;
        $bill->transaction_currency_id = 1;
        $bill->save();

        $response = $this->postJson(route('api.v1.bills.extra.rescan', ['bill' => $bill->id]));
        $response->assertStatus(422);
    }

    public function testRescanActiveBillWithNoRulesReturns422(): void
    {
        Passport::actingAs($this->user);

        $bill                          = new Bill();
        $bill->user_id                 = $this->user->id;
        $bill->user_group_id           = $this->user->user_group_id;
        $bill->name                    = 'Test Active Bill No Rules';
        $bill->match                   = 'test active bill no rules';
        $bill->amount_min              = '10.00';
        $bill->amount_max              = '20.00';
        $bill->date                    = now();
        $bill->repeat_freq             = 'monthly';
        $bill->skip                    = 0;
        $bill->automatch               = true;
        $bill->active                  = true;
        $bill->transaction_currency_id = 1;
        $bill->save();

        $response = $this->postJson(route('api.v1.bills.extra.rescan', ['bill' => $bill->id]));
        $response->assertStatus(422);
    }

    public function testRescanRequiresAuthentication(): void
    {
        $response = $this->postJson(route('api.v1.bills.extra.rescan', ['bill' => 99999]));
        $response->assertStatus(401);
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->user instanceof User) {
            $this->user = $this->createAuthenticatedUser();
        }
    }
}
