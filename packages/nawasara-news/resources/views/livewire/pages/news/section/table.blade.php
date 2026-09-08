<div x-data="{ toast: false }" x-on:news-sync-queued.window="toast = true; setTimeout(() => toast = false, 3000)">

    <x-nawasara-ui::page-header title="Nawasara News" description="Mirror artikel berita dari website ponorogo.go.id (read-only — sumber data ada di ponorogo.go.id)."
        :count="$articles->total().' artikel'">
        @can('news.settings.view')
            <x-nawasara-ui::icon-button icon="refresh-cw" tooltip="Sync sekarang" wire:click="syncNow" loadingTarget="syncNow" />
            <x-nawasara-ui::icon-button icon="settings"
                tooltip="Pengaturan"
                x-on:click="$dispatch('open-modal', { id: 'news-settings' })"
                wire:click="openSettings" />
        @endcan
    </x-nawasara-ui::page-header>

    <div class="flex items-center gap-3 mb-4 text-xs text-gray-500 dark:text-neutral-400">
        @if ($lastSyncedAt)
            <span><x-lucide-clock class="size-3 inline" /> Sinkronisasi terakhir: {{ $lastSyncedAt }}</span>
        @else
            <span class="text-amber-700 dark:text-amber-400">Belum pernah di-sync.</span>
        @endif
        <span x-show="toast" x-cloak class="text-emerald-600 dark:text-emerald-400">
            Sinkronisasi dijadwalkan — cek kembali beberapa saat lagi.
        </span>
    </div>

    @php
        // Named variable, matching WHM's real $statusOptions pattern —
        // reused for both filter-group's :items and filter-panel's :labels
        // below, rather than the inline pluck() call this used to be.
        $categoryOptions = $categories->pluck('slug', 'id')->toArray();
    @endphp

    <div class="flex flex-col md:flex-row md:items-center gap-3 mb-4">
        <x-nawasara-ui::filter-panel
            label="Filter"
            :state="['categoryId' => $categoryId]"
            :multiple="['categoryId']"
            :labels="['categoryId' => $categoryOptions]"
            :dimensions="['categoryId' => 'Kategori']">
            <x-nawasara-ui::filter-group
                model="categoryId"
                label="Kategori"
                :items="$categoryOptions"
                icon="lucide-tag" />
        </x-nawasara-ui::filter-panel>

        <x-nawasara-ui::search-input model="search" placeholder="Cari judul..." />
    </div>

    {{-- wire:ignore matches the real reference — without it, a Livewire
         re-render could replace this div and orphan filter-panel's
         teleported chips (target found once at Alpine init, not re-checked). --}}
    <div wire:ignore data-filter-chips class="mb-3"></div>

    @if ($articles->isEmpty())
        <x-nawasara-ui::empty-state
            title="Belum ada artikel"
            description="Klik ikon sync di atas, atau tunggu sinkronisasi terjadwal berikutnya." />
    @else
        <x-nawasara-ui::table :headers="['Judul', 'Kategori', 'Tanggal Terbit', '']">
            <x-slot:table>
                @foreach ($articles as $article)
                    <tr wire:key="article-{{ $article->id }}">
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">{{ $article->judul }}</td>
                        <td class="px-6 py-4 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $article->category->slug ?? '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $article->tanggal_terbit?->setTimezone(config('nawasara-news.display_timezone', 'Asia/Jakarta'))->format('d M Y H:i') ?? '—' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <x-nawasara-ui::dropdown-menu-action :id="$article->id" :items="[
                                [
                                    'type' => 'click',
                                    'label' => 'Detail',
                                    'wire:click' => 'openDetail(' . $article->id . ')',
                                    'modal' => 'news-detail',
                                    'icon' => 'lucide-eye',
                                    'permission' => 'news.article.view',
                                ],
                                [
                                    'type' => 'link',
                                    'label' => 'Original post',
                                    'url' => $article->link,
                                    'target' => '_blank',
                                    'icon' => 'lucide-link',
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

    {{-- Detail modal — $this->detail (with $this-> prefix) is deliberate:
         it's a #[Computed] accessor on Table.php, not a plain property, so
         the full Article model never rides along in the Livewire wire
         snapshot except while this modal is actually rendering. --}}
    <x-nawasara-ui::modal id="news-detail" maxWidth="2xl" :title="$this->detail?->judul ?? 'Detail Artikel'">
        @if ($this->detail)
            @php $d = $this->detail; @endphp
            @if ($d->gambar_sampul)
                {{-- 'medium_large' for a bigger detail-view display than
                     the API's list endpoint would typically use; every key
                     is guaranteed present once gambar_sampul itself is
                     non-null (see WordpressClient::resolveCoverImages()),
                     so no further fallback chain is needed here. --}}
                <img src="{{ $d->gambar_sampul['medium_large'] }}" alt="{{ $d->judul }}"
                    class="w-full object-cover rounded-lg mb-4" />
            @endif

            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div><span class="text-gray-500 dark:text-neutral-400">Kategori:</span> <span class="font-medium text-gray-800 dark:text-neutral-200">{{ $d->category->slug ?? '—' }}</span>
                </div>
                <div><span class="text-gray-500 dark:text-neutral-400">Tanggal Terbit:</span> <span
                        class="font-medium text-gray-800 dark:text-neutral-200">{{ $d->tanggal_terbit?->setTimezone(config('nawasara-news.display_timezone', 'Asia/Jakarta'))->format('d M Y H:i') ?? '—' }}</span>
                </div>
                <div class="col-span-2"><span class="text-gray-500 dark:text-neutral-400">Slug:</span> <span
                        class="font-mono text-xs text-gray-800 dark:text-neutral-200">{{ $d->slug }}</span></div>
            </div>

            @if ($d->ringkasan)
                <p class="text-sm italic text-neutral-600 dark:text-neutral-300 mb-4">
                    {!! strip_tags($d->ringkasan) !!}
                </p>
            @endif

            {{-- Raw HTML — deliberate. isi_lengkap comes from this
                 organization's own WordPress editors (trusted internal
                 source), and this modal is only reachable by permissioned
                 Nawasara admins (news.article.view), not the public API. --}}
            <div class="docs-prose dark:text-neutral-300">
                {!! $d->isi_lengkap !!}
            </div>
        @endif

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'news-detail')">Tutup</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>

    {{-- Settings modal — folded in from the former separate /settings page.
         No skeleton-loading roundtrip needed on open (data's already fresh
         from mount()/openSettings()'s refresh), so this could arguably skip
         the wire:click entirely and just be pure Alpine open-modal — kept
         wire:click anyway so openSettings() can re-pull latest values in
         case another admin changed them since this page loaded. --}}
    @can('news.settings.view')
        <x-nawasara-ui::modal id="news-settings" maxWidth="md" title="Pengaturan Sync">
            {{-- form="news-settings-form" + type="submit" in the footer below
                 IS the correct/proven pattern (confirmed against the real,
                 working whm-email-form modal) — an earlier attempt to "fix"
                 this by switching to wire:click was chasing the wrong cause;
                 the real bug was the dispatch() event name in saveSettings(),
                 not this button/form wiring. Reverted to match. --}}
            <form wire:submit="saveSettings" id="news-settings-form" class="space-y-4">
                <div>
                    <x-nawasara-ui::form.input
                        label="Jumlah Post Terbaru yang Disinkron"
                        type="number"
                        min="1"
                        max="1000"
                        wire:model="latestPostLimit" />
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                        Artikel di luar N terbaru ini akan dihapus dari Nawasara pada sinkronisasi berikutnya (WordPress tetap menyimpan semuanya).
                    </p>
                </div>
            </form>
            <x-slot:footer>
                <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'news-settings')">Batal</x-nawasara-ui::button>
                <x-nawasara-ui::button type="submit" form="news-settings-form" color="primary">Simpan</x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    @endcan
</div>
