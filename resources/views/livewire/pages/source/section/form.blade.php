<div>
    <x-nawasara-ui::modal id="news-source-form" maxWidth="lg"
        :title="$sourceId ? 'Ubah Sumber Berita' : 'Tambah Sumber Berita'">
        {{-- Tombol simpan berada di footer, yang dirender DI LUAR div konten
             modal — sehingga ia lolos dari <form> ini dan wire:submit tidak
             pernah menyala. Karena itu tombolnya memakai form="..." + submit,
             mengikat balik ke form berdasarkan id. --}}
        <form wire:submit="save" id="news-source-form-el" class="space-y-4">
            <div>
                <x-nawasara-ui::form.input
                    label="Nama"
                    wire:model="name"
                    placeholder="Pemerintah Kabupaten Ponorogo" />
            </div>

            <div>
                <x-nawasara-ui::form.input
                    label="Slug"
                    wire:model="slug"
                    placeholder="ponorogo" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Dipakai di URL API publik: <span class="font-mono">?source=ponorogo</span>.
                    Huruf kecil, angka, dan tanda hubung.
                </p>
            </div>

            <div>
                <x-nawasara-ui::form.input
                    label="Alamat Situs"
                    wire:model="baseUrl"
                    placeholder="https://ponorogo.go.id" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Alamat pangkal situs WordPress, tanpa <span class="font-mono">/wp-json</span>.
                </p>

                <div class="mt-2 flex items-center gap-2">
                    <x-nawasara-ui::button type="button" color="neutral" variant="outline"
                        wire:click="testConnection" loadingTarget="testConnection">
                        <x-lucide-plug class="size-4" />
                        Uji Koneksi
                    </x-nawasara-ui::button>

                    @if ($testResult)
                        <span @class([
                            'text-xs',
                            'text-emerald-600 dark:text-emerald-400' => $testOk,
                            'text-rose-600 dark:text-rose-400' => ! $testOk,
                        ])>{{ $testResult }}</span>
                    @endif
                </div>
            </div>

            <div>
                <x-nawasara-ui::form.input
                    label="Jumlah Artikel Terbaru"
                    type="number"
                    min="1"
                    max="1000"
                    wire:model="latestPostLimit" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Banyaknya artikel terbaru yang ditarik tiap sinkronisasi. Artikel yang
                    sudah tersimpan tidak dihapus meski bergeser keluar dari jumlah ini.
                </p>
            </div>

            <div>
                <x-nawasara-ui::form.checkbox wire:model="isActive" label="Aktif" />
                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                    Sumber nonaktif berhenti ditarik, tetapi artikelnya tetap tersimpan.
                </p>
            </div>
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline"
                @click="$dispatch('close-modal', 'news-source-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="news-source-form-el" color="primary">
                Simpan
            </x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
