<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Models\Transaction;

use Exception;
use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Enums\TransactionTypeEnum;
use FireflyIII\Events\Model\TransactionGroup\TransactionGroupEventFlags;
use FireflyIII\Events\Model\TransactionGroup\TransactionGroupEventObjects;
use FireflyIII\Events\Model\TransactionGroup\UpdatedSingleTransactionGroup;
use FireflyIII\Exceptions\FireflyException;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\TransactionGroup;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Models\TransactionType;
use FireflyIII\Repositories\Account\AccountRepositoryInterface;
use FireflyIII\Services\Internal\Update\JournalUpdateService;
use FireflyIII\Support\Facades\Steam;
use FireflyIII\Validation\AccountValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ConvertController extends Controller
{
    private AccountRepositoryInterface $accountRepository;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            $this->accountRepository = app(AccountRepositoryInterface::class);
            $this->accountRepository->setUser(auth()->user());

            return $next($request);
        });
    }

    public function convert(Request $request, TransactionGroup $group, TransactionType $destinationType): JsonResponse
    {
        if ($group->user_id !== auth()->user()->id) {
            return response()->json(['message' => 'not found'], 404);
        }

        /** @var TransactionJournal $first */
        $first = $group->transactionJournals()->first();

        if ($first->transactionType->type === $destinationType->type) {
            return response()->json(['message' => 'already this type'], 409);
        }

        $data = $request->only(['source_id', 'source_name', 'destination_id', 'destination_name']);

        try {
            /** @var TransactionJournal $journal */
            foreach ($group->transactionJournals as $journal) {
                $this->convertJournal($journal, $destinationType, $data);
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $group->refresh();

        $flags   = new TransactionGroupEventFlags();
        $objects = TransactionGroupEventObjects::collectFromTransactionGroup($group);
        event(new UpdatedSingleTransactionGroup($flags, $objects));

        return response()->json(['data' => ['message' => 'converted', 'group_id' => $group->id, 'type' => $destinationType->type]]);
    }

    /**
     * @throws FireflyException
     */
    private function convertJournal(TransactionJournal $journal, TransactionType $transactionType, array $data): TransactionJournal
    {
        /** @var AccountValidator $validator */
        $validator         = app(AccountValidator::class);
        $validator->setUser(auth()->user());
        $validator->setTransactionType($transactionType->type);

        $sourceId          = $data['source_id'][$journal->id] ?? null;
        $sourceName        = $data['source_name'][$journal->id] ?? null;
        $destinationId     = $data['destination_id'][$journal->id] ?? null;
        $destinationName   = $data['destination_name'][$journal->id] ?? null;

        $sourceId          = '' === $sourceId || null === $sourceId ? null : (int) $sourceId;
        $sourceName        = '' === $sourceName ? null : (string) $sourceName;
        $destinationId     = '' === $destinationId || null === $destinationId ? null : (int) $destinationId;
        $destinationName   = '' === $destinationName ? null : (string) $destinationName;
        $validSource       = $validator->validateSource(['id' => $sourceId, 'name' => $sourceName]);
        $validDestination  = $validator->validateDestination(['id' => $destinationId, 'name' => $destinationName]);

        if (false === $validSource) {
            throw new FireflyException(sprintf(trans('firefly.convert_invalid_source'), $journal->id));
        }
        if (false === $validDestination) {
            throw new FireflyException(sprintf(trans('firefly.convert_invalid_destination'), $journal->id));
        }

        $update            = [
            'source_id'        => $sourceId,
            'source_name'      => $sourceName,
            'destination_id'   => $destinationId,
            'destination_name' => $destinationName,
            'type'             => $transactionType->type,
        ];

        /** @var null|Transaction $sourceTransaction */
        $sourceTransaction = $journal->transactions()->where('amount', '<', 0)->first();
        $amount            = $sourceTransaction->amount ?? '0';

        if (TransactionTypeEnum::TRANSFER->value === $transactionType->type && TransactionTypeEnum::DEPOSIT->value === $journal->transactionType->type) {
            $source         = $this->accountRepository->find((int) $sourceId);
            $sourceCurrency = $this->accountRepository->getAccountCurrency($source);
            $dest           = $this->accountRepository->find((int) $destinationId);
            $destCurrency   = $this->accountRepository->getAccountCurrency($dest);
            if (
                $sourceCurrency instanceof TransactionCurrency
                && $destCurrency instanceof TransactionCurrency
                && $sourceCurrency->code !== $destCurrency->code
            ) {
                $update['currency_id']         = $sourceCurrency->id;
                $update['foreign_currency_id'] = $destCurrency->id;
                $update['foreign_amount']      = Steam::positive($amount);
            }
        }

        if (TransactionTypeEnum::TRANSFER->value === $transactionType->type && TransactionTypeEnum::WITHDRAWAL->value === $journal->transactionType->type) {
            $source         = $this->accountRepository->find((int) $sourceId);
            $sourceCurrency = $this->accountRepository->getAccountCurrency($source);
            $dest           = $this->accountRepository->find((int) $destinationId);
            $destCurrency   = $this->accountRepository->getAccountCurrency($dest);
            if (
                $sourceCurrency instanceof TransactionCurrency
                && $destCurrency instanceof TransactionCurrency
                && $sourceCurrency->code !== $destCurrency->code
            ) {
                $update['currency_id']         = $sourceCurrency->id;
                $update['foreign_currency_id'] = $destCurrency->id;
                $update['foreign_amount']      = Steam::positive($amount);
            }
        }

        /** @var JournalUpdateService $service */
        $service           = app(JournalUpdateService::class);
        $service->setTransactionJournal($journal);
        $service->setData($update);
        $service->update();
        $journal->refresh();

        return $journal;
    }
}
