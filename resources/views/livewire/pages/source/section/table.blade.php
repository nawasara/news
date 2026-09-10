<div>
    <x-nawasara-ui::page-header
        title="Sumber Berita"
        description="Situs WordPress yang ditarik beritanya. Menambah sumber tidak perlu menunggu pembaruan aplikasi."
        :count="$sources->count()">
        @can('news.source.create')
            <x-nawasara-ui::button color="primary"
                x-on:click="$dispatch('news-source-create')">
                <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                Tambah Sumber
            </x-nawasara-ui::button>
        @endcan
    </x-nawasara-ui::page-header>

    {{-- Toolbar — satu filter-panel untuk semua saringan, lalu pencarian. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['statusFilter' => $statusFilter]"
                    :labels="['statusFilter' => $this->statusOptions]"
                    :dimensions="['statusFilter' => 'Status']">
                    <x-nawasara-ui::filter-group label="Status" model="statusFilter"
                        :items="$this->statusOptions" icon="lucide-circle-check" />
                </x-nawasara-ui::filter-panel>
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari nama atau alamat situs..." />
        </div>

        <div wire:ignore data-filter-chips class="flex flex-wrap items-center gap-2"></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    @if ($sources->isEmpty())
        @if ($this->hasFilter())
            <x-nawasara-ui::empty-state
                icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::empty-state
                icon="lucide-globe"
                title="Belum ada sumber berita"
                description="Tambahkan situs WordPress pertama, misalnya https://ponorogo.go.id" />
        @endif
    @else
        <x-nawasara-ui::table :headers="['Nama', 'Alamat', 'Artikel', 'Batas', 'Sinkronisasi Terakhir', 'Status', '']" stickyLast>
            <x-slot:table>
                @foreach ($sources as $source)
                    <tr wire:key="source-{{ $source->id }}">
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $source->name }}
                            <div class="font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $source->slug }}</div>
                        </td>
                        <td class="px-6 py-4 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $source->base_url }}
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $source->articles_count }}
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $source->latest_post_limit }}
                        </td>
                        <td class="px-6 py-4 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $source->last_synced_at?->diffForHumans() ?? 'Belum pernah' }}
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if ($source->isFailing())
                                {{-- Kegagalan didahulukan atas aktif/nonaktif: sumber
                                     yang aktif tetapi gagal terlihat "normal" kalau
                                     hanya statusnya yang ditampilkan. --}}
                                <x-nawasara-ui::badge color="rose">Bermasalah</x-nawasara-ui::badge>
                                <div class="text-xs text-rose-600 dark:text-rose-400 mt-1 max-w-xs truncate"
                                    title="{{ $source->last_error }}">{{ $source->last_error }}</div>
                            @elseif ($source->is_active)
                                <x-nawasara-ui::badge color="emerald">Aktif</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Nonaktif</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$source->id" :items="[
                                [
                                    'type' => 'click',
                                    'label' => 'Sinkronkan sekarang',
                                    'wire:click' => 'syncOne(' . $source->id . ')',
                                    'icon' => 'lucide-refresh-cw',
                                    'permission' => 'news.source.sync',
                                ],
                                [
                                    'type' => 'click',
                                    'label' => 'Ubah',
                                    'wire:click' => '$dispatch(\'news-source-edit\', { sourceId: ' . $source->id . ' })',
                                    'icon' => 'lucide-pencil',
                                    'permission' => 'news.source.update',
                                ],
                                [
                                    'type' => 'click',
                                    'label' => $source->is_active ? 'Nonaktifkan' : 'Aktifkan',
                                    'wire:click' => 'toggleActive(' . $source->id . ')',
                                    'icon' => $source->is_active ? 'lucide-pause' : 'lucide-play',
                                    'permission' => 'news.source.update',
                                ],
                                [
                                    'type' => 'click',
                                    'label' => 'Hapus',
                                    'wire:click' => 'delete(' . $source->id . ')',
                                    'icon' => 'lucide-trash-2',
                                    'confirm' => 'Hapus sumber ' . $source->name . ' beserta ' . $source->articles_count . ' artikelnya? Tindakan ini tidak dapat dibatalkan.',
                                    'permission' => 'news.source.delete',
                                ],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>
    @endif
</div>
