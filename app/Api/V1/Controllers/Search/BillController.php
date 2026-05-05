<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Search;

use FireflyIII\Api\V1\Controllers\Controller;
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

    public function search(Request $request): JsonResponse
    {
        $query   = trim((string) $request->get('query', ''));
        $limit   = (int) $request->get('limit', 10);

        $results = $this->repository->searchBill($query, $limit);

        $data    = $results->map(static fn ($bill) => ['id' => $bill->id, 'name' => $bill->name])->values()->toArray();

        return response()->json(['data' => $data]);
    }
}
