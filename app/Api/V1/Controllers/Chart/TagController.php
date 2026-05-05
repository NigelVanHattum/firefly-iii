<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Chart;

use Carbon\Carbon;
use FireflyIII\Api\V1\Controllers\Controller;
use FireflyIII\Models\Tag;
use FireflyIII\Repositories\Tag\TagRepositoryInterface;
use FireflyIII\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TagController extends Controller
{
    private TagRepositoryInterface $repository;

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            /** @var User $admin */
            $admin            = auth()->user();

            $this->repository = app(TagRepositoryInterface::class);
            $this->repository->setUser($admin);

            return $next($request);
        });
    }

    public function tag(Request $request): JsonResponse
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

        $tags   = $this->repository->get();
        $result = [];

        /** @var Tag $tag */
        foreach ($tags as $tag) {
            $sums = $this->repository->sumsOfTag($tag, $start, $end);

            foreach ($sums as $currencyData) {
                $sum      = '0';
                foreach (['withdrawal', 'deposit', 'transfer', 'reconciliation', 'opening balance'] as $type) {
                    if (isset($currencyData[$type])) {
                        $sum = bcadd($sum, (string) $currencyData[$type]);
                    }
                }

                $result[] = [
                    'id'           => $tag->id,
                    'tag'          => $tag->tag,
                    'sum'          => $sum,
                    'currency_code' => $currencyData['currency_code'] ?? ($currencyData['currency_name'] ?? ''),
                ];
            }
        }

        return response()->json(['data' => $result]);
    }
}
