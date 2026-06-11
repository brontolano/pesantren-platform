<x-filament-panels::page>
    <x-filament::section heading="Periode">
        <div class="max-w-xs">
            <x-filament::input.wrapper>
                <x-filament::input type="month" wire:model.live="periode" />
            </x-filament::input.wrapper>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Selisih poin dikoreksi di <strong>sumber</strong> (jurnal mengajar / presensi / khidmah manual),
                lalu klik <em>Agregasi Ulang</em> — bukan edit angka di slip.
            </p>
        </div>
    </x-filament::section>

    <x-filament::section :heading="'Rekap Khidmah ' . $periode">
        @if ($rekap->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Belum ada baris khidmah pada periode ini. Klik <em>Agregasi Ulang</em> untuk menarik data dari
                jurnal mengajar &amp; presensi staf.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left rtl:text-right">
                            <th class="px-3 py-2 font-medium">Pegawai</th>
                            <th class="px-3 py-2 font-medium">Rincian Sumber</th>
                            <th class="px-3 py-2 font-medium text-right">Baris Manual</th>
                            <th class="px-3 py-2 font-medium text-right">Total Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rekap as $r)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="px-3 py-2 font-medium">{{ $r['employee']?->nama ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($r['rincian'] as $jenis => $d)
                                            <x-filament::badge color="info">
                                                {{ $labelJenis[$jenis] ?? $jenis }}: {{ $d['jumlah'] }}× ({{ $d['poin'] }})
                                            </x-filament::badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-right">{{ $r['manual'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ $r['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
