<?php

namespace Nawasara\News\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pembungkus tipis REST API wp-json milik satu situs WordPress.
 *
 * Sengaja generik — base URL adalah argumen konstruktor, tidak ada yang
 * dipatok ke Ponorogo. Satu instance melayani satu sumber, dan job sync
 * membuat satu instance per baris `nawasara_news_sources`.
 */
class WordpressClient
{
    public function __construct(
        protected string $baseUrl,
        protected int $timeoutSeconds = 15,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Semua kategori, dipaginasi di dalam.
     *
     * `name` ikut diambil karena itu yang dibaca manusia ("Berita Daerah");
     * slug dipertahankan sebagai kunci yang stabil untuk penyaringan.
     *
     * @return array<int, array{wp_id: int, slug: string, name: ?string}>
     */
    public function getCategories(): array
    {
        $categories = [];
        $page = 1;

        do {
            $response = $this->get('/wp-json/wp/v2/categories', [
                'per_page' => 100,
                'page' => $page,
            ]);

            $batch = $response->json() ?? [];

            foreach ($batch as $row) {
                $categories[] = [
                    'wp_id' => (int) $row['id'],
                    'slug' => (string) $row['slug'],
                    'name' => isset($row['name'])
                        ? html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8')
                        : null,
                ];
            }

            $page++;
        } while (count($batch) === 100);

        return $categories;
    }

    /**
     * Latest $limit posts (by `modified`, so edits resurface — not just new
     * publishes), normalized, with cover image URLs already resolved via a
     * batched media lookup (not one request per post).
     *
     * @return array<int, array{
     *   wp_id: int, category_wp_id: ?int, title: string, slug: string,
     *   excerpt: ?string, content: string, link: string,
     *   cover_image: ?array<string, string>, published_at: ?string
     * }>
     */
    public function getLatestPosts(int $limit): array
    {
        $limit = max(1, $limit);
        $posts = [];
        $page = 1;

        while (count($posts) < $limit) {
            $remaining = $limit - count($posts);
            $perPage = min(100, $remaining);

            $response = $this->get('/wp-json/wp/v2/posts', [
                'per_page' => $perPage,
                'page' => $page,
                'orderby' => 'modified',
                'order' => 'desc',
            ]);

            $batch = $response->json() ?? [];

            if (empty($batch)) {
                break; // fewer posts exist on WP than $limit — not an error
            }

            foreach ($batch as $row) {
                $posts[] = $row;
            }

            $page++;
        }

        $coverSizesByMediaId = $this->resolveCoverImages($posts);

        return array_map(function (array $row) use ($coverSizesByMediaId) {
            $featuredMediaId = (int) ($row['featured_media'] ?? 0);

            return [
                'wp_id' => (int) $row['id'],
                // WP posts technically support multiple categories, but this
                // site only ever uses one in practice (confirmed) — taking
                // the first is the correct behavior here, not a workaround.
                'category_wp_id' => ! empty($row['categories'])
                    ? (int) $row['categories'][0]
                    : null,
                'title' => html_entity_decode(
                    (string) ($row['title']['rendered'] ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                ),
                'slug' => (string) $row['slug'],
                'excerpt' => $row['excerpt']['rendered'] ?? null,
                'content' => (string) ($row['content']['rendered'] ?? ''),
                'link' => (string) ($row['link'] ?? ''),
                'cover_image' => $featuredMediaId > 0
                    ? ($coverSizesByMediaId[$featuredMediaId] ?? null)
                    : null,
                'published_at' => isset($row['date_gmt'])
                    // date_gmt has no timezone marker either, but IS UTC —
                    // appending 'Z' makes Carbon parse it unambiguously as
                    // UTC on the way into the dateTime cast, rather than
                    // assuming the app's configured timezone. Deliberately
                    // NOT using the plain `date` field: it's WP's site-local
                    // time (confirmed ~7h offset from date_gmt in a real
                    // sample from this install) with no timezone marker —
                    // using it directly would silently misparse by however
                    // many hours off if the app's timezone config doesn't
                    // happen to match WP's site timezone exactly.
                    ? $row['date_gmt'].'Z'
                    : null,
            ];
        }, $posts);
    }

    /**
     * Sizes exposed per image, smallest to largest. 'full' is core-
     * guaranteed to always be present in a WP media response whenever the
     * media object exists at all — every fallback walk below terminates.
     */
    protected const SIZE_ORDER = ['thumbnail', 'medium', 'medium_large', 'full'];

    /**
     * Batched cover-image resolution: collect every distinct featured_media
     * id across the given posts, then fetch them via `include[]` in chunks
     * of 100 — one (or a handful of) request(s) total instead of one
     * request per post. For each image, returns ALL of self::SIZE_ORDER as
     * keys, always — a size WordPress didn't generate (source image
     * narrower than that size's target width) gets filled with the
     * closest LARGER size that does exist, never a smaller one. This
     * means consumers (API resources, admin blade) never need their own
     * null-fallback chain: whenever the whole array is non-null, every
     * key in it has a working URL.
     *
     * @param  array<int, array<string, mixed>>  $posts  raw WP post rows
     * @return array<int, array<string, string>> media id => [size => url, ...]
     */
    protected function resolveCoverImages(array $posts): array
    {
        $mediaIds = collect($posts)
            ->pluck('featured_media')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values();

        if ($mediaIds->isEmpty()) {
            return [];
        }

        $sizesByMediaId = [];

        foreach ($mediaIds->chunk(100) as $chunk) {
            $response = $this->get('/wp-json/wp/v2/media', [
                'include' => $chunk->values()->all(),
                'per_page' => $chunk->count(),
            ]);

            foreach ($response->json() ?? [] as $media) {
                $mediaId = (int) $media['id'];
                $available = $media['media_details']['sizes'] ?? [];

                // Walk LARGEST -> smallest, carrying forward the closest
                // larger URL found so far. This guarantees "fall upward,
                // never downward" — a missing 'medium' gets filled with
                // 'medium_large' if present, else 'full'; never with the
                // smaller 'thumbnail'.
                $closestLargerUrl = null;
                $resolved = [];
                foreach (array_reverse(self::SIZE_ORDER) as $sizeName) {
                    $url = $available[$sizeName]['source_url'] ?? null;
                    if ($url) {
                        $closestLargerUrl = $url;
                    }
                    $resolved[$sizeName] = $url ?? $closestLargerUrl;
                }

                // Re-order back to SIZE_ORDER (thumbnail -> full) — the
                // reversed build loop above inserted keys largest-first,
                // which doesn't affect correctness but reads oddly as JSON.
                $ordered = [];
                foreach (self::SIZE_ORDER as $sizeName) {
                    $ordered[$sizeName] = $resolved[$sizeName];
                }

                $sizesByMediaId[$mediaId] = $ordered;
            }
        }

        return $sizesByMediaId;
    }

    protected function get(string $path, array $query = [])
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->get($this->baseUrl.$path, $query);

        if ($response->failed()) {
            throw new RuntimeException(
                "WordPress request failed: GET {$path} returned {$response->status()}"
            );
        }

        return $response;
    }
}
