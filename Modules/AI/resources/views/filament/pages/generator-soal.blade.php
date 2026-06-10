<x-filament-panels::page>
    {{-- Form-first (T-A090): formulir di dalam panel asisten, bukan chat kosong --}}
    <x-ai::panel-asisten
        judul="Generator Soal & Kisi-kisi"
        deskripsi="Khadim Digital menyusun draf soal dari materi kurikulum pesantren Anda — lengkap dengan kunci, kisi-kisi, dan sumber materi per butir. Draf tidak berlaku sebelum Anda sahkan."
    >
        <form wire:submit="buatDraf" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-sparkles">
                    Susun draf soal
                </x-filament::button>
            </div>
        </form>
    </x-ai::panel-asisten>

    {{-- Daftar draf + status (poll ringan selama ada draf yang sedang diproses) --}}
    @php($daftar = $this->daftarDraf())
    @php($adaProses = $daftar->whereIn('status', ['antri', 'menyusun'])->isNotEmpty())

    <div
        class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        @if ($adaProses) wire:poll.5s @endif
    >
        <div class="border-b border-gray-200 p-4 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Daftar Paket Soal</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400" role="status" aria-live="polite">
                @if ($adaProses)
                    Sebagian paket sedang disusun di latar belakang — status diperbarui otomatis.
                @else
                    Paket berstatus <strong>draf</strong> menunggu tinjauan dan pengesahan Anda.
                @endif
            </p>
        </div>

        @if ($daftar->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada paket soal. Silakan susun paket pertama Anda lewat formulir di atas.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-600 dark:border-gray-700 dark:text-gray-300">
                        <th class="p-3">Judul</th>
                        <th class="p-3">Tipe</th>
                        <th class="p-3 text-right">Butir</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $draf)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50" wire:key="draf-{{ $draf->id }}">
                            <td class="p-3 font-medium text-gray-800 dark:text-gray-100">
                                {{ $draf->judul }}
                                @if ($draf->jenjang)
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ $draf->jenjang }})</span>
                                @endif
                                @if ($draf->status === 'gagal' && $draf->error_ringkas)
                                    <p class="mt-1 text-xs font-normal text-danger-600 dark:text-danger-400">{{ $draf->error_ringkas }}</p>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-300">{{ str_replace('_', ' ', $draf->tipe) }}</td>
                            <td class="p-3 text-right text-gray-600 dark:text-gray-300">{{ count((array) $draf->butir) }}/{{ $draf->jumlah }}</td>
                            <td class="p-3">
                                <x-filament::badge :color="match ($draf->status) {
                                    'disahkan' => 'success',
                                    'draf' => 'info',
                                    'gagal' => 'danger',
                                    'menyusun' => 'warning',
                                    default => 'gray',
                                }">
                                    {{ $draf->status === 'menyusun' ? 'sedang disusun' : $draf->status }}
                                </x-filament::badge>
                            </td>
                            <td class="p-3 text-right">
                                <div class="flex justify-end gap-2">
                                    @if (in_array($draf->status, ['draf', 'disahkan'], true))
                                        <x-filament::button size="sm" color="gray" icon="heroicon-o-eye"
                                            wire:click="pilihDraf({{ $draf->id }})">
                                            Buka
                                        </x-filament::button>
                                        <x-filament::button size="sm" color="gray" icon="heroicon-o-printer"
                                            tag="a"
                                            href="{{ route('ai.draf-soal.cetak', ['draf' => $draf->id]) }}"
                                            target="_blank">
                                            Cetak
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button size="sm" color="danger" icon="heroicon-o-trash"
                                        wire:click="hapusDraf({{ $draf->id }})"
                                        wire:confirm="Hapus paket ini? Seluruh butir draf akan dihapus permanen.">
                                        Hapus
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Detail draf: editor butir + sitasi + pengesahan (human-in-the-loop) --}}
    @php($terpilih = $this->drafTerpilih())

    @if ($terpilih !== null && in_array($terpilih->status, ['draf', 'disahkan'], true))
        <x-ai::editor-draf
            judul="{{ $terpilih->judul }} — {{ count((array) $terpilih->butir) }} butir"
            :aksi="$terpilih->status === 'draf' ? 'sahkan' : null"
            labelAksi="Sahkan paket soal"
        >
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($terpilih->status === 'disahkan')
                            <x-ai::badge-ai varian="data" class="mr-2" />
                            Disahkan pada {{ $terpilih->disahkan_pada?->translatedFormat('d F Y H:i') ?? '-' }}.
                            Butir terkunci dari penyuntingan.
                        @else
                            Sunting pertanyaan, opsi, kunci, dan kisi seperlunya, lalu simpan. Sumber materi (sitasi) melekat dari pipeline dan tidak dapat diubah.
                        @endif
                    </p>
                    <div class="flex gap-2">
                        {{-- TODO(T-A040 ekspor): tombol "Ekspor ke modul Ujian" SENGAJA
                             nonaktif — belum ada service tulis resmi bank soal di
                             Modules/PPDB (UjianOnlineService hanya mengelola attempt).
                             Aturan Emas #2: dilarang insert langsung ke tabel modul
                             lain (paket_soal/soal). Aktifkan saat service resmi
                             (mis. BankSoalService) tersedia. --}}
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-up-on-square" disabled
                            title="Menunggu integrasi modul Ujian — ekspor akan tersedia setelah service resmi bank soal dirilis.">
                            Ekspor ke Ujian
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-printer"
                            tag="a"
                            href="{{ route('ai.draf-soal.cetak', ['draf' => $terpilih->id]) }}"
                            target="_blank">
                            Cetak
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="tutupDetail">
                            Tutup
                        </x-filament::button>
                    </div>
                </div>

                @foreach ($butirEdit as $i => $butir)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="butir-{{ $terpilih->id }}-{{ $butir['no'] ?? $i }}">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Butir {{ $butir['no'] ?? ($i + 1) }}
                        </p>

                        @if ($terpilih->status === 'draf')
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="pertanyaan-{{ $i }}">Pertanyaan</label>
                            <textarea
                                id="pertanyaan-{{ $i }}"
                                wire:model="butirEdit.{{ $i }}.pertanyaan"
                                rows="2"
                                class="mb-2 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            ></textarea>

                            @if (is_array($butir['opsi'] ?? null))
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Pilihan jawaban</label>
                                <div class="mb-2 space-y-1">
                                    @foreach ($butir['opsi'] as $j => $opsi)
                                        <input
                                            type="text"
                                            wire:model="butirEdit.{{ $i }}.opsi.{{ $j }}"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                            aria-label="Pilihan {{ $j + 1 }} butir {{ $butir['no'] ?? ($i + 1) }}"
                                        />
                                    @endforeach
                                </div>
                            @endif

                            <div class="grid gap-2 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="kunci-{{ $i }}">Kunci jawaban</label>
                                    <input
                                        id="kunci-{{ $i }}"
                                        type="text"
                                        wire:model="butirEdit.{{ $i }}.kunci"
                                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="kisi-{{ $i }}">Kisi-kisi / indikator</label>
                                    <input
                                        id="kisi-{{ $i }}"
                                        type="text"
                                        wire:model="butirEdit.{{ $i }}.kisi"
                                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                    />
                                </div>
                            </div>
                        @else
                            <p class="mb-2 text-sm text-gray-800 dark:text-gray-100">{{ $butir['pertanyaan'] ?? '' }}</p>
                            @if (is_array($butir['opsi'] ?? null))
                                <ol class="mb-2 list-[upper-alpha] pl-6 text-sm text-gray-700 dark:text-gray-200">
                                    @foreach ($butir['opsi'] as $opsi)
                                        <li>{{ $opsi }}</li>
                                    @endforeach
                                </ol>
                            @endif
                            <p class="mb-1 text-xs text-gray-600 dark:text-gray-300"><strong>Kunci:</strong> {{ $butir['kunci'] ?? '-' }}</p>
                            <p class="mb-2 text-xs text-gray-600 dark:text-gray-300"><strong>Kisi:</strong> {{ $butir['kisi'] ?? '-' }}</p>
                        @endif

                        {{-- Sitasi deterministik per butir (T-A090 blok-sitasi) --}}
                        <x-ai::blok-sitasi :sitasi="$butir['sitasi'] ?? []" class="mt-2" />
                    </div>
                @endforeach

                @if ($terpilih->status === 'draf')
                    <div class="flex justify-end">
                        <x-filament::button color="gray" icon="heroicon-o-check" wire:click="simpanButir">
                            Simpan perubahan butir
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-ai::editor-draf>
    @endif
</x-filament-panels::page>
