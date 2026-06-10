<x-filament-panels::page>
    @if ($this->bolehLihatLaporan())
        @php($laporan = $this->laporanBulanIni())

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                    Laporan Pimpinan — Bulan Berjalan ({{ $laporan['periode'] }})
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Ringkasan antrean Tanya Ustadz: bukti pagar kebijakan AI bekerja.
                    Laporan bulan penuh dikirim otomatis setiap awal bulan.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-3">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total eskalasi</p>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $laporan['total'] }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sudah dijawab</p>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">{{ $laporan['jumlah_dijawab'] }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Rata-rata waktu jawab</p>
                    <p class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                        {{ $laporan['rata_rata_jawab_jam'] !== null ? number_format($laporan['rata_rata_jawab_jam'], 1, ',', '.').' jam' : '—' }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 px-4 pb-4 md:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Volume per kategori</h3>
                    @if (empty($laporan['volume_per_kategori']))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada eskalasi bulan ini.</p>
                    @else
                        <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($laporan['volume_per_kategori'] as $kategori => $jumlah)
                                <li class="flex justify-between gap-4">
                                    <span>{{ $kategori }}</span>
                                    <span class="font-medium">{{ $jumlah }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                        Contoh pertanyaan (anonim, terredaksi)
                    </h3>
                    @if (empty($laporan['contoh_anonim']))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada contoh.</p>
                    @else
                        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($laporan['contoh_anonim'] as $contoh)
                                <li>{{ \Illuminate\Support\Str::limit($contoh, 140) }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @php($daftar = $this->daftarEskalasi())
    @php($namaPengguna = $this->namaPengguna())
    @php($pilihan = $this->bolehKelola() ? $this->pilihanPenugasan() : [])

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Antrean Tanya Ustadz</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Pertanyaan yang dialihkan asisten kepada manusia. Mohon dijawab sebelum tenggat.
                </p>
            </div>
            <select
                wire:model.live="filterStatus"
                class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            >
                <option value="">Semua status</option>
                <option value="baru">Baru</option>
                <option value="ditugaskan">Ditugaskan</option>
                <option value="dijawab">Dijawab</option>
                <option value="ditutup">Ditutup</option>
            </select>
        </div>

        @if ($daftar->isEmpty())
            <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Alhamdulillah, tidak ada eskalasi pada filter ini.
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                @foreach ($daftar as $eskalasi)
                    @php($lewatTenggat = $eskalasi->status === 'ditugaskan' && $eskalasi->sla_deadline?->isPast())
                    <div class="space-y-3 p-4" wire:key="eskalasi-{{ $eskalasi->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge :color="match ($eskalasi->status) {
                                'baru' => 'warning',
                                'ditugaskan' => 'info',
                                'dijawab' => 'success',
                                default => 'gray',
                            }">
                                {{ $eskalasi->status }}
                            </x-filament::badge>
                            <x-filament::badge color="gray">{{ $eskalasi->kategori }}</x-filament::badge>
                            <x-filament::badge color="gray">kanal: {{ $eskalasi->kanal }}</x-filament::badge>
                            @if ($lewatTenggat)
                                <x-filament::badge color="danger">lewat tenggat</x-filament::badge>
                            @endif
                            <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                                Masuk {{ $eskalasi->created_at?->translatedFormat('d F Y H:i') }}
                            </span>
                        </div>

                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $eskalasi->pertanyaan_ringkas }}
                        </p>

                        <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($eskalasi->ditugaskan_ke)
                                <span>Ditugaskan ke: <strong>{{ $namaPengguna[$eskalasi->ditugaskan_ke] ?? 'Pengguna #'.$eskalasi->ditugaskan_ke }}</strong></span>
                            @endif
                            @if ($eskalasi->sla_deadline)
                                <span @class(['text-danger-600 dark:text-danger-400 font-medium' => $lewatTenggat])>
                                    Tenggat jawab: {{ $eskalasi->sla_deadline->translatedFormat('d F Y H:i') }}
                                </span>
                            @endif
                            @if ($eskalasi->dijawab_pada)
                                <span>
                                    Dijawab {{ $eskalasi->dijawab_pada->translatedFormat('d F Y H:i') }}
                                    oleh {{ $namaPengguna[$eskalasi->dijawab_oleh] ?? 'Pengguna #'.$eskalasi->dijawab_oleh }}
                                </span>
                            @endif
                        </div>

                        @if ($eskalasi->jawaban)
                            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Jawaban</span>
                                {{ $eskalasi->jawaban }}
                            </div>
                        @endif

                        @if ($this->bolehKelola() && in_array($eskalasi->status, ['baru', 'ditugaskan'], true))
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-48">
                                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300" for="tugas-{{ $eskalasi->id }}">
                                        Tugaskan kepada
                                    </label>
                                    <select
                                        id="tugas-{{ $eskalasi->id }}"
                                        wire:model="tugasKe.{{ $eskalasi->id }}"
                                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                    >
                                        <option value="">Tujuan default (Wizard)</option>
                                        @foreach ($pilihan as $userId => $nama)
                                            <option value="{{ $userId }}">{{ $nama }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <x-filament::button size="sm" color="info" icon="heroicon-o-user-plus"
                                    wire:click="tugaskan({{ $eskalasi->id }})">
                                    Tugaskan
                                </x-filament::button>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="jawab-{{ $eskalasi->id }}">
                                    Jawaban untuk penanya
                                </label>
                                <textarea
                                    id="jawab-{{ $eskalasi->id }}"
                                    wire:model="jawaban.{{ $eskalasi->id }}"
                                    rows="2"
                                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                    placeholder="Tulis jawaban dengan bahasa yang santun…"
                                ></textarea>
                                <div class="flex justify-end">
                                    <x-filament::button size="sm" color="success" icon="heroicon-o-chat-bubble-left-ellipsis"
                                        wire:click="jawab({{ $eskalasi->id }})">
                                        Kirim jawaban
                                    </x-filament::button>
                                </div>
                            </div>
                        @endif

                        @if ($this->bolehKelola() && $eskalasi->status === 'dijawab')
                            <div class="flex justify-end">
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-check-circle"
                                    wire:click="tutup({{ $eskalasi->id }})"
                                    wire:confirm="Tutup eskalasi ini? Antrean dianggap selesai ditindaklanjuti.">
                                    Tutup eskalasi
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
