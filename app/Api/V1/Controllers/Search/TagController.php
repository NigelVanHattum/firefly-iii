<?php

declare(strict_types=1);

namespace FireflyIII\Api\V1\Controllers\Search;

use FireflyIII\Api\V1\Controllers\Controller;
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

    public function search(Request $request): JsonResponse
    {
        $query   = trim((string) $request->get('query', ''));
        $limit   = (int) $request->get('limit', 10);

        $results = $this->repository->searchTags($query, $limit);

        $data    = $results->map(static fn ($tag) => ['id' => $tag->id, 'tag' => $tag->tag])->values()->toArray();

        return response()->json(['data' => $data]);
    }
}
