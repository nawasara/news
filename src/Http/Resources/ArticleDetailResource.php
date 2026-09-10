<?php

namespace Nawasara\News\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'excerpt' => $this->excerpt,
            'content' => $this->content,
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
