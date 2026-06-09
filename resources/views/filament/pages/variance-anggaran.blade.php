<x-filament-panels::page>
    @php($t = $laporan['total'])
    <div class="space-y-6">
        {{-- Filter tahun --}}
        <div class="flex items-center gap-3">
            <label class="text-sm text-gray-500 dark:text-gray-400">Tahun Anggaran</label>
            <select wire:model.live="tahun"
                class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                @foreach ($tahunOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>
            <span class="text-xs text-gray-400">Proyeksi berbasis bulan ke-{{ $laporan['bulan_berjalan'] }}</span>
        </div>

        {{-- Ringkasan total --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Pagu</div>
                <div class="mt-1 text-2xl font-bold">Rp {{ number_format($t['pagu'], 0, ',', '.') }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Realisasi</div>
                <div class="mt-1 text-2xl font-bold">Rp {{ number_format($t['realisasi'], 0, ',', '.') }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Sisa Pagu</div>
                <div class="mt-1 text-2xl font-bold {{ $t['sisa'] < 0 ? 'text-danger-600' : 'text-success-600' }}">
                    Rp {{ number_format($t['sisa'], 0, ',', '.') }}
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">Serapan</div>
                <div class="mt-1 text-2xl font-bold">{{ number_format($t['persen'], 1, ',', '.') }}%</div>
            </x-filament::section>
        </div>

        {{-- Tabel varians per pos --}}
        <x-filament::section>
            <x-slot name="heading">Varians per Pos Anggaran ({{ $laporan['tahun'] }})</x-slot>

            @if (count($laporan['baris']) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada anggaran RKAM untuk tahun {{ $laporan['tahun'] }}.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                                <th class="py-2 pr-3">Akun</th>
                                <th class="py-2 pr-3">Divisi</th>
                                <th class="py-2 pr-3 text-right">Pagu</th>
                                <th class="py-2 pr-3 text-right">Realisasi</th>
                                <th class="py-2 pr-3 text-right">Sisa</th>
                                <th class="py-2 pr-3 text-right">%</th>
                                <th class="py-2 pr-3 text-right">Proyeksi Akhir Thn</th>
                                <th class="py-2 pr-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($laporan['baris'] as $b)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-3 font-medium">{{ $b['akun'] }}</td>
                                    <td class="py-2 pr-3 text-gray-500">{{ $b['divisi'] ?? '—' }}</td>
                                    <td class="py-2 pr-3 text-right">{{ number_format($b['pagu'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right">{{ number_format($b['realisasi'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right {{ $b['sisa'] < 0 ? 'text-danger-600' : '' }}">
                                        {{ number_format($b['sisa'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2 pr-3 text-right">{{ number_format($b['persen'], 1, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right {{ $b['forecast_lampaui'] ? 'text-danger-600 font-semibold' : 'text-gray-500' }}">
                                        {{ number_format($b['forecast'], 0, ',', '.') }}
                                    </td>
                                    <td class="py-2 pr-3">
                                        @php($warna = ['aman' => 'success', 'waspada' => 'warning', 'kritis' => 'danger', 'lampaui' => 'danger'][$b['status']] ?? 'gray')
                                        <x-filament::badge :color="$warna">{{ ucfirst($b['status']) }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
