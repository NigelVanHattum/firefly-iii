<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Models\Bill;

use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Models\Bill;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\TransactionRules\Engine\RuleEngineInterface;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RescanController extends Controller
{
    private BillRepositoryInterface $repository;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            /** @var User $admin */
            $admin            = auth()->user();

            $this->repository = app(BillRepositoryInterface::class);
            $this->repository->setUser($admin);

            return $next($request);
        });
    }

    public function rescan(Request $request, Bill $bill): JsonResponse
    {
        if (!$bill->active) {
            return response()->json(['message' => 'cannot scan inactive bill'], 422);
        }

        $set = $this->repository->getRulesForBill($bill);
        if (0 === $set->count()) {
            return response()->json(['message' => 'no rules for bill'], 422);
        }

        $this->repository->unlinkAll($bill);

        /** @var RuleEngineInterface $ruleEngine */
        $ruleEngine = app(RuleEngineInterface::class);
        $ruleEngine->setRules($set);
        $ruleEngine->fire();

        return response()->json(['data' => ['message' => 'bill rescanned']]);
    }
}
