# nawasara/news

Mirrors news articles from the Ponorogo WordPress site into Nawasara and
exposes a public, no-auth read API for external client apps (Android/iOS).

## What this package does

1. **Sync** Sinkronisasi Otomatis (`SyncNewsFromPonorogoJob`)
   Berjalan otomatis sesuai waktu yang diatur (`sync_interval`).
   Mengambil kategori dan N artikel terbaru dari WordPress melalui wp-json.
   Memperbarui data berdasarkan wp_id dan menghapus artikel lokal yang 
   tidak masuk dalam daftar terbaru, sehingga data selalu sama persis.
   Aman dari error: Jika pengambilan data dari WordPress gagal karena 
   masalah jaringan, proses dibatalkan dan tidak ada data lokal yang berubah.
2. **Public API** `GET /api/v1/news/articles` dan `GET /api/v1/news/articles/{slug}`. 
   Sepenuhnya bebas akses tanpa token atau login. Rate limit (throttle:60,1).
3. **Admin UI**: Tersedia dalam satu halaman (`/nawasara-news/articles`). 
   Tabel artikel bersifat hanya-baca (read-only). Pengaturan batas jumlah 
   artikel dan tombol sinkronisasi manual.

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
