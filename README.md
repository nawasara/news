# nawasara/news

Menarik artikel berita dari situs-situs WordPress milik Pemerintah Kabupaten
Ponorogo ke dalam Nawasara, dan menyediakannya lewat API publik tanpa
autentikasi untuk aplikasi klien (Android/iOS).

Bekerja bersama `nawasara/ui` (tampilan panel), `nawasara/sync` (kerangka job
sinkronisasi), dan `nawasara/api` (awalan route serta batas laju).

## Status v0.2.0

| Fitur | |
|---|---|
| Sumber jamak, dikelola lewat panel | ✅ |
| Sinkronisasi terjadwal + manual per sumber | ✅ |
| Uji koneksi saat menambah sumber | ✅ |
| API publik: daftar, detail, daftar sumber | ✅ |
| Saringan sumber & kategori di panel | ✅ |
| Pencarian judul & kategori | ✅ |
| Penyuntingan artikel di Nawasara | ❌ — sengaja; sumbernya WordPress |
| Notifikasi saat sumber gagal berhari-hari | ⏳ |

## Setup

```bash
composer require nawasara/news
php artisan migrate
php artisan db:seed --class="Nawasara\News\Database\Seeders\PermissionSeeder"
php artisan db:seed --class="Nawasara\News\Database\Seeders\SourceSeeder"
```

Daftarkan di `resources/css/app.css` — **tanpa ini seluruh kelas Tailwind di
blade paket ini tidak ikut dikompilasi**, dan halamannya tampil tanpa gaya:

```css
@source "../../vendor/nawasara/news";
```

## Sumber berita disimpan di basis data, bukan di config

Ini keputusan yang paling mungkin dibatalkan orang lain kalau tidak
dijelaskan.

Versi pertama memakai satu nilai config, `NAWASARA_NEWS_WP_BASE_URL`. Itu
berarti menambahkan situs dinas — disbudparpora, dinkes, dan seterusnya —
menuntut penyuntingan berkas `.env` dan penerapan ulang aplikasi. Padahal yang
tahu situs mana yang perlu ditarik adalah staf Kominfo, bukan pengembang, dan
mereka tidak seharusnya menunggu jadwal deploy untuk itu.

Karena itu sumber berpindah ke tabel `nawasara_news_sources` dengan halaman
pengelolaannya sendiri. `wp_http_timeout` dan `sync_interval` tetap di config —
keduanya menyangkut perilaku teknis, bukan keputusan redaksional.

## Keunikan `wp_id` dan `slug` adalah PER SUMBER

Setiap instalasi WordPress menomori kategori dan postingnya sendiri mulai dari
1. Situs dinas hampir pasti punya kategori ber-`wp_id` 1, sama seperti situs
kabupaten. Begitu pula slug: "hut-ri-ke-81" diterbitkan hampir semua situs
pemerintah pada hari yang sama.

Keunikan global — yang benar sewaktu hanya ada satu sumber — akan membuat
sinkronisasi situs kedua **menimpa** baris situs pertama alih-alih membuat
baris baru. Bentuk kegagalannya diam: tidak ada galat, hanya artikel yang
berubah isinya sendiri.

Karena itu:

```php
$table->unique(['source_id', 'wp_id']);
$table->unique(['source_id', 'slug']);
```

dan peta kategori di `SyncNewsJob::writeArticles()` disaring `where('source_id', ...)`
sebelum dipakai. Uji regresinya ada di `tests/MultiSourceIsolationTest.php`.

## Artikel lama TIDAK dihapus

Versi pertama menghapus artikel yang tidak muncul di N terbaru, agar Nawasara
"sama persis" dengan WordPress. Akibatnya arsip berita menyusut diam-diam:
menaikkan batas jumlah tidak mengembalikan yang sudah hilang, dan tautan yang
sudah dibagikan ke publik mati begitu artikelnya bergeser keluar.

Nawasara kini **menumpuk**, bukan mencerminkan. Artikel yang benar-benar
ditarik turun di WordPress tetap tinggal di sini — itu keputusan sadar, dan
membuangnya adalah pekerjaan manusia lewat panel, bukan efek samping
sinkronisasi.

Hal yang sama berlaku untuk kategori: menghapusnya akan memutus `category_id`
artikel lama hanya karena kategori itu dirapikan di sisi WordPress.

Menonaktifkan sumber (`is_active = false`) menghentikan penarikan **tanpa**
membuang artikel yang sudah ada. Yang membuang adalah menghapus barisnya.

## Bentuk `cover_image`

Bukan satu URL, melainkan objek berisi ukuran-ukuran yang sudah disiapkan
WordPress, dari terkecil ke terbesar:

```json
"cover_image": {
  "thumbnail": "https://.../photo-80x80.jpg",
  "medium": "https://.../photo-768x456.jpg",
  "medium_large": "https://.../photo-768x512.jpg",
  "full": "https://.../photo.jpg"
}
```

Setiap kunci **dijamin ada dan berisi URL yang bekerja** selama keseluruhan
nilainya tidak null. Ukuran yang tidak dihasilkan WordPress — karena gambar
aslinya lebih sempit dari target — diisi dengan ukuran yang lebih BESAR
terdekat, tidak pernah yang lebih kecil. Jadi klien tidak perlu menyusun
rantai cadangannya sendiri; gambar bisa tampak lebih berat dari perlunya,
tetapi tidak pernah pecah.

## Tanggal terbit dibaca dari `date_gmt`, bukan `date`

Kolom `date` milik WordPress adalah waktu lokal situs tanpa penanda zona
waktu — pada instalasi ini selisihnya sekitar 7 jam dari `date_gmt`.
Memakainya langsung akan salah baca sebanyak selisih itu bila zona waktu
aplikasi tidak kebetulan sama persis dengan zona waktu situs.

`date_gmt` juga tanpa penanda, tetapi memang UTC, sehingga huruf `Z`
ditambahkan agar Carbon menguraikannya tanpa menebak.

## Endpoint publik

Tanpa token, tanpa scope, tanpa login. Dibatasi hanya oleh throttle
(`nawasara-api.rate_limit.per_minute`).

| Endpoint | Keterangan |
|---|---|
| `GET /api/v1/news/articles` | Daftar. Saringan: `?source=`, `?category=`, `?per_page=` (1–100) |
| `GET /api/v1/news/articles/{slug}` | Detail. Tambahkan `?source=` bila slug-nya bisa berulang antar situs |
| `GET /api/v1/news/sources` | Daftar sumber, agar klien tak perlu memasang daftar situs secara tetap |

⚠️ Slug hanya unik per sumber. Tanpa `?source=`, endpoint detail menjawab
dengan artikel **terbit paling akhir** yang slug-nya cocok — perilaku yang
dipastikan, bukan diserahkan pada urutan basis data.

Resource ditulis sebagai **daftar-izin**: kolom disebutkan satu per satu, bukan
`toArray()` lalu membuang beberapa. Dengan daftar-larang, setiap kolom baru di
masa depan otomatis ikut terkirim, termasuk yang tidak seharusnya. `id` dan
`wp_id` tidak pernah keluar.

## Permission

| | |
|---|---|
| `news.article.view` | Melihat daftar & detail artikel |
| `news.source.view` | Melihat daftar sumber |
| `news.source.create` | Menambah sumber |
| `news.source.update` | Mengubah / mengaktifkan / menonaktifkan |
| `news.source.delete` | Menghapus sumber beserta artikelnya |
| `news.source.sync` | Menjalankan sinkronisasi manual |

## Roadmap

- Notifikasi ketika sebuah sumber gagal berturut-turut (kini hanya tampak
  sebagai lencana "Bermasalah" di halaman Sumber Berita)
- Menyembunyikan artikel tertentu dari API publik tanpa menghapusnya
- Riwayat sinkronisasi per sumber, bukan hanya waktu terakhir

## Author

Pringgo J. Saputro — Dinas Kominfo Kabupaten Ponorogo

## License

Proprietary.
