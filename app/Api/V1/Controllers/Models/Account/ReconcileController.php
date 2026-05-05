<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Models\Account;

use Carbon\Carbon;
use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Enums\TransactionTypeEnum;
use FireflyIII\Exceptions\DuplicateTransactionException;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Factory\TransactionGroupFactory;
use FireflyIII\Helpers\Collector\GroupCollectorInterface;
use FireflyIII\Models\Account;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Repositories\Journal\JournalRepositoryInterface;
use FireflyIII\Support\Facades\Steam;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ReconcileController extends Controller
{
    private AccountRepositoryInterface $accountRepository;
    private JournalRepositoryInterface $journalRepository;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            $this->accountRepository = app(AccountRepositoryInterface::class);
            $this->accountRepository->setUser(auth()->user());
            $this->journalRepository = app(JournalRepositoryInterface::class);
            $this->journalRepository->setUser(auth()->user());

            return $next($request);
        });
    }

    public function overview(Request $request, Account $account): JsonResponse
    {
        $startBalance    = $request->get('startBalance');
        $endBalance      = $request->get('endBalance');
        $accountCurrency = $this->accountRepository->getAccountCurrency($account) ?? $this->primaryCurrency;
        $amount          = '0';
        $clearedAmount   = '0';

        $startParam = $request->get('start');
        $endParam   = $request->get('end');

        if (null === $startParam || null === $endParam) {
            return response()->json(['message' => 'Invalid dates submitted.'], 422);
        }

        $start = Carbon::createFromFormat('Y-m-d', $startParam, config('app.timezone'));
        $end   = Carbon::createFromFormat('Y-m-d', $endParam, config('app.timezone'));

        if (false === $start || false === $end) {
            return response()->json(['message' => 'Invalid dates submitted.'], 422);
        }

        if (!is_numeric($startBalance)) {
            $startBalance = '0';
        }
        if (!is_numeric($endBalance)) {
            $endBalance = '0';
        }
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }
        $end->endOfDay();
        $start->startOfDay();

        $selectedIds     = $request->get('journals') ?? [];
        $clearedIds      = $request->get('cleared') ?? [];
        $journals        = [];
        $clearedJournals = [];

        if (count($selectedIds) > 0) {
            /** @var GroupCollectorInterface $collector */
            $collector = app(GroupCollectorInterface::class);
            $collector->setJournalIds($selectedIds);
            $journals  = $collector->getExtractedJournals();
        }

        if (count($clearedIds) > 0) {
            /** @var GroupCollectorInterface $collector */
            $collector       = app(GroupCollectorInterface::class);
            $collector->setJournalIds($clearedIds);
            $clearedJournals = $collector->getExtractedJournals();
        }

        /** @var array $journal */
        foreach ($journals as $journal) {
            $amount = $this->processJournal($account, $accountCurrency, $journal, $amount);
        }
        Log::debug(sprintf('Final amount is %s', $amount));

        /** @var array $journal */
        foreach ($clearedJournals as $journal) {
            if ($journal['date'] <= $end) {
                $clearedAmount = $this->processJournal($account, $accountCurrency, $journal, $clearedAmount);
            }
        }
        Log::debug(sprintf('Start balance: "%s"', $startBalance));
        Log::debug(sprintf('End balance: "%s"', $endBalance));
        Log::debug(sprintf('Cleared amount: "%s"', $clearedAmount));
        Log::debug(sprintf('Amount: "%s"', $amount));

        $difference   = bcadd(bcadd(bcsub($startBalance, $endBalance), $clearedAmount), $amount);
        $diffCompare  = bccomp($difference, '0');
        $countCleared = count($clearedJournals);
        $reconSum     = bcadd(bcadd($startBalance, $amount), $clearedAmount);

        return response()->json([
            'data' => [
                'difference'         => $difference,
                'difference_compare' => $diffCompare,
                'cleared_amount'     => $clearedAmount,
                'start_balance'      => $startBalance,
                'end_balance'        => $endBalance,
                'amount'             => $amount,
                'recon_sum'          => $reconSum,
                'count_cleared'      => $countCleared,
                'currency'           => [
                    'id'             => $accountCurrency->id,
                    'code'           => $accountCurrency->code,
                    'symbol'         => $accountCurrency->symbol,
                    'decimal_places' => $accountCurrency->decimal_places,
                ],
            ],
        ]);
    }

    public function transactions(Request $request, Account $account): JsonResponse
    {
        $startParam = $request->get('start');
        $endParam   = $request->get('end');

        if (null === $startParam || null === $endParam) {
            return response()->json(['message' => 'Invalid dates submitted.'], 422);
        }

        $start = Carbon::createFromFormat('Y-m-d', $startParam, config('app.timezone'));
        $end   = Carbon::createFromFormat('Y-m-d', $endParam, config('app.timezone'));

        if (false === $start || false === $end) {
            return response()->json(['message' => 'Invalid dates submitted.'], 422);
        }

        if ($end->lt($start)) {
            [$end, $start] = [$start, $end];
        }
        $start->endOfDay();
        $end->endOfDay();

        $startDate = clone $start;
        $startDate->subDay();

        $currency     = $this->accountRepository->getAccountCurrency($account) ?? $this->primaryCurrency;

        Log::debug(sprintf('transactions: Call accountsBalancesOptimized with date/time "%s"', $startDate->toIso8601String()));
        Log::debug(sprintf('transactions2: Call accountsBalancesOptimized with date/time "%s"', $end->toIso8601String()));

        $startBalance = Steam::accountsBalancesOptimized(new Collection()->push($account), $startDate)[$account->id];
        $endBalance   = Steam::accountsBalancesOptimized(new Collection()->push($account), $end)[$account->id];

        foreach ($startBalance as $key => $value) {
            $startBalance[$key] = Steam::bcround($value, $currency->decimal_places);
        }
        foreach ($endBalance as $key => $value) {
            $endBalance[$key] = Steam::bcround($value, $currency->decimal_places);
        }

        $selectionStart = clone $start;
        $selectionStart->startOfDay();
        $selectionStart->subDays(3);
        $selectionEnd   = clone $end;
        $selectionEnd->endOfDay();
        $selectionEnd->addDays(3);

        $start->startOfDay();

        /** @var GroupCollectorInterface $collector */
        $collector = app(GroupCollectorInterface::class);
        $collector
            ->setAccounts(new Collection()->push($account))
            ->setRange($selectionStart, $selectionEnd)
            ->withBudgetInformation()
            ->withCategoryInformation()
            ->withAccountInformation()
        ;
        $array    = $collector->getExtractedJournals();
        $journals = $this->processTransactions($account, $array);

        return response()->json([
            'data' => [
                'start_balance' => $startBalance,
                'end_balance'   => $endBalance,
                'transactions'  => $journals,
            ],
        ]);
    }

    public function store(Request $request, Account $account): JsonResponse
    {
        $startParam  = $request->get('start');
        $endParam    = $request->get('end');
        $journalIds  = $request->get('journal_ids', []);
        $reconcile   = $request->get('reconcile', 'nothing');
        $startBal    = (string) $request->get('start_balance', '0');
        $endBal      = (string) $request->get('end_balance', '0');

        $start = null;
        $end   = null;

        if (null !== $startParam) {
            $start = Carbon::createFromFormat('Y-m-d', $startParam, config('app.timezone'));
            if (false === $start) {
                $start = null;
            }
        }
        if (null !== $endParam) {
            $end = Carbon::createFromFormat('Y-m-d', $endParam, config('app.timezone'));
            if (false === $end) {
                $end = null;
            }
        }

        if ($start instanceof Carbon && $end instanceof Carbon && $end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        /** @var string $journalId */
        foreach ($journalIds as $journalId) {
            $this->journalRepository->reconcileById((int) $journalId);
        }
        Log::debug('Reconciled all transactions.');

        if ('create' === $reconcile && $start instanceof Carbon && $end instanceof Carbon) {
            $difference = bcsub($endBal, $startBal);
            $result     = $this->createReconciliation($account, $start, $end, $difference);
            if ('' !== $result) {
                return response()->json(['message' => $result], 500);
            }
        }

        Log::debug('End of reconcile store routine.');

        return response()->json(['data' => ['message' => 'reconciliation stored']]);
    }

    private function createReconciliation(Account $account, Carbon $start, Carbon $end, string $difference): string
    {
        $reconciliation = $this->accountRepository->getReconciliation($account);
        $currency       = $this->accountRepository->getAccountCurrency($account) ?? $this->primaryCurrency;
        $source         = $reconciliation;
        $destination    = $account;
        if (1 === bccomp($difference, '0')) {
            $source      = $account;
            $destination = $reconciliation;
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $description = trans('firefly.reconciliation_transaction_title', [
            'from' => $start->isoFormat('MMM D, YYYY'),
            'to'   => $end->isoFormat('MMM D, YYYY'),
        ]);

        $submission = [
            'user'         => auth()->user(),
            'user_group'   => auth()->user()->userGroup,
            'group_title'  => null,
            'transactions' => [[
                'user'                => auth()->user(),
                'user_group'          => auth()->user()->userGroup,
                'type'                => strtolower(TransactionTypeEnum::RECONCILIATION->value),
                'date'                => $end,
                'order'               => 0,
                'currency_id'         => $currency->id,
                'foreign_currency_id' => null,
                'amount'              => $difference,
                'foreign_amount'      => null,
                'description'         => $description,
                'source_id'           => $source->id,
                'destination_id'      => $destination->id,
                'reconciled'          => true,
            ]],
        ];

        /** @var TransactionGroupFactory $factory */
        $factory = app(TransactionGroupFactory::class);

        /** @var User $user */
        $user = auth()->user();
        $factory->setUser($user);

        try {
            $factory->create($submission);
        } catch (FireflyException $e) {
            return $e->getMessage();
        }

        return '';
    }

    private function processJournal(Account $account, TransactionCurrency $currency, array $journal, string $amount): string
    {
        $toAdd = '0';
        Log::debug(sprintf('User submitted %s #%d: "%s"', $journal['transaction_type_type'], $journal['transaction_journal_id'], $journal['description']));

        if ($account->id === $journal['source_account_id']) {
            if ($currency->id === $journal['currency_id']) {
                $toAdd = $journal['amount'];
            }
            if (null !== $journal['foreign_currency_id'] && $journal['foreign_currency_id'] === $currency->id) {
                $toAdd = $journal['foreign_amount'];
            }
        }
        if ($account->id === $journal['destination_account_id']) {
            if ($currency->id === $journal['currency_id']) {
                $toAdd = bcmul((string) $journal['amount'], '-1');
            }
            if (null !== $journal['foreign_currency_id'] && $journal['foreign_currency_id'] === $currency->id) {
                $toAdd = bcmul((string) $journal['foreign_amount'], '-1');
            }
        }

        Log::debug(sprintf('Going to add %s to %s', $toAdd, $amount));
        $amount = bcadd($amount, (string) $toAdd);
        Log::debug(sprintf('Result is %s', $amount));

        return $amount;
    }

    private function processTransactions(Account $account, array $array): array
    {
        $journals = [];

        /** @var array $journal */
        foreach ($array as $journal) {
            $inverse = false;

            if (TransactionTypeEnum::DEPOSIT->value === $journal['transaction_type_type']) {
                $inverse = true;
            }
            if (TransactionTypeEnum::TRANSFER->value === $journal['transaction_type_type'] && $account->id === $journal['destination_account_id']) {
                $inverse = true;
            }
            if (TransactionTypeEnum::OPENING_BALANCE->value === $journal['transaction_type_type'] && $account->id === $journal['destination_account_id']) {
                $inverse = true;
            }

            if ($inverse) {
                $journal['amount'] = Steam::positive($journal['amount']);
                if (null !== $journal['foreign_amount']) {
                    $journal['foreign_amount'] = Steam::positive($journal['foreign_amount']);
                }
            }

            $journals[] = $journal;
        }

        return $journals;
    }
}
