<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'News', 'url' => '#'], ['label' => 'Berita']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        @livewire('nawasara-news.news.section.table')
    </x-nawasara-ui::page.container>
</div>
