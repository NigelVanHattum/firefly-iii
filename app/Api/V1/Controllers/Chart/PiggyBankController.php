<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Chart;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Models\PiggyBank;
use FireflyIII\Repositories\PiggyBank\PiggyBankRepositoryInterface;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PiggyBankController extends Controller
{
    private PiggyBankRepositoryInterface $repository;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            /** @var User $admin */
            $admin            = auth()->user();

            $this->repository = app(PiggyBankRepositoryInterface::class);
            $this->repository->setUser($admin);

            return $next($request);
        });
    }

    public function piggyBanks(Request $request): JsonResponse
    {
        $piggyBanks = $this->repository->getPiggyBanks();
        $result     = [];

        /** @var PiggyBank $piggyBank */
        foreach ($piggyBanks as $piggyBank) {
            $currency      = $piggyBank->transactionCurrency;
            $current       = $this->repository->getCurrentAmount($piggyBank);
            $target        = $piggyBank->target_amount;
            $targetFloat   = (float) $target;
            $currentFloat  = (float) $current;

            if ($targetFloat > 0) {
                $percentage = min(100, round(($currentFloat / $targetFloat) * 100, 2));
            } else {
                $percentage = 0;
            }

            $result[] = [
                'id'                       => $piggyBank->id,
                'name'                     => $piggyBank->name,
                'current_amount'           => $current,
                'target_amount'            => $target,
                'currency_code'            => $currency->code ?? '',
                'currency_symbol'          => $currency->symbol ?? '',
                'currency_decimal_places'  => $currency->decimal_places ?? 2,
                'percentage'               => $percentage,
            ];
        }

        return response()->json(['data' => $result]);
    }
}
