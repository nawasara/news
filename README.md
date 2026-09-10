# nawasara/news

Menarik artikel berita dari situs-situs WordPress milik Pemerintah Kabupaten
Ponorogo ke dalam Nawasara, dan menyediakannya lewat API publik tanpa
autentikasi untuk aplikasi klien — SuperApps (Flutter/Android) yang pertama.

Bekerja bersama `nawasara/ui` (tampilan panel), `nawasara/core` (penyimpanan
setelan), `nawasara/sync` (kerangka job sinkronisasi), dan `nawasara/api`
(awalan route `/api/v1`).

## Kenapa mencerminkan, bukan menautkan

Aplikasi bisa saja membuka situs WordPress-nya langsung. Yang membuat cara itu
tidak memadai: setiap OPD punya situsnya sendiri dengan tema, tata letak, dan
kecepatan yang berbeda-beda, sebagian tanpa versi seluler yang layak. Warga
yang menekan satu berita akan mendarat di halaman yang bentuknya tak terduga
dan kadang lambat.

Dengan mencerminkan, artikel dari semua situs tampil dalam satu bentuk yang
sama di dalam aplikasi, dapat dicari lintas-situs, dan tetap terbaca meski
situs asalnya sedang bermasalah. Tautan ke situs asli tetap disertakan bagi
yang ingin membacanya di sumbernya.

## Status v0.3.0

| Fitur | |
|---|---|
| Sumber jamak, dikelola lewat panel | ✅ |
| Halaman Pengaturan (batas laju, jeda sync, timeout) | ✅ |
| Sinkronisasi terjadwal + manual per sumber | ✅ |
| Uji koneksi saat menambah sumber | ✅ |
| API publik: daftar, detail, daftar sumber | ✅ |
| Saringan sumber & kategori, pencarian judul & kategori | ✅ |
| Penanda sumber bermasalah beserta pesan galatnya | ✅ |
| Penyuntingan artikel di Nawasara | ❌ — sengaja; sumbernya WordPress |
| Notifikasi saat sumber gagal berhari-hari | ⏳ |
| Menyembunyikan satu artikel dari API publik | ⏳ |

## Keputusan yang mudah dibatalkan tanpa tahu alasannya

### 1. Sumber dan setelan ada di basis data, bukan config

Versi pertama memakai satu nilai config, `NAWASARA_NEWS_WP_BASE_URL`. Itu
berarti menambahkan situs dinas menuntut penyuntingan `.env` dan penerapan
ulang aplikasi. Padahal yang tahu situs mana yang perlu ditarik adalah staf
Kominfo, bukan pengembang.

Hal yang sama berlaku untuk batas laju API. Orang yang melihat keluhan "berita
gagal dimuat" masuk adalah staf; menunggu jendela deploy untuk menaikkannya
berarti aplikasi warga tetap gagal sepanjang penantian.

Sumber menjadi tabel `nawasara_news_sources` dengan halamannya sendiri. Setelan
lain masuk `nawasara_settings` (key-value milik `nawasara/core`, sudah
ber-cache) dengan awalan `news.` — bukan tabel sendiri, karena bentuknya memang
segelintir angka dan bendera, bukan entitas.

⚠️ **Config tetap menjadi cadangan, jangan dihapus.** Urutan pembacaan: basis
data → config → angka bawaan di kode. Pada pemasangan baru tabel setelan masih
kosong, dan pembacaan pertama terjadi saat ServiceProvider mendaftarkan route —
sebelum ada kesempatan siapa pun membuka panel. Tanpa cadangan, batas laju
bernilai nol, `throttle:0,1` menolak setiap permintaan, dan seluruh API berita
mati pada pemasangan yang tampak berhasil.

Karena alasan yang sama, `NewsSettings` menolak nilai nol atau negatif meski
tersimpan di basis data, dan pembacaannya dibungkus `try/catch` — pembacaan
pertama dapat terjadi sebelum migrasi dijalankan, saat `php artisan migrate`
sendiri mem-boot aplikasi.

⚠️ Menghapus setelan harus lewat **model**, bukan `where(...)->delete()`.
`Setting` membersihkan cache-nya di event `deleted`, dan event itu hanya menyala
untuk instance model. Penghapusan massal melewatinya: barisnya hilang, tetapi
nilai lama tetap terbaca dari cache selama satu jam — tombol "Kembalikan
Bawaan" akan tampak tidak berfungsi sama sekali.

### 2. Keunikan `wp_id` dan `slug` adalah PER SUMBER

Setiap instalasi WordPress menomori kategori dan postingnya sendiri mulai dari
1. Situs dinas hampir pasti punya kategori ber-`wp_id` 1, sama seperti situs
kabupaten. Begitu pula slug: "hut-ri-ke-81" diterbitkan hampir semua situs
pemerintah pada hari yang sama.

Keunikan global — yang benar sewaktu hanya ada satu sumber — akan membuat
sinkronisasi situs kedua **menimpa** baris situs pertama alih-alih membuat
baris baru. Bentuk kegagalannya diam: tidak ada galat, hanya artikel yang
berubah isinya sendiri.

```php
$table->unique(['source_id', 'wp_id']);
$table->unique(['source_id', 'slug']);
```

Peta kategori di `SyncNewsJob::writeArticles()` juga disaring
`where('source_id', ...)` sebelum dipakai. Uji regresinya ada di
`tests/MultiSourceIsolationTest.php`, dan kasusnya terbukti pada data nyata:
ponorogo.go.id dan dinsos.ponorogo.go.id punya satu kategori dengan `wp_id`
yang sama persis.

### 3. Artikel lama TIDAK dihapus

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

### 4. Batas laju dipisah dari API lain, dan jauh lebih longgar

`rate_limit_per_minute` **sengaja tidak memakai**
`nawasara-api.rate_limit.per_minute`, dengan bawaan 300.

Throttle Laravel menghitung per kunci, dan pada route yang tidak memeriksa
token kuncinya adalah **alamat IP**. Ponsel di jaringan seluler tidak punya IP
publik sendiri: ratusan ribu pelanggan satu operator keluar lewat segelintir
alamat NAT, sehingga dari sisi server mereka tampak sebagai satu pengunjung dan
berbagi satu jatah.

Dengan 60 seperti API lainnya, beberapa puluh warga yang membuka SuperApps
bersamaan sudah cukup membuat sisanya menerima 429 — dan yang mereka lihat
hanya "gagal memuat". Keluhannya berbunyi *"kadang bisa kadang tidak"*, dan
tidak dapat ditiru dari kantor, yang IP-nya sendiri dan lengang.

Angkanya dipisah, bukan menaikkan yang global, karena yang dilindungi di sini
hanya artikel yang memang boleh dibaca siapa saja. Endpoint lain menulis data
dan memegang token — keduanya pantas tetap ketat.

> Naikkan bila datang laporan "berita gagal dimuat" berkelompok dari daerah
> yang sama; itu tanda batas ini yang tercapai, bukan gangguan jaringan.

### 5. Tanggal terbit dibaca dari `date_gmt`, bukan `date`

Kolom `date` milik WordPress adalah waktu lokal situs tanpa penanda zona
waktu — pada instalasi ini selisihnya sekitar 7 jam dari `date_gmt`.
Memakainya langsung akan salah baca sebanyak selisih itu bila zona waktu
aplikasi tidak kebetulan sama persis dengan zona waktu situs.

`date_gmt` juga tanpa penanda, tetapi memang UTC, sehingga huruf `Z`
ditambahkan agar Carbon menguraikannya tanpa menebak.

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

Sumber pertama (ponorogo.go.id) dibuat oleh `SourceSeeder`. Sisanya ditambahkan
staf lewat **Berita → Sumber Berita → Tambah Sumber**; tombol Uji Koneksi
memastikan alamatnya benar-benar situs WordPress ber-wp-json sebelum disimpan.

### Environment (opsional — semua ada bawaannya)

| | Bawaan | |
|---|---|---|
| `NAWASARA_NEWS_RATE_LIMIT_PER_MINUTE` | 300 | Dapat ditimpa dari halaman Pengaturan |
| `NAWASARA_NEWS_SYNC_INTERVAL` | 60 | Menit antar sinkronisasi |
| `NAWASARA_NEWS_WP_HTTP_TIMEOUT` | 15 | Detik menunggu situs sumber |
| `NAWASARA_NEWS_SCHEDULER_ENABLED` | true | |
| `NAWASARA_NEWS_DISPLAY_TIMEZONE` | Asia/Jakarta | |

## Endpoint publik

Tanpa token, tanpa scope, tanpa login. Dibatasi hanya oleh throttle
(bawaan 300/menit **per alamat IP**).

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
masa depan otomatis ikut terkirim, termasuk yang tidak seharusnya. `id`,
`wp_id`, `source_id`, dan `category_id` tidak pernah keluar; `/sources` juga
tidak membocorkan `base_url` maupun `last_error`.

### Bentuk `cover_image`

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

## Model

| | |
|---|---|
| `Source` | Situs WordPress yang ditarik. `scopeActive()`, `isFailing()` |
| `Category` | Kategori per sumber. `display_name` jatuh ke slug bila `name` kosong |
| `Article` | Artikel per sumber. `cover_image` di-cast array |

## Permissions

| | |
|---|---|
| `news.article.view` | Melihat daftar & detail artikel |
| `news.source.view` | Melihat daftar sumber |
| `news.source.create` | Menambah sumber |
| `news.source.update` | Mengubah / mengaktifkan / menonaktifkan |
| `news.source.delete` | Menghapus sumber beserta artikelnya |
| `news.source.sync` | Menjalankan sinkronisasi manual |
| `news.setting.view` | Membuka halaman Pengaturan |
| `news.setting.update` | Menyimpan / mengembalikan setelan |

## Roadmap

- Notifikasi ketika sebuah sumber gagal berturut-turut (kini hanya tampak
  sebagai lencana "Bermasalah" di halaman Sumber Berita)
- Menyembunyikan artikel tertentu dari API publik tanpa menghapusnya
- Riwayat sinkronisasi per sumber, bukan hanya waktu terakhir

## Author

Pringgo J. Saputro — Dinas Kominfo Kabupaten Ponorogo

## License

MIT.
