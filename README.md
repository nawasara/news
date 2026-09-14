# nawasara/news

Pulls news articles from the WordPress sites run by the Ponorogo Regency
government into Nawasara, and serves them through a public API with no
authentication for client apps. The SuperApps client (Flutter/Android) is the
first consumer.

Works together with `nawasara/ui` (the panel views), `nawasara/core` (settings
storage), `nawasara/sync` (the sync job framework), and `nawasara/api` (the
`/api/v1` route prefix).

## Why mirror instead of link

The app could just open the WordPress site directly. What makes that approach
inadequate: each OPD has its own site with a different theme, layout, and speed,
and some have no decent mobile version. A citizen who taps a story lands on a
page whose shape is unpredictable and sometimes slow.

By mirroring, articles from every site appear in one consistent shape inside the
app, can be searched across sites, and stay readable even when the source site
is having trouble. A link to the original site is still included for anyone who
wants to read it at the source.

## Status v0.3.0

| Feature | |
|---|---|
| Multiple sources, managed from the panel | ready |
| Settings page (rate limit, sync interval, timeout) | ready |
| Scheduled sync plus manual sync per source | ready |
| Connection test when adding a source | ready |
| Public API: list, detail, source list | ready |
| Source and category filters, title and category search | ready |
| Marker for failing sources with their error message | ready |
| Editing articles inside Nawasara | none, by design; the source is WordPress |
| Notification when a source fails for days | not built yet |
| Hiding a single article from the public API | not built yet |

## Design notes

### 1. Sources and settings live in the database, not config

The first version used a single config value, `NAWASARA_NEWS_WP_BASE_URL`. That
meant adding an agency site required editing `.env` and redeploying the app. But
the people who know which sites need pulling are Kominfo staff, not developers.

The same goes for the API rate limit. The person who sees "news failed to load"
complaints come in is staff; waiting for a deploy window to raise it means the
citizen app keeps failing the whole time.

Sources became the `nawasara_news_sources` table with its own page. Other
settings go into `nawasara_settings` (the cached key-value store owned by
`nawasara/core`) under the `news.` prefix, rather than a table of their own,
because their shape really is a handful of numbers and flags, not an entity.

Config stays as a fallback; do not remove it. The read order is: database,
config, then the built-in defaults in code. On a fresh install the settings
table is still empty, and the first read happens when the ServiceProvider
registers routes, before anyone has a chance to open the panel. Without the
fallback the rate limit is zero, `throttle:0,1` rejects every request, and the
entire news API is dead on an install that looked successful.

For the same reason, `NewsSettings` rejects zero or negative values even when
they are stored in the database, and reads are wrapped in `try/catch`: the first
read can happen before migrations run, when `php artisan migrate` itself boots
the app.

Deleting a setting must go through the **model**, not `where(...)->delete()`.
`Setting` clears its cache on the `deleted` event, and that event only fires for
model instances. A mass delete skips it: the row is gone, but the old value
keeps being read from cache for an hour, so the "Restore Defaults" button looks
like it does nothing at all.

### 2. `wp_id` and `slug` uniqueness is per source

Each WordPress install numbers its own categories and posts starting from 1. An
agency site almost certainly has a category with `wp_id` 1, the same as the
regency site. Slugs are the same: "hut-ri-ke-81" is published by nearly every
government site on the same day.

Global uniqueness (which was correct when there was only one source) would make
a second site's sync **overwrite** the first site's rows instead of creating new
ones. The failure is silent: no error, just articles whose content changes on
its own.

```php
$table->unique(['source_id', 'wp_id']);
$table->unique(['source_id', 'slug']);
```

The category map in `SyncNewsJob::writeArticles()` is also filtered by
`where('source_id', ...)` before use. The regression test is in
`tests/MultiSourceIsolationTest.php`, and the case is proven on real data:
ponorogo.go.id and dinsos.ponorogo.go.id each have a category with the exact
same `wp_id`.

### 3. Old articles are not deleted

The first version deleted articles that no longer appeared in the latest N, so
Nawasara would be an exact copy of WordPress. The effect was that the news
archive quietly shrank: raising the count limit did not bring back what was
gone, and links already shared publicly broke once their article slid out of
range.

Nawasara now accumulates rather than mirrors. An article genuinely taken down on
WordPress stays here. That is a deliberate choice, and removing it is a human
job through the panel, not a side effect of syncing.

The same goes for categories: deleting them would break `category_id` on older
articles just because the category was tidied up on the WordPress side.

Deactivating a source (`is_active = false`) stops the pull without removing the
articles that already exist. Only deleting the row removes them.

### 4. The rate limit is separate from other APIs, and much looser

`rate_limit_per_minute` deliberately does not use
`nawasara-api.rate_limit.per_minute`, and defaults to 300.

Laravel's throttle counts per key, and on a route that does not check a token
the key is the **IP address**. Phones on a mobile network do not have their own
public IP: hundreds of thousands of one carrier's subscribers exit through a
handful of NAT addresses, so from the server's side they look like a single
visitor sharing a single quota.

At 60, like the other APIs, a few dozen citizens opening SuperApps at once is
enough to make the rest get 429, and all they see is "failed to load". The
complaint sounds like *"sometimes it works, sometimes it doesn't"*, and it
cannot be reproduced from the office, whose IP is its own and quiet.

The number is separate rather than raising the global one, because what is
protected here is only articles that anyone is allowed to read anyway. Other
endpoints write data and carry tokens; both deserve to stay strict.

If clustered "news failed to load" reports arrive from the same area, that is a
sign this limit was reached, not a network problem. Raise it then.

### 5. Publish date is read from `date_gmt`, not `date`

WordPress's `date` column is the site's local time with no timezone marker, and
on this install the difference from `date_gmt` is about 7 hours. Using it
directly misreads the time by that difference whenever the app's timezone does
not happen to match the site's exactly.

`date_gmt` also has no marker, but it is genuinely UTC, so a `Z` is appended so
Carbon parses it without guessing.

## Setup

```bash
composer require nawasara/news
php artisan migrate
php artisan db:seed --class="Nawasara\News\Database\Seeders\PermissionSeeder"
php artisan db:seed --class="Nawasara\News\Database\Seeders\SourceSeeder"
```

Register it in `resources/css/app.css`. Without this, none of the Tailwind
classes in this package's blades are compiled, and the pages render with no
styling:

```css
@source "../../vendor/nawasara/news";
```

The first source (ponorogo.go.id) is created by `SourceSeeder`. The rest are
added by staff through **Berita → Sumber Berita → Tambah Sumber**; the Test
Connection button confirms the address really is a WordPress site with wp-json
before saving.

### Environment (optional; everything has a default)

| | Default | |
|---|---|---|
| `NAWASARA_NEWS_RATE_LIMIT_PER_MINUTE` | 300 | Can be overridden from the Settings page |
| `NAWASARA_NEWS_SYNC_INTERVAL` | 60 | Minutes between syncs |
| `NAWASARA_NEWS_WP_HTTP_TIMEOUT` | 15 | Seconds to wait for the source site |
| `NAWASARA_NEWS_SCHEDULER_ENABLED` | true | |
| `NAWASARA_NEWS_DISPLAY_TIMEZONE` | Asia/Jakarta | |

## Public endpoints

No token, no scope, no login. Limited only by the throttle (default 300/minute
**per IP address**).

| Endpoint | Notes |
|---|---|
| `GET /api/v1/news/articles` | List. Filters: `?source=`, `?category=`, `?per_page=` (1-100) |
| `GET /api/v1/news/articles/{slug}` | Detail. Add `?source=` when the slug can repeat across sites |
| `GET /api/v1/news/sources` | Source list, so the client does not have to hard-code the site list |

Slugs are only unique per source. Without `?source=`, the detail endpoint
returns the **most recently published** article whose slug matches. That is
defined behavior, not left to database order.

The Resource is written as an **allow-list**: columns are named one by one,
rather than `toArray()` with a few dropped. With a deny-list, every future
column would be sent automatically, including ones that should not be. `id`,
`wp_id`, `source_id`, and `category_id` never leave; `/sources` also does not
leak `base_url` or `last_error`.

### The shape of `cover_image`

Not a single URL but an object holding the sizes WordPress has already prepared,
smallest to largest:

```json
"cover_image": {
  "thumbnail": "https://.../photo-80x80.jpg",
  "medium": "https://.../photo-768x456.jpg",
  "medium_large": "https://.../photo-768x512.jpg",
  "full": "https://.../photo.jpg"
}
```

Every key is guaranteed to exist and hold a working URL, as long as the whole
value is not null. A size WordPress did not produce (because the original image
was narrower than the target) is filled with the nearest **larger** size, never
a smaller one. So the client does not have to build its own fallback chain; the
image may look heavier than needed, but it never breaks.

## Models

| | |
|---|---|
| `Source` | A WordPress site being pulled. `scopeActive()`, `isFailing()` |
| `Category` | Category per source. `display_name` falls back to the slug when `name` is empty |
| `Article` | Article per source. `cover_image` is cast to an array |

## Permissions

| | |
|---|---|
| `news.article.view` | View the article list and detail |
| `news.source.view` | View the source list |
| `news.source.create` | Add a source |
| `news.source.update` | Edit / activate / deactivate |
| `news.source.delete` | Delete a source and its articles |
| `news.source.sync` | Run a manual sync |
| `news.setting.view` | Open the Settings page |
| `news.setting.update` | Save / restore settings |

## Roadmap

- Notification when a source fails repeatedly (currently only shown as a
  "Failing" badge on the Sumber Berita page)
- Hide a specific article from the public API without deleting it
- Per-source sync history, not just the last time

## Author

Pringgo J. Saputro, Dinas Kominfo Kabupaten Ponorogo

## License

MIT.
