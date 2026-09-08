<?php

namespace Nawasara\News\Jobs;

use Illuminate\Support\Facades\DB;
use Nawasara\News\Models\Article;
use Nawasara\News\Models\Category;
use Nawasara\News\Models\Setting;
use Nawasara\News\Services\WordpressClient;
use Nawasara\Sync\Jobs\AbstractSyncJob;

/**
 * Verified against the real Nawasara\Sync\Jobs\AbstractSyncJob source:
 * - dispatch() accepts (instance, payload, triggeredBy, triggerSource,
 *   expectedHash) — all optional/named, so our zero-arg-except-triggerSource
 *   usage in NewsServiceProvider is correct.
 * - handle() already retries (3x, 10/30/60s backoff) and marks the tracker
 *   row failed on final attempt — execute() should just let exceptions
 *   propagate, which it does here.
 * - execute()'s declared return type is array|null; array (used below) is
 *   a valid covariant narrowing.
 */
class SyncNewsFromPonorogoJob extends AbstractSyncJob
{
    public int $timeout = 120;

    protected function service(): string
    {
        return 'news';
    }

    protected function action(): string
    {
        return 'sync_ponorogo';
    }

    protected function targetType(): ?string
    {
        return 'WordpressSource';
    }

    protected function targetId(): ?string
    {
        return 'ponorogo';
    }

    protected function execute(): array|null
    {
        $baseUrl = config('nawasara-news.wp_base_url');

        if (empty($baseUrl)) {
            // Fail loudly rather than silently no-op — a missing config
            // value should surface as a failed sync run, not a quiet skip.
            throw new \RuntimeException(
                'nawasara-news.wp_base_url is not configured — set NAWASARA_NEWS_WP_BASE_URL.'
            );
        }

        $client = new WordpressClient($baseUrl, (int) config('nawasara-news.wp_http_timeout', 15));
        $limit = Setting::current()->latest_post_limit;

        // Fetch from WordPress FIRST, entirely outside any DB transaction —
        // holding a transaction open across slow external HTTP calls risks
        // long-lived locks / connection timeouts under load. If either
        // fetch throws, we never even open a transaction, so nothing gets
        // touched in the DB at all.
        $remoteCategories = $client->getCategories();
        $remotePosts = $client->getLatestPosts($limit);

        // Only the actual DB writes are transactional — all-or-nothing
        // between the categories and articles phases, so a failure partway
        // through writing can never leave a half-synced, inconsistent state.
        return DB::transaction(function () use ($remoteCategories, $remotePosts) {
            $categoryStats = $this->writeCategories($remoteCategories);
            $articleStats = $this->writeArticles($remotePosts);

            Setting::current()->update(['last_synced_at' => now()]);

            return array_merge($categoryStats, $articleStats);
        });
    }

    protected function writeCategories(array $remote): array
    {
        $seenWpIds = [];
        $created = 0;
        $updated = 0;

        foreach ($remote as $row) {
            $category = Category::query()->updateOrCreate(
                ['wp_id' => $row['wp_id']],
                ['slug' => $row['slug']],
            );

            $category->wasRecentlyCreated ? $created++ : $updated++;
            $seenWpIds[] = $row['wp_id'];
        }

        // Prune stale rows — safe here because we only ever reach this line
        // after a fully successful fetch (see execute()).
        $deleted = Category::query()
            ->whereNotIn('wp_id', $seenWpIds)
            ->delete();

        return [
            'categories_created' => $created,
            'categories_updated' => $updated,
            'categories_deleted' => $deleted,
        ];
    }

    protected function writeArticles(array $remote): array
    {
        $seenWpIds = [];
        $created = 0;
        $updated = 0;

        // Map WP category id -> local category_id, resolved AFTER
        // writeCategories() ran (same transaction), so FKs resolve
        // correctly even for a brand-new category on a brand-new post.
        $categoryMap = Category::query()->pluck('id', 'wp_id');

        foreach ($remote as $row) {
            $categoryId = $row['category_wp_id']
                ? ($categoryMap[$row['category_wp_id']] ?? null)
                : null;

            $article = Article::query()->updateOrCreate(
                ['wp_id' => $row['wp_id']],
                [
                    'category_id' => $categoryId,
                    'judul' => $row['judul'],
                    'slug' => $row['slug'],
                    'ringkasan' => $row['ringkasan'],
                    'isi_lengkap' => $row['isi_lengkap'],
                    'link' => $row['link'],
                    'gambar_sampul' => $row['gambar_sampul'],
                    'tanggal_terbit' => $row['tanggal_terbit'],
                ],
            );

            $article->wasRecentlyCreated ? $created++ : $updated++;
            $seenWpIds[] = $row['wp_id'];
        }

        // Keep only the latest N — delete anything not in this fetch.
        $deleted = Article::query()
            ->whereNotIn('wp_id', $seenWpIds)
            ->delete();

        return [
            'articles_created' => $created,
            'articles_updated' => $updated,
            'articles_deleted' => $deleted,
        ];
    }
}
