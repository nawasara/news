<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Berita', 'url' => '#'], ['label' => 'Sumber Berita']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        @livewire('nawasara-news.source.section.table')
        @livewire('nawasara-news.source.section.form')
    </x-nawasara-ui::page.container>
</div>
