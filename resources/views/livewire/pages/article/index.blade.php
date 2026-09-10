<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Berita', 'url' => '#'], ['label' => 'Artikel']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        @livewire('nawasara-news.article.section.table')
    </x-nawasara-ui::page.container>
</div>
