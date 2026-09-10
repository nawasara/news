<?php

namespace Nawasara\News\Jobs;

use Illuminate\Support\Facades\DB;
use Nawasara\News\Models\Article;
use Nawasara\News\Models\Category;
use Nawasara\News\Models\Source;
use Nawasara\News\Services\WordpressClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Menarik berita dari situs-situs WordPress yang terdaftar.
 *
 * Tanpa payload, job ini menyinkronkan SEMUA sumber aktif. Dengan
 * `['source_id' => N]`, hanya satu sumber — dipakai tombol "Sync sekarang"
 * per baris di panel.
 *
 * Satu sumber yang gagal TIDAK menggagalkan sumber lain: galatnya dicatat di
 * kolom `last_error` sumber tersebut lalu proses lanjut. Kegagalan satu situs
 * dinas tidak boleh menghentikan berita kabupaten.
 */
class SyncNewsJob extends AbstractSyncJob
{
    public int $timeout = 300;

    protected function service(): string
    {
        return 'news';
    }

    protected function action(): string
    {
        return 'sync_articles';
    }

    protected function targetType(): ?string
    {
        return 'NewsSource';
    }

    protected function targetId(): ?string
    {
        return isset($this->payload['source_id'])
            ? (string) $this->payload['source_id']
            : null;
    }

    protected function execute(): array|null
    {
        $sources = Source::query()
            ->when(
                isset($this->payload['source_id']),
                fn ($q) => $q->whereKey($this->payload['source_id']),
                fn ($q) => $q->active(),
            )
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            return ['sources' => 0, 'articles_created' => 0, 'articles_updated' => 0];
        }

        $total = [
            'sources' => 0,
            'sources_failed' => 0,
            'categories_created' => 0,
            'categories_updated' => 0,
            'articles_created' => 0,
            'articles_updated' => 0,
        ];

        foreach ($sources as $source) {
            try {
                $stats = $this->syncSource($source);

                foreach ($stats as $key => $value) {
                    $total[$key] += $value;
                }

                $total['sources']++;
            } catch (\Throwable $e) {
                // Dicatat di barisnya sendiri supaya terlihat di panel.
                // Tanpa ini, sumber yang mati hanya tampak "tidak ada artikel
                // baru" — mudah disangka sepi padahal rusak.
                $source->update(['last_error' => $e->getMessage()]);
                $total['sources_failed']++;

                \Log::warning("Sinkronisasi berita gagal untuk {$source->slug}: ".$e->getMessage());
            }
        }

        return $total;
    }

    /**
     * @return array<string, int>
     */
    protected function syncSource(Source $source): array
    {
        $client = new WordpressClient(
            $source->base_url,
            (int) config('nawasara-news.wp_http_timeout', 15),
        );

        // Ambil dari WordPress DULU, seluruhnya di luar transaksi — menahan
        // transaksi selama panggilan HTTP yang lambat berisiko mengunci baris
        // berkepanjangan. Bila salah satu gagal, transaksi tidak pernah
        // dibuka sama sekali, jadi tidak ada yang tersentuh di basis data.
        $remoteCategories = $client->getCategories();
        $remotePosts = $client->getLatestPosts($source->latest_post_limit);

        return DB::transaction(function () use ($source, $remoteCategories, $remotePosts) {
            $categoryStats = $this->writeCategories($source, $remoteCategories);
            $articleStats = $this->writeArticles($source, $remotePosts);

            $source->update([
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            return array_merge($categoryStats, $articleStats);
        });
    }

    /**
     * @return array<string, int>
     */
    protected function writeCategories(Source $source, array $remote): array
    {
        $created = 0;
        $updated = 0;

        foreach ($remote as $row) {
            $category = Category::query()->updateOrCreate(
                ['source_id' => $source->id, 'wp_id' => $row['wp_id']],
                ['slug' => $row['slug'], 'name' => $row['name']],
            );

            $category->wasRecentlyCreated ? $created++ : $updated++;
        }

        // ⚠️ Kategori yang tidak lagi ada di WordPress SENGAJA tidak dihapus.
        //
        // Menghapusnya akan memutus `category_id` artikel yang masih tersimpan
        // (nullOnDelete), sehingga artikel lama kehilangan kategorinya hanya
        // karena kategori itu dirapikan di sisi WordPress.
        return [
            'categories_created' => $created,
            'categories_updated' => $updated,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function writeArticles(Source $source, array $remote): array
    {
        $created = 0;
        $updated = 0;

        // Peta id kategori WP -> id lokal, diambil SETELAH writeCategories()
        // berjalan (transaksi yang sama), agar kategori yang baru dibuat pun
        // tetap terhubung. Disaring per sumber — dua situs berbeda boleh
        // memakai wp_id yang sama untuk kategori yang berlainan.
        $categoryMap = Category::query()
            ->where('source_id', $source->id)
            ->pluck('id', 'wp_id');

        foreach ($remote as $row) {
            $categoryId = $row['category_wp_id']
                ? ($categoryMap[$row['category_wp_id']] ?? null)
                : null;

            $article = Article::query()->updateOrCreate(
                ['source_id' => $source->id, 'wp_id' => $row['wp_id']],
                [
                    'category_id' => $categoryId,
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'excerpt' => $row['excerpt'],
                    'content' => $row['content'],
                    'link' => $row['link'],
                    'cover_image' => $row['cover_image'],
                    'published_at' => $row['published_at'],
                ],
            );

            $article->wasRecentlyCreated ? $created++ : $updated++;
        }

        // ⚠️ Artikel yang tidak muncul di N terbaru SENGAJA TIDAK DIHAPUS.
        //
        // Versi sebelumnya menghapusnya agar Nawasara "sama persis" dengan
        // WordPress. Akibatnya arsip berita menyusut diam-diam: menaikkan
        // jumlah artikel tidak mengembalikan yang sudah hilang, dan tautan
        // yang pernah dibagikan ke publik mati begitu artikelnya bergeser
        // keluar dari N terbaru.
        //
        // Nawasara kini menumpuk, bukan mencerminkan. Artikel yang benar-benar
        // ditarik turun di WordPress tetap tinggal di sini — itu keputusan
        // sadar, dan menghapusnya adalah pekerjaan manusia lewat panel, bukan
        // efek samping sinkronisasi.
        return [
            'articles_created' => $created,
            'articles_updated' => $updated,
        ];
    }
}
