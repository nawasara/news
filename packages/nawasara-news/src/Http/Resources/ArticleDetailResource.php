<?php

namespace Nawasara\News\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Allow-list resource for GET /api/v1/news/articles/{slug} (detail).
 * Same blocked fields as ArticleListResource, plus adds `isi_lengkap`.
 */
class ArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'judul' => $this->judul,
            'ringkasan' => $this->ringkasan,
            'isi_lengkap' => $this->isi_lengkap,
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
