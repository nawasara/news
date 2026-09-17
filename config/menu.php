<?php

$prefix = 'nawasara-news';

/*
| Sidebar Berita, bagian dari workspace Ponorogo Hub.
|
| ⚠️ Workspace id `ponorogo-hub` DIBAGI dengan nawasara/aspirations,
| nawasara/citizen, nawasara/emergency, nawasara/tourism, dan nawasara/pbb.
| Semuanya layanan untuk aplikasi warga, jadi muncul bersama alih-alih
| tersebar di antara menu infrastruktur.
|
| WorkspaceManager menggabungkan entri ber-id sama dan mengambil label + icon
| dari paket yang termuat LEBIH DULU secara alfabet, yaitu
| `nawasara-aspirations`. Label dan icon di sini HARUS sama persis dengan yang
| ada di sana, atau judul sidebar berubah tergantung paket mana yang terpasang.
|
| ⚠️ Submenu WAJIB diawali penanda `section` sendiri. Tanpa itu, entri ini
| ditempelkan di bawah seksi terakhir yang disumbang paket lain, sehingga
| "Artikel" terbaca sebagai bagian dari pengaturan Lapor Bunda.
|
| ⚠️ `group` harus salah satu dari WorkspaceManager::GROUP_ORDER. Selain itu
| mendarat di "Lainnya" tanpa peringatan.
*/

return [
    [
        'workspace' => 'ponorogo-hub',
        'label' => 'Ponorogo Hub',
        'icon' => 'lucide-landmark',
        'group' => 'Layanan',
        'url' => '',
        'permission' => 'news.article.view',
        'submenu' => [
            [
                'section' => 'Berita',
                'icon' => 'lucide-newspaper',
                'permission' => 'news.article.view',
            ],
            [
                'label' => 'Artikel',
                'icon' => 'lucide-file-text',
                'url' => url($prefix.'/articles'),
                'permission' => 'news.article.view',
                'navigate' => true,
            ],
            [
                'label' => 'Sumber Berita',
                'icon' => 'lucide-globe',
                'url' => url($prefix.'/sources'),
                'permission' => 'news.source.view',
                'navigate' => true,
            ],
            [
                'label' => 'Pengaturan Berita',
                'icon' => 'lucide-settings',
                'url' => url($prefix.'/settings'),
                'permission' => 'news.setting.view',
                'navigate' => true,
            ],
        ],
    ],
];
