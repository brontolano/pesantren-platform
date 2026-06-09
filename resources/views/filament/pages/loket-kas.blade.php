<x-filament-panels::page>
    @php($sesi = $this->currentSesi())

    @if ($sesi)
        {{-- Ringkasan sesi aktif --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Saldo awal (modal)</div>
                <div class="mt-1 text-2xl font-bold tracking-tight">
                    Rp {{ number_format((float) $sesi->saldo_awal, 0, ',', '.') }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Saldo menurut sistem</div>
                <div class="mt-1 text-2xl font-bold tracking-tight text-primary-600 dark:text-primary-400">
                    Rp {{ number_format((float) $sesi->saldo_sistem, 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-gray-400">
                    {{ ucwords(str_replace('_', ' ', $sesi->pos_jenis)) }} ·
                    dibuka {{ optional($sesi->dibuka_pada)->translatedFormat('d M Y H:i') }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Jumlah transaksi</div>
                <div class="mt-1 text-2xl font-bold tracking-tight">
                    {{ $sesi->transaksi()->count() }}
                </div>
            </x-filament::section>
        </div>

        {{-- Form catat transaksi --}}
        <form wire:submit="catat" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button type="submit" icon="heroicon-o-plus-circle">
                    Catat Transaksi
                </x-filament::button>
            </div>
        </form>

        {{-- Transaksi terakhir --}}
        <x-filament::section heading="Transaksi terakhir sesi ini">
            @php($items = $this->transaksiTerbaru())
            @if ($items->isEmpty())
                <div class="py-4 text-sm text-gray-500 dark:text-gray-400">Belum ada transaksi pada sesi ini.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 font-medium">Waktu</th>
                                <th class="py-2 pr-4 font-medium">Kategori</th>
                                <th class="py-2 pr-4 font-medium">Jenis</th>
                                <th class="py-2 pr-4 text-right font-medium">Nominal</th>
                                <th class="py-2 font-medium">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($items as $tx)
                                <tr>
                                    <td class="py-2 pr-4 whitespace-nowrap text-gray-500">
                                        {{ optional($tx->created_at)->format('H:i') }}
                                    </td>
                                    <td class="py-2 pr-4">{{ $tx->kategori?->nama ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($tx->jenis === 'pemasukan')
                                            <x-filament::badge color="success">Masuk</x-filament::badge>
                                        @else
                                            <x-filament::badge color="danger">Keluar</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-right font-medium whitespace-nowrap">
                                        Rp {{ number_format((float) $tx->nominal, 0, ',', '.') }}
                                    </td>
                                    <td class="py-2 text-gray-500">{{ $tx->keterangan }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @else
        {{-- Belum ada sesi --}}
        <x-filament::section>
            <div class="flex flex-col items-center gap-3 py-10 text-center">
                <x-filament::icon icon="heroicon-o-lock-closed" class="h-10 w-10 text-gray-400" />
                <div class="text-base font-medium">Belum ada sesi kas yang terbuka</div>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Klik tombol <span class="font-semibold">Buka Sesi Kas</span> di kanan atas, isi modal laci awal,
                    lalu mulai mencatat pemasukan/pengeluaran tunai loket.
                </p>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
