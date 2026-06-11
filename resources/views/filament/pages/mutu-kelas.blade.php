<x-filament-panels::page>
    <x-filament::section>
        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Pilih Kelas</label>
        <select wire:model.live="kelasId"
                class="fi-input mt-1 block w-full max-w-md rounded-lg border-gray-300 text-sm shadow-sm dark:bg-gray-800 dark:border-gray-600">
            <option value="">— pilih kelas —</option>
            @foreach ($kelasList as $k)
                <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
            @endforeach
        </select>
    </x-filament::section>

    @php
        // Heatmap sederhana: warna sel via kelas blade (hijau baik → merah perlu perhatian).
        $warnaNilai = fn ($v) => $v === null ? 'text-gray-400'
            : ($v >= 80 ? 'bg-success-50 text-success-700' : ($v >= 60 ? 'bg-warning-50 text-warning-700' : 'bg-danger-50 text-danger-700'));
        $warnaHadir = fn ($v) => $v === null ? 'text-gray-400'
            : ($v >= 90 ? 'bg-success-50 text-success-700' : ($v >= 75 ? 'bg-warning-50 text-warning-700' : 'bg-danger-50 text-danger-700'));
        $warnaPoin = fn ($v) => $v <= 0 ? 'bg-success-50 text-success-700'
            : ($v < 25 ? 'bg-warning-50 text-warning-700' : 'bg-danger-50 text-danger-700');
        $warnaSetoran = fn ($v) => $v >= 4 ? 'bg-success-50 text-success-700'
            : ($v >= 1 ? 'bg-warning-50 text-warning-700' : 'bg-danger-50 text-danger-700');
    @endphp

    @if ($kelasId)
        <x-filament::section>
            <x-slot name="heading">Heatmap Mutu Santri</x-slot>
            @if (count($baris) === 0)
                <p class="text-sm text-gray-500">Tidak ada santri aktif di kelas ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-2">Santri</th>
                                <th class="py-2 px-2 text-center">Nilai Rata</th>
                                <th class="py-2 px-2 text-center">Hadir %</th>
                                <th class="py-2 px-2 text-center">Alpa</th>
                                <th class="py-2 px-2 text-center">Setoran 8 Pekan</th>
                                <th class="py-2 px-2 text-center">Poin Disiplin</th>
                                <th class="py-2 px-2">Lampu</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($baris as $b)
                                <tr class="border-b last:border-0">
                                    <td class="py-1.5 pr-2 font-medium">{{ $b['nama'] }} <span class="text-xs text-gray-400">{{ $b['nis'] }}</span></td>
                                    <td class="py-1.5 px-2 text-center {{ $warnaNilai($b['nilai_rata']) }}">{{ $b['nilai_rata'] ?? '—' }}</td>
                                    <td class="py-1.5 px-2 text-center {{ $warnaHadir($b['hadir_persen']) }}">{{ $b['hadir_persen'] !== null ? $b['hadir_persen'].'%' : '—' }}</td>
                                    <td class="py-1.5 px-2 text-center {{ $b['alpa'] > 0 ? 'bg-danger-50 text-danger-700' : '' }}">{{ $b['alpa'] }}</td>
                                    <td class="py-1.5 px-2 text-center {{ $warnaSetoran($b['setoran_8pekan']) }}">{{ $b['setoran_8pekan'] }}</td>
                                    <td class="py-1.5 px-2 text-center {{ $warnaPoin($b['poin_disiplin']) }}">{{ $b['poin_disiplin'] }}</td>
                                    <td class="py-1.5 px-2">
                                        @foreach ($b['lampu'] as $l)
                                            <span title="{{ $l['label'] }}">🟡</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-400">Lampu 🟡 dihitung job harian (mutu:hitung-lampu, 05:00). Warna sel: hijau baik, kuning waspada, merah perlu perhatian.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
