<?php

namespace Nawasara\News\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Daftar-izin untuk GET /api/v1/news/articles.
 *
 * Sengaja ditulis sebagai daftar KOLOM YANG BOLEH KELUAR, bukan membuang
 * beberapa dari toArray(): dengan daftar-larang, setiap kolom baru di masa
 * depan otomatis ikut terkirim, termasuk yang tidak seharusnya.
 *
 * Yang ditahan dan alasannya:
 *   - `id`, `wp_id`, `source_id`, `category_id` — kunci internal; pencarian
 *     dari luar memakai `slug`.
 *   - `created_at` / `updated_at` — catatan sinkronisasi, bukan tanggal
 *     redaksional. `published_at` yang bermakna bagi pembaca.
 */
class ArticleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_image' => $this->cover_image,
            'link' => $this->link,
            'source' => $this->whenLoaded('source', fn () => [
                'slug' => $this->source->slug,
                'name' => $this->source->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'slug' => $this->category->slug,
                'name' => $this->category->display_name,
            ]),
            'published_at' => $this->published_at
                ?->setTimezone(config('nawasara-news.display_timezone', 'Asia/Jakarta'))
                ?->toIso8601String(),
        ];
    }
}
