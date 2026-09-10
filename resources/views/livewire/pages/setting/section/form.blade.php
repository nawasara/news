<div>
    <x-nawasara-ui::page-header
        title="Pengaturan Berita"
        description="Setelan sinkronisasi dan API publik. Daftar situs sumber ada di halaman Sumber Berita.">
        @can('news.setting.update')
            <x-nawasara-ui::button color="neutral" variant="outline"
                wire:click="resetToDefault"
                wire:confirm="Kembalikan semua setelan ke nilai bawaan?">
                <x-slot:icon><x-lucide-rotate-ccw class="size-4" /></x-slot:icon>
                Kembalikan Bawaan
            </x-nawasara-ui::button>
        @endcan
    </x-nawasara-ui::page-header>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <x-nawasara-ui::stat-card compact icon="lucide-globe" label="Sumber Aktif"
            :value="$jumlahSumberAktif . ' / ' . $jumlahSumber" />
        <x-nawasara-ui::stat-card compact icon="lucide-newspaper" label="Artikel Tersimpan"
            :value="$jumlahArtikel" />
        <x-nawasara-ui::stat-card compact icon="lucide-timer" label="Sinkronisasi"
            :value="$schedulerEnabled ? 'Tiap ' . $syncInterval . ' menit' : 'Nonaktif'" />
    </div>

    <form wire:submit="save">
        <x-nawasara-ui::page.card>
            <div class="space-y-6">

                {{-- ── API publik ────────────────────────────────────────── --}}
                <div>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-1">
                        API Publik
                    </h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
                        Dibaca aplikasi SuperApps dan klien lain, tanpa login.
                    </p>

                    <div class="max-w-sm">
                        <x-nawasara-ui::form.input
                            label="Batas Permintaan per Menit"
                            type="number"
                            min="10"
                            max="10000"
                            wire:model="rateLimitPerMinute" />
                    </div>

                    {{-- Penjelasan ini yang membuat angkanya bisa disetel dengan
                         benar. Tanpa tahu kuota dihitung per IP, 60 terlihat
                         masuk akal — padahal itu yang memutus aplikasi warga. --}}
                    <div class="mt-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 p-3">
                        <p class="text-xs text-amber-800 dark:text-amber-300">
                            <x-lucide-info class="size-3.5 inline -mt-0.5" />
                            Kuota dihitung <strong>per alamat IP</strong>, bukan per pengguna.
                            Warga yang memakai operator seluler yang sama keluar lewat alamat
                            yang sama, sehingga mereka berbagi jatah ini.
                        </p>
                        <p class="text-xs text-amber-800 dark:text-amber-300 mt-1.5">
                            Naikkan bila ada laporan <em>berita gagal dimuat</em> yang datang
                            berkelompok dari daerah yang sama — itu tanda batas ini tercapai,
                            bukan gangguan jaringan.
                        </p>
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-neutral-700">

                {{-- ── Sinkronisasi ──────────────────────────────────────── --}}
                <div>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-1">
                        Sinkronisasi
                    </h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
                        Penarikan otomatis dari situs-situs sumber.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <x-nawasara-ui::form.checkbox wire:model.live="schedulerEnabled"
                                label="Jalankan sinkronisasi terjadwal" />
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                Bila dimatikan, artikel hanya bertambah lewat tombol
                                sinkronisasi manual. Artikel yang sudah tersimpan tetap tampil.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
                            <div>
                                <x-nawasara-ui::form.input
                                    label="Jeda Sinkronisasi (menit)"
                                    type="number"
                                    min="5"
                                    max="1440"
                                    wire:model="syncInterval" />
                                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                    Berita daerah jarang terbit tiap menit; 60 menit sudah memadai.
                                </p>
                            </div>

                            <div>
                                <x-nawasara-ui::form.input
                                    label="Batas Waktu Situs Sumber (detik)"
                                    type="number"
                                    min="5"
                                    max="120"
                                    wire:model="httpTimeout" />
                                <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1">
                                    Situs yang lambat akan ditandai bermasalah bila melewati ini.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-neutral-700">

                {{-- ── Tampilan ──────────────────────────────────────────── --}}
                <div>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-1">
                        Tampilan
                    </h3>

                    <div class="max-w-sm mt-3">
                        <x-nawasara-ui::form.select
                            label="Zona Waktu Tanggal Terbit"
                            wire:model="displayTimezone"
                            :options="$this->timezoneOptions" />
                    </div>
                </div>

            </div>

            <x-slot:footer>
                <div class="flex justify-end">
                    @can('news.setting.update')
                        <x-nawasara-ui::button type="submit" color="primary">
                            Simpan Pengaturan
                        </x-nawasara-ui::button>
                    @endcan
                </div>
            </x-slot:footer>
        </x-nawasara-ui::page.card>
    </form>
</div>
