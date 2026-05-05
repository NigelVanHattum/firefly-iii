<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Chart;

use Carbon\Carbon;
use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Models\Bill;
use FireflyIII\Repositories\Bill\BillRepositoryInterface;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillController extends Controller
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

    public function frontpage(Request $request): JsonResponse
    {
        $startParam = $request->get('start');
        $endParam   = $request->get('end');

        if (null !== $startParam) {
            $start = Carbon::createFromFormat('Y-m-d', $startParam)->startOfDay();
        } else {
            $start = session('start', today(config('app.timezone'))->startOfMonth());
        }

        if (null !== $endParam) {
            $end = Carbon::createFromFormat('Y-m-d', $endParam)->endOfDay();
        } else {
            $end = session('end', today(config('app.timezone'))->endOfMonth());
        }

        $bills  = $this->repository->getActiveBills();
        $result = [];

        /** @var Bill $bill */
        foreach ($bills as $bill) {
            $currency   = $bill->transactionCurrency;
            $paidDates  = $this->repository->getPaidDatesInRange($bill, $start, $end)
                ->map(static fn ($journal) => $journal->date instanceof Carbon ? $journal->date->toDateString() : (string) $journal->date)
                ->values()
                ->toArray();
            $unpaidDates = $this->repository->getPayDatesInRange($bill, $start, $end)
                ->map(static fn (Carbon $date) => $date->toDateString())
                ->values()
                ->toArray();

            $result[] = [
                'id'                       => $bill->id,
                'name'                     => $bill->name,
                'amount_min'               => $bill->amount_min,
                'amount_max'               => $bill->amount_max,
                'currency_code'            => $currency->code ?? '',
                'currency_symbol'          => $currency->symbol ?? '',
                'currency_decimal_places'  => $currency->decimal_places ?? 2,
                'paid_dates'               => $paidDates,
                'unpaid_dates'             => $unpaidDates,
            ];
        }

        return response()->json(['data' => $result]);
    }
}
