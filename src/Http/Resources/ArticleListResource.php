<?php

namespace Nawasara\News\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Allow-list resource for GET /api/v1/news/articles (list).
 *
 * Blocked and why:
 *   - `id`          — internal PK, never leaves the system; lookup is by `slug`.
 *   - `wp_id`        — internal sync key, meaningless to API consumers.
 *   - `category_id`  — internal FK; consumers get the resolved category object.
 *   - `created_at` / `updated_at` — sync bookkeeping, not editorial dates.
 */
class ArticleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'judul' => $this->judul,
            'ringkasan' => $this->ringkasan,
            'gambar_sampul' => $this->gambar_sampul,
            'link' => $this->link,
            'kategori' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category->slug,
            ]),
            'tanggal_terbit' => $this->tanggal_terbit
                ?->setTimezone(config('nawasara-news.display_timezone', 'Asia/Jakarta'))
                ?->toIso8601String(),
        ];
    }
}
