# nawasara/news

Mirrors news articles from the Ponorogo WordPress site into Nawasara and
exposes a public, no-auth read API for external client apps (Android/iOS).

## What this package does

1. **Sync** (`SyncNewsFromPonorogoJob`, scheduled every `sync_interval`
   minutes): fetches categories and the latest N posts from WordPress via
   `wp-json` first, entirely outside any DB transaction, then upserts by
   WordPress's own id (`wp_id`) and deletes any locally-stored row whose
   `wp_id` wasn't in the fetch — keeping the mirror to exactly the latest N.
   Only the DB write phase is wrapped in a transaction; if the WordPress
   fetch fails, nothing is touched at all — a network blip aborts the run
   rather than wiping existing data.
2. **Public API** (`GET /api/v1/news/articles`, `GET /api/v1/news/articles/{slug}`):
   genuinely no-auth — no token, no scope, no citizen login. Rate-limited
   via a plain `throttle:60,1` (not the shared `nawasara-citizen` limiter,
   since this route doesn't use the citizen auth path at all).
3. **Admin UI**: a single page (`/nawasara-news/articles`) — read-only
   articles table (no create/edit/delete — WordPress is the only source of
   truth), a detail modal per article, and a settings modal (post-count
   limit + manual sync trigger) reachable from the page header. Originally
   built as two separate pages/routes; combined into one, following the
   real established convention of one Livewire component per page handling
   everything via modals (confirmed against a real WHM reference page) —
   not the child-component-per-section split the original written
   conventions doc calls for. `news.settings.view` gates seeing the
   settings button/modal; `news.settings.update` gates actually saving or
   triggering a sync (checked server-side via `$this->authorize()`, so a
   view-only user sees the form but can't submit it).

## `gambar_sampul` shape

Not a plain URL string — an object of pre-generated WordPress image sizes,
smallest to largest:

```json
"gambar_sampul": {
  "thumbnail": "https://.../photo-80x80.jpg",
  "medium": "https://.../photo-768x456.jpg",
  "medium_large": "https://.../photo-768x512.jpg",
  "full": "https://.../photo.jpg"
}
```

All four keys are **always present with a working URL** whenever
`gambar_sampul` itself is non-null — a size WordPress didn't generate for a
given image (source narrower than that size's target width) is filled with
the closest **larger** available size instead, never a smaller one (see
`WordpressClient::resolveCoverImages()`). `gambar_sampul` is `null` only
when the article has no featured image at all. Coordinated directly with
the mobile team — this is a deliberate API shape, not the original simpler
single-URL design.

## Setup steps (in the monorepo)

1. Copy this folder to `packages/nawasara-news/`.
2. Root `composer.json`:
   ```json
   "require": { "nawasara/news": "^0.1 || dev-main" },
   "repositories": [{ "type": "path", "url": "./packages/nawasara-news" }]
   ```
   then `composer update nawasara/news`.
3. Register Tailwind sources in `resources/css/app.css` (matches the pattern
   every other package uses there — dual path for the Windows-junction
   issue noted in that file's own comments):
   ```css
   @source "../../packages/nawasara-news";
   @source "../../vendor/nawasara/news";
   ```
4. Set the WordPress source URL in `.env`:
   ```
   NAWASARA_NEWS_WP_BASE_URL=https://your-wordpress-site.example
   ```
5. `php artisan migrate` — includes a column-type migration
   (`gambar_sampul` string → json) if you'd already run this package before
   the `gambar_sampul_sizes` change. Requires `doctrine/dbal` for the
   `->change()` call on MySQL if it isn't already installed elsewhere in
   this app. **If you already have synced articles**: existing rows hold
   plain URL strings in that column, which aren't valid JSON — Eloquent's
   `array` cast decodes those to `null` rather than erroring, so
   `gambar_sampul` will read as `null` for old rows until the next sync
   run overwrites them with the new object shape. Self-healing within one
   sync interval, not a data-loss concern — but don't be alarmed if images
   briefly disappear from the admin table/API right after this migration.
6. `php artisan db:seed --class="Nawasara\News\Database\Seeders\PermissionSeeder"`
7. Run a sync manually to populate data before the schedule kicks in:
   `php artisan tinker` → `Nawasara\News\Jobs\SyncNewsFromPonorogoJob::dispatchSync();`
   (or `dispatch()` if you'd rather wait for the queue worker)

## Verification status

**Confirmed against real source** (thanks for the uploads):
- `AbstractSyncJob`'s actual contract — constructor args, retry/backoff
  behavior, `execute(): array|null` return type. The job in this package
  was adjusted to match: fetches WordPress data *before* opening a DB
  transaction (not during), and the scheduled dispatch now correctly passes
  `triggerSource: 'scheduled'` (was silently defaulting to `'manual'`).
- `PermissionMiddleware` is `Spatie\Permission\Middleware\PermissionMiddleware`
  — not a Nawasara-specific class as originally guessed. Fixed in
  `routes/web.php`, and the now-unused `nawasara/auth-primitives` dependency
  was removed from `composer.json`.
- `<x-slot:table>` usage — confirmed correct, no change needed.
- `filter-group`'s props (`model`, `label`, `items`) — confirmed correct.
- **`filter-panel.blade.php` itself** — now confirmed. This one caught a
  real mistake, not just an unverified guess: I'd previously changed
  `Table.php`'s `$categoryId` to an array based on `filter-group`'s
  `(state[model]||[]).length` check. That check is only the panel's
  internal Alpine UI bookkeeping (it always holds arrays client-side for
  the badge counter) — what actually reaches `$wire.set()` depends on
  whether the model name is listed in the panel's `:multiple` prop.
  `categoryId` isn't, so it's single-select and gets flushed as a scalar.
  Reverted `$categoryId` back to `?int`, `whereIn` back to `where`. Also
  added the `<div data-filter-chips>` target the panel teleports its active-
  filter chips into — without it, chips may not render at all (the panel's
  own comments claim an inline fallback exists, but the code shown only
  gates the teleport on `x-if="hasChipTarget"` with no visible else-branch).
  One more thing this surfaced: `filter-panel`'s own doc-comment usage
  example shows `filter-group` taking an `:options` prop — but the real
  `@props` array is `items`. That comment is stale in their own codebase;
  trust the `@props` declaration over doc-comment examples generally.
- **`nawasara-api.route.prefix`** — confirmed, exists exactly as assumed
  (`config('nawasara-api.route.prefix', 'api/v1')`). Also picked up
  `nawasara-api.rate_limit.per_minute` from the same config file and wired
  it into the public route's throttle instead of a hardcoded `60` — so
  tuning `NAWASARA_API_RATE_PER_MINUTE` affects this route too, even though
  it bypasses `api.auth`/`api.citizen` entirely.
- **`search-input.blade.php`** — confirmed, and caught a real structural
  mistake: it was nested inside `filter-panel`'s slot, but that slot is
  specifically the panel's dimension-list column. `search-input`'s own
  docblock says to use it "in a toolbar row alongside a filter-panel", i.e.
  as a sibling, not a child. Moved it out to sit next to `filter-panel` in a
  flex toolbar row. Binding itself (`wire:model.live.debounce`, `model`
  prop defaulting to `'search'`) was already correct as guessed.
- **WordPress's `date` field is site-local time with no timezone marker**
  — caught by testing against the live site. Switched to `date_gmt`
  (explicitly marked UTC) — see `WordpressClient.php`.
- **`include[]` media batching** — tested directly against the live site
  (`GET /wp-json/wp/v2/media?include[]=43135`) and confirmed working
  exactly as implemented: `guid.rendered` is the right field path, and it
  correctly resolved to the same image as post `43131`'s `featured_media`.
  No changes needed.

## Verification is complete

Every file this package depends on has been checked against either real
Nawasara source or real WordPress API responses — `AbstractSyncJob`,
`PermissionMiddleware`, `filter-panel`/`filter-group`/`table`/`search-input`
from `nawasara-ui`, `nawasara-api`'s config, a live wp-json post sample, and
a live wp-json media sample (`include[]` batching). Nothing on this list is
still a guess.

One optional (not a bug) consideration surfaced by the media sample:
`guid.rendered` — which you explicitly asked to use — points to the
full-size original image. WordPress's response also includes smaller
pre-generated sizes (`media_details.sizes.medium`/`.thumbnail`). If mobile
data usage on the list endpoint (20 cover images per page) ever becomes a
concern, the list endpoint could switch to a smaller size while detail
keeps the full one — but this is exactly what was asked for, so left as-is
unless you want it changed.

## Smoke test

```
php artisan optimize:clear
php artisan package:discover
php artisan route:list --path=api/v1/news
php artisan route:list --name=nawasara-news
php artisan migrate --pretend
php artisan db:seed --class="Nawasara\News\Database\Seeders\PermissionSeeder"
php artisan schedule:list | grep "nawasara-news"
```

Then:
1. Dispatch the sync job manually (see step 7 above), confirm
   `nawasara_news_articles`/`nawasara_news_categories` populate.
2. Re-run it — confirm it upserts (no duplicates) rather than re-inserting.
3. Temporarily lower `latest_post_limit` via the settings modal, sync again,
   confirm older rows get pruned.
4. Call `GET /api/v1/news/articles` with **no** `Authorization` header at
   all — should succeed (this is the whole point).
5. Confirm the response body never contains `id` or `category_id` anywhere
   (allow-list leak test).
6. Kill network access to WordPress mid-sync (or point `wp_base_url` at a
   bad host) and confirm the job fails loudly and existing data is
   untouched — NOT silently wiped.
