<x-filament-panels::page>
    @php($daftar = $this->daftarPercakapan())
    @php($pesanTerakhir = $this->pesanTerakhir($daftar))
    @php($namaWali = $this->namaWali($daftar))
    @php($dipilih = $this->percakapanTerpilih())

    {{-- Poll ringan HANYA saat ada percakapan handoff menunggu admin. --}}
    <div class="space-y-6" @if ($this->adaHandoff()) wire:poll.15s @endif>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">Percakapan WhatsApp</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Nomor ditampilkan tersamar demi privasi wali. Percakapan berstatus
                        <strong>handoff</strong> sedang menunggu layanan manusia — asisten diam.
                    </p>
                </div>
                <select
                    wire:model.live="filterStatus"
                    class="rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                >
                    <option value="">Semua status</option>
                    <option value="handoff">Handoff (menunggu admin)</option>
                    <option value="aktif">Aktif (dilayani asisten)</option>
                    <option value="ditutup">Ditutup</option>
                </select>
            </div>

            @if ($daftar->isEmpty())
                <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada percakapan pada filter ini.
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @foreach ($daftar as $percakapan)
                        @php($terakhir = $pesanTerakhir[$percakapan->id] ?? null)
                        <div class="space-y-2 p-4" wire:key="percakapan-{{ $percakapan->id }}">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($percakapan->status === 'handoff')
                                    <x-filament::badge color="warning">handoff — menunggu admin</x-filament::badge>
                                @elseif ($percakapan->status === 'aktif')
                                    <x-filament::badge color="success">aktif</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">{{ $percakapan->status }}</x-filament::badge>
                                @endif

                                <span class="font-mono text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ $this->nomorTersamar($percakapan) }}
                                </span>

                                @if ($percakapan->verifikasi_status === 'terverifikasi' && isset($namaWali[$percakapan->wali_user_id]))
                                    <span class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ $namaWali[$percakapan->wali_user_id] }}
                                    </span>
                                    <x-filament::badge color="info">wali terverifikasi</x-filament::badge>
                                @endif

                                @if ($percakapan->opt_out)
                                    <x-filament::badge color="danger">opt-out</x-filament::badge>
                                @endif

                                <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                                    Aktif terakhir {{ $percakapan->terakhir_aktif?->translatedFormat('d F Y H:i') ?? '—' }}
                                </span>
                            </div>

                            @if ($terakhir !== null)
                                <p class="truncate text-sm text-gray-600 dark:text-gray-300">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        {{ $terakhir->arah === 'masuk' ? 'Masuk' : 'Keluar' }}:
                                    </span>
                                    {{ \Illuminate\Support\Str::limit($terakhir->isi_terredaksi, 140) }}
                                </p>
                            @endif

                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-eye"
                                    wire:click="pilih({{ $percakapan->id }})">
                                    {{ $this->terpilih === $percakapan->id ? 'Tutup thread' : 'Lihat thread' }}
                                </x-filament::button>

                                @if ($percakapan->status !== 'handoff')
                                    <x-filament::button size="sm" color="warning" icon="heroicon-o-hand-raised"
                                        wire:click="ambilAlih({{ $percakapan->id }})"
                                        wire:confirm="Ambil alih percakapan ini? Asisten akan berhenti membalas sampai dikembalikan ke bot.">
                                        Ambil Alih
                                    </x-filament::button>
                                @endif

                                @if ($percakapan->status === 'handoff')
                                    <x-filament::button size="sm" color="success" icon="heroicon-o-arrow-uturn-left"
                                        wire:click="kembalikanKeBot({{ $percakapan->id }})"
                                        wire:confirm="Kembalikan percakapan ini ke asisten? Pesan berikutnya dijawab otomatis lagi.">
                                        Kembalikan ke Bot
                                    </x-filament::button>
                                @endif

                                @if ($percakapan->status !== 'ditutup')
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-check-circle"
                                        wire:click="tutup({{ $percakapan->id }})"
                                        wire:confirm="Tutup percakapan ini? Pesan baru dari nomor ini akan membukanya kembali.">
                                        Tutup
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($dipilih !== null)
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 p-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                        Thread {{ $this->nomorTersamar($dipilih) }}
                    </h2>
                    @if ($dipilih->verifikasi_status === 'terverifikasi' && isset($namaWali[$dipilih->wali_user_id]))
                        <span class="text-sm text-gray-600 dark:text-gray-300">— {{ $namaWali[$dipilih->wali_user_id] }}</span>
                    @endif
                    <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                        Riwayat terredaksi PII, maksimal 100 pesan terakhir.
                    </span>
                </div>

                <div class="max-h-96 space-y-2 overflow-y-auto p-4">
                    @forelse ($this->pesanThread() as $pesan)
                        <div @class([
                            'max-w-[85%] rounded-lg p-3 text-sm',
                            'bg-gray-100 text-gray-800 dark:bg-gray-900/60 dark:text-gray-100' => $pesan->arah === 'masuk',
                            'ms-auto bg-primary-50 text-gray-800 dark:bg-primary-900/30 dark:text-gray-100' => $pesan->arah !== 'masuk',
                        ]) wire:key="pesan-{{ $pesan->id }}">
                            <div class="mb-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-semibold uppercase tracking-wide">
                                    {{ $pesan->arah === 'masuk' ? 'Masuk' : 'Keluar' }}
                                </span>
                                @if ($pesan->arah !== 'masuk' && ($pesan->meta['jenis'] ?? null) === 'manual')
                                    <x-filament::badge color="info">balasan admin</x-filament::badge>
                                @endif
                                <span class="ms-auto">{{ $pesan->created_at?->translatedFormat('d M Y H:i') }}</span>
                            </div>
                            <p class="whitespace-pre-line">{{ $pesan->isi_terredaksi }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pesan pada percakapan ini.</p>
                    @endforelse
                </div>

                <div class="space-y-2 border-t border-gray-200 p-4 dark:border-gray-700">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300" for="balas-{{ $dipilih->id }}">
                        Balasan admin (dikirim lewat jalur WhatsApp resmi)
                    </label>
                    <textarea
                        id="balas-{{ $dipilih->id }}"
                        wire:model="balasTeks.{{ $dipilih->id }}"
                        rows="2"
                        class="w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="Tulis balasan dengan bahasa yang santun…"
                    ></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @if ($dipilih->status !== 'handoff')
                                Asisten masih aktif pada percakapan ini — gunakan <strong>Ambil Alih</strong>
                                bila ingin asisten berhenti membalas.
                            @else
                                Asisten sedang diam — wali hanya menerima balasan dari admin.
                            @endif
                        </p>
                        <x-filament::button size="sm" color="success" icon="heroicon-o-paper-airplane"
                            wire:click="kirimBalasan({{ $dipilih->id }})">
                            Kirim balasan
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
