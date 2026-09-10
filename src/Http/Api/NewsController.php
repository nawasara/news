<?php

namespace Nawasara\News\Http\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nawasara\News\Http\Resources\ArticleDetailResource;
use Nawasara\News\Http\Resources\ArticleListResource;
use Nawasara\News\Models\Article;
use Nawasara\News\Models\Source;

/**
 * API berita untuk publik — tanpa token, tanpa scope, tanpa login.
 * Lihat NewsServiceProvider::registerPublicApiRoutes(). Dibatasi hanya oleh
 * throttle.
 */
class NewsController extends Controller
{
    /**
     * GET /api/v1/news/articles
     *
     * Saringan opsional lewat query:
     *   ?source=ponorogo      slug sumber
     *   ?category=pengumuman  slug kategori
     *   ?per_page=20          1..100
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $rows = Article::query()
            ->with(['category', 'source'])
            ->when($request->query('source'), fn ($q, $slug) => $q->whereHas('source', fn ($s) => $s->where('slug', $slug)))
            ->when($request->query('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            // Pemecah seri pada `id` — orderByDesc(published_at) saja tidak
            // unik, dan baris yang tanggal terbitnya sama dapat berpindah
            // halaman di antara dua permintaan.
            ->orderByDesc('published_at')
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

    /**
     * GET /api/v1/news/sources
     *
     * Daftar sumber yang tersedia, supaya klien dapat menyusun saringannya
     * sendiri tanpa memasang daftar situs secara tetap di dalam aplikasi.
     */
    public function sources(): JsonResponse
    {
        $rows = Source::query()
            ->withCount('articles')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Source $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'article_count' => $s->articles_count,
            ])->all(),
        ]);
    }

    /**
     * GET /api/v1/news/articles/{slug}
     *
     * ⚠️ Slug hanya unik PER SUMBER — dua situs boleh menerbitkan
     * "hut-ri-ke-81" pada hari yang sama. Tanpa `?source=`, permintaan
     * dijawab dengan artikel terbaru yang slug-nya cocok, sehingga hasilnya
     * dapat berubah ketika situs lain menerbitkan slug serupa.
     *
     * Karena itu urutannya dipastikan, bukan diserahkan pada urutan basis
     * data: yang terbit paling akhir yang menang, dan itu perilaku yang
     * dapat dijelaskan. Klien yang membutuhkan kepastian mengirim `?source=`.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $article = Article::query()
            ->with(['category', 'source'])
            ->where('slug', $slug)
            ->when($request->query('source'), fn ($q, $sourceSlug) => $q->whereHas('source', fn ($s) => $s->where('slug', $sourceSlug)))
            ->orderByDesc('published_at')
            ->firstOrFail();

        return response()->json([
            'data' => (new ArticleDetailResource($article))->resolve(),
        ]);
    }
}
