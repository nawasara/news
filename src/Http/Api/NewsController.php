<?php

namespace Nawasara\News\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\News\Http\Resources\ArticleDetailResource;
use Nawasara\News\Http\Resources\ArticleListResource;
use Nawasara\News\Models\Article;

/**
 * Genuinely public, no-auth — see NewsServiceProvider::registerPublicApiRoutes().
 * No token, no scope, no citizen login. Gated only by throttle:60,1.
 */
class NewsController extends Controller
{
    // GET /api/v1/news/articles
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        // Tie-breaker on `id` — orderByDesc(tanggal_terbit) alone isn't
        // unique, rows sharing a publish date could shift between pages.
        $rows = Article::query()
            ->with('category')
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => ArticleListResource::collection($rows->items())->resolve(),
            'meta' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    // GET /api/v1/news/articles/{slug}
    public function show(string $slug): JsonResponse
    {
        $article = Article::query()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => (new ArticleDetailResource($article))->resolve(),
        ]);
    }
}
