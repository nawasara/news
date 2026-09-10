<?php

$prefix = 'nawasara-news';

return [
    [
        'workspace' => 'news',
        'label' => 'Berita',
        'icon' => 'lucide-newspaper',
        'url' => '',
        'permission' => 'news.article.view',
        'submenu' => [
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
        ],
    ],
];
