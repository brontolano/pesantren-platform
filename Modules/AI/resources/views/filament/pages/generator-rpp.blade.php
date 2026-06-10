<x-filament-panels::page>
    {{-- Form-first (T-A090): formulir di dalam panel asisten, bukan chat kosong --}}
    <x-ai::panel-asisten
        judul="Generator RPP / Modul Ajar"
        deskripsi="Khadim Digital mengisi tiap seksi dokumen sesuai template milik pesantren Anda, dari materi kurikulum yang sudah diunggah — lengkap dengan sumber materi per seksi. Draf tidak berlaku sebelum Anda sahkan."
    >
        <form wire:submit="buatDraf" class="space-y-4">
            {{ $this->form }}

            {{-- Isian awal guru per seksi (opsional) — struktur ikut template terpilih --}}
            @php($seksiForm = $this->seksiTemplateTerpilih())
            @if ($seksiForm !== [])
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-200">Isian awal per seksi (opsional)</p>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                        Catatan awal Anda dikembangkan asisten saat mengisi seksi terkait. Seksi berpetunjuk "salin" diambil persis dari isian Anda tanpa diubah.
                    </p>
                    <div class="space-y-3">
                        @foreach ($seksiForm as $seksi)
                            <div wire:key="input-seksi-{{ $seksi['kunci'] ?? $loop->index }}">
                                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="input-guru-{{ $seksi['kunci'] ?? $loop->index }}">
                                    {{ $seksi['judul'] ?? ($seksi['kunci'] ?? '') }}
                                    @if (!empty($seksi['wajib']))
                                        <span class="text-danger-500" title="Seksi wajib pada template">*</span>
                                    @endif
                                </label>
                                @if (!empty($seksi['petunjuk']))
                                    <p class="mb-1 text-xs text-gray-400 dark:text-gray-500">{{ $seksi['petunjuk'] }}</p>
                                @endif
                                <textarea
                                    id="input-guru-{{ $seksi['kunci'] ?? $loop->index }}"
                                    wire:model="inputGuru.{{ $seksi['kunci'] ?? $loop->index }}"
                                    rows="2"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                ></textarea>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-sparkles">
                    Susun draf RPP
                </x-filament::button>
            </div>
        </form>
    </x-ai::panel-asisten>

    {{-- Kelola template (permission terpisah ai.template.kelola — Wakakurikulum/admin) --}}
    @if ($this->bolehKelolaTemplate())
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Template RPP</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Struktur dokumen sepenuhnya milik pesantren Anda — silakan sesuaikan seksi per jenis lembaga.
                    </p>
                </div>
                <x-filament::button size="sm" icon="heroicon-o-plus" wire:click="bukaTemplateBaru">
                    Template baru
                </x-filament::button>
            </div>

            @php($daftarTemplate = $this->daftarTemplate())
            @if ($daftarTemplate->isEmpty())
                <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada template. Silakan buat template pertama Anda.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            <th class="p-3">Nama</th>
                            <th class="p-3">Jenis lembaga</th>
                            <th class="p-3 text-right">Seksi</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($daftarTemplate as $template)
                            <tr class="border-b border-gray-100 dark:border-gray-700/50" wire:key="template-{{ $template->id }}">
                                <td class="p-3 font-medium text-gray-800 dark:text-gray-100">{{ $template->nama }}</td>
                                <td class="p-3 text-gray-600 dark:text-gray-300">{{ $template->jenis_lembaga ?? '—' }}</td>
                                <td class="p-3 text-right text-gray-600 dark:text-gray-300">{{ count((array) $template->seksi) }}</td>
                                <td class="p-3">
                                    <x-filament::badge :color="$template->aktif ? 'success' : 'gray'">
                                        {{ $template->aktif ? 'aktif' : 'nonaktif' }}
                                    </x-filament::badge>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <x-filament::button size="sm" color="gray" icon="heroicon-o-pencil"
                                            wire:click="editTemplate({{ $template->id }})">
                                            Ubah
                                        </x-filament::button>
                                        <x-filament::button size="sm" color="danger" icon="heroicon-o-trash"
                                            wire:click="hapusTemplate({{ $template->id }})"
                                            wire:confirm="Hapus template ini? Template yang sudah dipakai draf akan ditolak.">
                                            Hapus
                                        </x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- Editor template sederhana --}}
            @if ($templateForm !== null)
                <div class="space-y-3 border-t border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        {{ ($templateForm['id'] ?? null) === null ? 'Template baru' : 'Ubah template' }}
                    </h3>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="template-nama">Nama template</label>
                            <input id="template-nama" type="text" wire:model="templateForm.nama"
                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="template-jenis">Jenis lembaga (opsional, teks bebas)</label>
                            <input id="template-jenis" type="text" wire:model="templateForm.jenis_lembaga"
                                placeholder="mis. MTs / MA / Diniyah"
                                class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                <input type="checkbox" wire:model="templateForm.aktif" class="rounded border-gray-300" />
                                Aktif (bisa dipakai membuat draf)
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2">
                        @foreach ($templateForm['seksi'] ?? [] as $i => $seksi)
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="template-seksi-{{ $i }}">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Judul seksi</label>
                                        <input type="text" wire:model="templateForm.seksi.{{ $i }}.judul"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Kunci (opsional — otomatis dari judul)</label>
                                        <input type="text" wire:model="templateForm.seksi.{{ $i }}.kunci"
                                            class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Petunjuk pengisian (untuk AI/guru — petunjuk berkata "salin" berarti seksi diambil persis dari isian guru tanpa AI)</label>
                                    <textarea rows="2" wire:model="templateForm.seksi.{{ $i }}.petunjuk"
                                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"></textarea>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <label class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" wire:model="templateForm.seksi.{{ $i }}.wajib" class="rounded border-gray-300" />
                                        Seksi wajib
                                    </label>
                                    <x-filament::button size="sm" color="danger" icon="heroicon-o-x-mark"
                                        wire:click="hapusSeksiTemplate({{ $i }})">
                                        Hapus seksi
                                    </x-filament::button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap justify-between gap-2">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-plus" wire:click="tambahSeksiTemplate">
                            Tambah seksi
                        </x-filament::button>
                        <div class="flex gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="batalTemplate">
                                Batal
                            </x-filament::button>
                            <x-filament::button size="sm" icon="heroicon-o-check" wire:click="simpanTemplate">
                                Simpan template
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Daftar draf + status (poll ringan selama ada draf yang sedang diproses) --}}
    @php($daftar = $this->daftarDraf())
    @php($adaProses = $daftar->whereIn('status', ['antri', 'menyusun'])->isNotEmpty())

    <div
        class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
        @if ($adaProses) wire:poll.5s @endif
    >
        <div class="border-b border-gray-200 p-4 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Daftar Dokumen RPP</h2>
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
                Belum ada dokumen RPP. Silakan susun dokumen pertama Anda lewat formulir di atas.
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-600 dark:border-gray-700 dark:text-gray-300">
                        <th class="p-3">Judul</th>
                        <th class="p-3">Template</th>
                        <th class="p-3 text-right">Seksi</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($daftar as $draf)
                        <tr class="border-b border-gray-100 dark:border-gray-700/50" wire:key="draf-rpp-{{ $draf->id }}">
                            <td class="p-3 font-medium text-gray-800 dark:text-gray-100">
                                {{ $draf->judul }}
                                @if ($draf->kelas)
                                    <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ $draf->kelas }})</span>
                                @endif
                                @if ($draf->status === 'gagal' && $draf->error_ringkas)
                                    <p class="mt-1 text-xs font-normal text-danger-600 dark:text-danger-400">{{ $draf->error_ringkas }}</p>
                                @endif
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-300">{{ $draf->template?->nama ?? '—' }}</td>
                            <td class="p-3 text-right text-gray-600 dark:text-gray-300">{{ count((array) $draf->isi) }}</td>
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
                                            href="{{ route('ai.draf-rpp.cetak', ['draf' => $draf->id]) }}"
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

    {{-- Detail draf: editor isi per seksi + sitasi + pengesahan (human-in-the-loop) --}}
    @php($terpilih = $this->drafTerpilih())

    @if ($terpilih !== null && in_array($terpilih->status, ['draf', 'disahkan'], true))
        <x-ai::editor-draf
            judul="{{ $terpilih->judul }} — {{ count((array) $terpilih->isi) }} seksi"
            :aksi="$terpilih->status === 'draf' ? 'sahkan' : null"
            labelAksi="Sahkan dokumen RPP"
        >
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($terpilih->status === 'disahkan')
                            <x-ai::badge-ai varian="data" class="mr-2" />
                            Disahkan pada {{ $terpilih->disahkan_pada?->translatedFormat('d F Y H:i') ?? '-' }}.
                            Isi terkunci dari penyuntingan.
                        @else
                            Sunting isi tiap seksi seperlunya, lalu simpan. Sumber materi (sitasi) melekat dari pipeline dan tidak dapat diubah.
                        @endif
                    </p>
                    <div class="flex gap-2">
                        {{-- Ekspor dokumen: cetak print-friendly + unduh .doc (HTML
                             ber-mime Word). phpoffice/phpword TIDAK tersedia di
                             vendor (keputusan T-A041: tanpa dependency baru) —
                             DOCX sungguhan menyusul bila paket itu masuk. --}}
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray"
                            tag="a"
                            href="{{ route('ai.draf-rpp.unduh', ['draf' => $terpilih->id]) }}">
                            Unduh Word
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-printer"
                            tag="a"
                            href="{{ route('ai.draf-rpp.cetak', ['draf' => $terpilih->id]) }}"
                            target="_blank">
                            Cetak
                        </x-filament::button>
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-x-mark" wire:click="tutupDetail">
                            Tutup
                        </x-filament::button>
                    </div>
                </div>

                @foreach ($isiEdit as $i => $seksi)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="isi-{{ $terpilih->id }}-{{ $seksi['kunci'] ?? $i }}">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $seksi['judul'] ?? ($seksi['kunci'] ?? 'Seksi '.($i + 1)) }}
                        </p>

                        @if ($terpilih->status === 'draf')
                            <textarea
                                id="konten-{{ $i }}"
                                wire:model="isiEdit.{{ $i }}.konten"
                                rows="4"
                                aria-label="Isi seksi {{ $seksi['judul'] ?? ($i + 1) }}"
                                class="mb-2 w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            ></textarea>
                        @else
                            <p class="mb-2 whitespace-pre-line text-sm text-gray-800 dark:text-gray-100">{{ $seksi['konten'] ?? '' }}</p>
                        @endif

                        {{-- Sitasi deterministik per seksi (T-A090 blok-sitasi) --}}
                        <x-ai::blok-sitasi :sitasi="$seksi['sitasi'] ?? []" class="mt-2" />
                    </div>
                @endforeach

                @if ($terpilih->status === 'draf')
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
