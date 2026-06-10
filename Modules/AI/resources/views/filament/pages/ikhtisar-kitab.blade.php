<x-filament-panels::page>
    {{-- Form-first (T-A090): formulir di dalam panel asisten, bukan chat kosong --}}
    <x-ai::panel-asisten
        judul="Ikhtisar Materi Kitab"
        deskripsi="Khadim Digital merangkum materi kitab pilihan Anda menjadi poin pembelajaran, istilah kunci, dan pertanyaan diskusi — lengkap dengan sumber rujukan. Draf tidak berlaku sebelum Anda sahkan."
    >
        <form wire:submit="buatDraf" class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-sparkles">
                    Susun draf
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
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Daftar Dokumen</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400" role="status" aria-live="polite">
                @if ($adaProses)
                    Sebagian dokumen sedang disusun di latar belakang — status diperbarui otomatis.
                @else
                    Dokumen berstatus <strong>draf</strong> menunggu tinjauan dan pengesahan Anda.
                @endif
            </p>
        </div>

        @if ($daftar->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada dokumen. Silakan susun dokumen pertama Anda lewat formulir di atas.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-600 dark:border-gray-700 dark:text-gray-300">
                        <th class="p-3">Judul</th>
                        <th class="p-3">Jenis</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $draf)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50" wire:key="draf-ikhtisar-{{ $draf->id }}">
                            <td class="p-3 font-medium text-gray-800 dark:text-gray-100">
                                {{ $draf->judul }}
                                @if ($draf->status === 'gagal' && $draf->error_ringkas)
                                    <p class="mt-1 text-xs font-normal text-danger-600 dark:text-danger-400">{{ $draf->error_ringkas }}</p>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-300">{{ $draf->jenis }}</td>
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
                                            href="{{ route('ai.draf-ikhtisar.cetak', ['draf' => $draf->id]) }}"
                                            target="_blank">
                                            Cetak
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button size="sm" color="danger" icon="heroicon-o-trash"
                                        wire:click="hapusDraf({{ $draf->id }})"
                                        wire:confirm="Hapus dokumen ini? Seluruh isi draf akan dihapus permanen.">
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

    {{-- Detail draf: editor isi + sitasi terkunci + pengesahan (human-in-the-loop) --}}
    @php($terpilih = $this->drafTerpilih())

    @if ($terpilih !== null && in_array($terpilih->status, ['draf', 'disahkan'], true))
        @php($bolehEdit = $terpilih->status === 'draf')
        <x-ai::editor-draf
            judul="{{ $terpilih->judul }}"
            :aksi="$bolehEdit ? 'sahkan' : null"
            labelAksi="Sahkan dokumen"
        >
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($terpilih->status === 'disahkan')
                            <x-ai::badge-ai varian="data" class="mr-2" />
                            Disahkan pada {{ $terpilih->disahkan_pada?->translatedFormat('d F Y H:i') ?? '-' }}.
                            Isi terkunci dari penyuntingan.
                        @else
                            Sunting isi seperlunya, lalu simpan. Sumber rujukan (sitasi) melekat dari pipeline dan tidak dapat diubah.
                        @endif
                    </p>
                    <div class="flex gap-2">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-printer"
                            tag="a"
                            href="{{ route('ai.draf-ikhtisar.cetak', ['draf' => $terpilih->id]) }}"
                            target="_blank">
                            Cetak handout
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="tutupDetail">
                            Tutup
                        </x-filament::button>
                    </div>
                </div>

                @if ($terpilih->jenis === 'ikhtisar')
                    {{-- Poin pembelajaran — tiap poin tertaut sitasi_idx (kolom sitasi draf) --}}
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Poin Pembelajaran</p>
                        <div class="space-y-2">
                            @foreach ((array) ($isiEdit['poin'] ?? []) as $i => $poin)
                                <div wire:key="poin-{{ $terpilih->id }}-{{ $i }}">
                                    @if ($bolehEdit)
                                        <textarea
                                            wire:model="isiEdit.poin.{{ $i }}.teks"
                                            rows="2"
                                            aria-label="Poin {{ $i + 1 }}"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                        ></textarea>
                                    @else
                                        <p class="text-sm text-gray-800 dark:text-gray-100">{{ $poin['teks'] ?? '' }}</p>
                                    @endif
                                    @php($idxSumber = collect($poin['sitasi_idx'] ?? [])->map(fn ($x) => '['.((int) $x + 1).']'))
                                    @if ($idxSumber->isNotEmpty())
                                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Rujukan sumber: {{ $idxSumber->implode(' ') }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Istilah kunci --}}
                    @if (! empty($isiEdit['istilah']))
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Istilah Kunci</p>
                            <div class="space-y-2">
                                @foreach ((array) $isiEdit['istilah'] as $i => $istilah)
                                    <div class="grid gap-2 sm:grid-cols-2" wire:key="istilah-{{ $terpilih->id }}-{{ $i }}">
                                        @if ($bolehEdit)
                                            <input type="text" wire:model="isiEdit.istilah.{{ $i }}.istilah"
                                                aria-label="Istilah {{ $i + 1 }}"
                                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                            <input type="text" wire:model="isiEdit.istilah.{{ $i }}.arti"
                                                aria-label="Arti istilah {{ $i + 1 }}"
                                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                        @else
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $istilah['istilah'] ?? '' }}</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $istilah['arti'] ?? '' }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Pertanyaan diskusi --}}
                    @if (! empty($isiEdit['pertanyaan_diskusi']))
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pertanyaan Diskusi</p>
                            <div class="space-y-2">
                                @foreach ((array) $isiEdit['pertanyaan_diskusi'] as $i => $tanya)
                                    <div wire:key="tanya-{{ $terpilih->id }}-{{ $i }}">
                                        @if ($bolehEdit)
                                            <textarea wire:model="isiEdit.pertanyaan_diskusi.{{ $i }}" rows="2"
                                                aria-label="Pertanyaan diskusi {{ $i + 1 }}"
                                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                                        @else
                                            <p class="text-sm text-gray-800 dark:text-gray-100">{{ is_array($tanya) ? ($tanya['teks'] ?? '') : $tanya }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    {{-- Kerangka kultum --}}
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kerangka</p>
                        <div class="space-y-2">
                            @foreach ((array) ($isiEdit['kerangka'] ?? []) as $i => $bagian)
                                <div wire:key="kerangka-{{ $terpilih->id }}-{{ $i }}">
                                    @if ($bolehEdit)
                                        <textarea wire:model="isiEdit.kerangka.{{ $i }}" rows="2"
                                            aria-label="Bagian kerangka {{ $i + 1 }}"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                                    @else
                                        <p class="text-sm text-gray-800 dark:text-gray-100">{{ is_array($bagian) ? ($bagian['teks'] ?? '') : $bagian }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Blok PERIKSA DALIL — menonjol; rujukan justru DIISI ustadz --}}
                    @if (! empty($isiEdit['pengingat_dalil']))
                        <div class="rounded-lg border-2 border-amber-400 bg-amber-50 p-3 dark:border-amber-500 dark:bg-amber-950/30">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-400">PERIKSA DALIL — wajib dilengkapi Ustadz</p>
                            <div class="space-y-3">
                                @foreach ((array) $isiEdit['pengingat_dalil'] as $i => $pengingat)
                                    <div wire:key="dalil-{{ $terpilih->id }}-{{ $i }}">
                                        @if ($bolehEdit)
                                            <textarea wire:model="isiEdit.pengingat_dalil.{{ $i }}.klaim" rows="2"
                                                aria-label="Klaim {{ $i + 1 }}"
                                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                                            <input type="text" wire:model="isiEdit.pengingat_dalil.{{ $i }}.rujukan"
                                                aria-label="Rujukan dalil {{ $i + 1 }}"
                                                placeholder="Rujukan dalil — diisi Ustadz"
                                                class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                        @else
                                            <p class="text-sm text-gray-800 dark:text-gray-100">{{ $pengingat['klaim'] ?? '' }}</p>
                                            <p class="text-xs text-gray-600 dark:text-gray-300">Rujukan: {{ ($pengingat['rujukan'] ?? '') !== '' ? $pengingat['rujukan'] : '(belum diisi)' }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Disclaimer wajib (PRD §7.5 + teks prosedural) — terkunci --}}
                    @if (! empty($isiEdit['disclaimer']))
                        <div class="rounded-lg border border-gray-300 bg-gray-50 p-3 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">
                            <p class="whitespace-pre-line font-medium">{{ $isiEdit['disclaimer'] }}</p>
                        </div>
                    @endif
                @endif

                {{-- Sumber rujukan deterministik pipeline (T-A090 blok-sitasi) --}}
                <x-ai::blok-sitasi :sitasi="(array) $terpilih->sitasi" class="mt-2" />

                @if ($bolehEdit)
                    <div class="flex justify-end">
                        <x-filament::button color="gray" icon="heroicon-o-check" wire:click="simpanIsi">
                            Simpan perubahan isi
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-ai::editor-draf>
    @endif
</x-filament-panels::page>
