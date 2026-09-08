<?php

// ⚠️ BEFORE MERGING: run `grep -rH "workspace" packages/*/config/menu.php`
// in the monorepo and confirm 'news' isn't already used by another package
// (guide section 5). Change the 'workspace' key below if it collides.

$prefix = 'nawasara-news';

return [
    [
        'workspace' => 'news',
        'label' => 'News',
        'icon' => 'lucide-newspaper',
        'url' => '',
        'permission' => 'news.article.view',
        'submenu' => [
            [
                'label' => 'Articles',
                'icon' => 'lucide-file-text',
                'url' => url($prefix.'/articles'),
                'permission' => 'news.article.view',
                'navigate' => true,
            ],
        ],
    ],
];
