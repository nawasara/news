<?php

namespace Nawasara\News\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nawasara\News\Services\ArticleHtmlSanitizer;

/**
 * Daftar-izin untuk GET /api/v1/news/articles/{slug}.
 * Kolom yang ditahan sama dengan ArticleListResource, ditambah `content`.
 */
class ArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            // Dibersihkan juga saat dibaca: baris lama tersimpan sebelum
            // sanitasi saat sinkron ada (audit keamanan 15 Oktober 2026, S9),
            // dan aplikasi mobile merendernya di WebView.
            'excerpt' => ArticleHtmlSanitizer::clean($this->excerpt),
            'content' => ArticleHtmlSanitizer::clean($this->content),
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
