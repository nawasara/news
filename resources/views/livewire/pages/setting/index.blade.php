<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Berita', 'url' => '#'], ['label' => 'Pengaturan']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        @livewire('nawasara-news.setting.section.form')
    </x-nawasara-ui::page.container>
</div>
