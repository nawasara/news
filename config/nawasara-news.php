<?php

return [
    // ⚠️ Alamat situs TIDAK lagi di sini — sumber berita disimpan di tabel
    // `nawasara_news_sources` dan dikelola staf lewat panel. Menambah situs
    // baru tidak perlu menunggu pembaruan aplikasi.
    //
    // Batas jumlah artikel juga per sumber, bukan satu angka global: situs
    // kabupaten menerbitkan jauh lebih sering daripada situs dinas, dan
    // menyamakan keduanya berarti salah satunya pasti keliru.

    'wp_http_timeout' => env('NAWASARA_NEWS_WP_HTTP_TIMEOUT', 15),

    'scheduler' => [
        'enabled' => env('NAWASARA_NEWS_SCHEDULER_ENABLED', true),
    ],

    // Menit antar sinkronisasi terjadwal.
    'sync_interval' => env('NAWASARA_NEWS_SYNC_INTERVAL', 60),

    // Zona waktu untuk menampilkan tanggal terbit.
    'display_timezone' => env('NAWASARA_NEWS_DISPLAY_TIMEZONE', 'Asia/Jakarta'),
];
