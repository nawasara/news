<div>
    <x-nawasara-ui::page-header
        title="Artikel Berita"
        description="Cermin artikel dari situs-situs WordPress yang terdaftar. Hanya-baca — penyuntingan dilakukan di situs sumbernya."
        :count="$totalArticles">
        @can('news.source.sync')
            <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sinkronkan semua sumber"
                wire:click="syncNow" loadingTarget="syncNow" />
        @endcan
    </x-nawasara-ui::page-header>

    <x-nawasara-ui::sync-info-bar :lastSyncedAt="$this->lastSyncedAt" />

    {{-- Toolbar — satu filter-panel untuk semua saringan, lalu pencarian. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['sourceId' => $sourceId, 'categoryId' => $categoryId, 'sort' => $sort]"
                    :multiple="['sourceId', 'categoryId']"
                    :labels="['sourceId' => $this->sourceOptions, 'categoryId' => $this->categoryOptions, 'sort' => $this->sortOptions]"
                    :dimensions="['sourceId' => 'Sumber', 'categoryId' => 'Kategori', 'sort' => 'Urutkan']">
                    <x-nawasara-ui::filter-group label="Sumber" model="sourceId"
                        :items="$this->sourceOptions" icon="lucide-globe" />
                    <x-nawasara-ui::filter-group label="Kategori" model="categoryId"
                        :items="$this->categoryOptions" icon="lucide-folder" />
                    <x-nawasara-ui::filter-group label="Urutkan" model="sort"
                        :items="$this->sortOptions" icon="lucide-arrow-up-down" />
                </x-nawasara-ui::filter-panel>
            </div>

            <x-nawasara-ui::search-input model="search" placeholder="Cari judul atau kategori..." />
        </div>

        {{-- filter-panel meneleportasikan chip ke sini. wire:ignore WAJIB:
             tanpanya morph Livewire membuang chip yang disisipkan Alpine,
             karena chip itu tidak ada di keluaran server. --}}
        <div wire:ignore data-filter-chips class="flex flex-wrap items-center gap-2"></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    @if ($articles->isEmpty())
        @if ($this->hasFilter())
            <x-nawasara-ui::empty-state
                icon="lucide-search-x"
                title="Tidak ada yang cocok"
                description="Ubah kata kunci atau saringannya." />
        @else
            <x-nawasara-ui::empty-state
                icon="lucide-newspaper"
                title="Belum ada artikel"
                description="Tambahkan sumber berita lebih dulu, lalu jalankan sinkronisasi." />
        @endif
    @else
        <x-nawasara-ui::table :headers="['Judul', 'Sumber', 'Kategori', 'Terbit', '']" stickyLast>
            <x-slot:table>
                @foreach ($articles as $article)
                    <tr wire:key="article-{{ $article->id }}">
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $article->title }}
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $article->source?->name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $article->category?->display_name ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $article->published_at?->setTimezone(config('nawasara-news.display_timezone', 'Asia/Jakarta'))->translatedFormat('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$article->id" :items="[
                                [
                                    'type' => 'click',
                                    'label' => 'Detail',
                                    'wire:click' => 'openDetail(' . $article->id . ')',
                                    'modal' => 'news-article-detail',
                                    'icon' => 'lucide-eye',
                                    'permission' => 'news.article.view',
                                ],
                                [
                                    'type' => 'link',
                                    'label' => 'Buka di situs asal',
                                    'url' => $article->link,
                                    'target' => '_blank',
                                    'icon' => 'lucide-external-link',
                                    'permission' => 'news.article.view',
                                ],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>

            <x-slot:footer>
                {{ $articles->links() }}
            </x-slot:footer>
        </x-nawasara-ui::table>
    @endif

    {{-- Modal detail. $this->detail memakai awalan $this-> dengan sengaja:
         ia #[Computed], bukan properti biasa, sehingga artikel utuh —
         termasuk `content` yang panjang — tidak ikut dalam snapshot Livewire
         kecuali saat modal ini benar-benar dirender. --}}
    <x-nawasara-ui::modal id="news-article-detail" maxWidth="2xl"
        :title="$this->detail?->title ?? 'Detail Artikel'">
        @if ($this->detail)
            @php $artikel = $this->detail; @endphp

            @if ($artikel->cover_image)
                {{-- 'medium_large' untuk tampilan detail. Setiap kunci ukuran
                     dijamin ada selama cover_image tidak null — lihat
                     WordpressClient::resolveCoverImages() — jadi tidak perlu
                     rantai cadangan di sini. --}}
                <img src="{{ $artikel->cover_image['medium_large'] }}" alt="{{ $artikel->title }}"
                    class="w-full object-cover rounded-lg mb-4" />
            @endif

            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div>
                    <span class="text-gray-500 dark:text-neutral-400">Sumber:</span>
                    <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $artikel->source?->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-neutral-400">Kategori:</span>
                    <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $artikel->category?->display_name ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-neutral-400">Terbit:</span>
                    <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $artikel->published_at?->setTimezone(config('nawasara-news.display_timezone', 'Asia/Jakarta'))->translatedFormat('d F Y H:i') ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-neutral-400">Slug:</span>
                    <span class="font-mono text-xs text-gray-800 dark:text-neutral-200">{{ $artikel->slug }}</span>
                </div>
            </div>

            @if ($artikel->excerpt)
                <p class="text-sm italic text-neutral-600 dark:text-neutral-300 mb-4">
                    {!! strip_tags($artikel->excerpt) !!}
                </p>
            @endif

            {{-- HTML mentah — disengaja. `content` berasal dari penyunting
                 WordPress milik organisasi ini sendiri, dan modal ini hanya
                 dapat dibuka admin ber-permission news.article.view. --}}
            <div class="docs-prose dark:text-neutral-300">
                {!! $artikel->content !!}
            </div>
        @endif

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline"
                @click="$dispatch('close-modal', 'news-article-detail')">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
