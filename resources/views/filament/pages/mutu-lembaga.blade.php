<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Agregat Mutu per Lembaga</x-slot>
        @if (count($baris) === 0)
            <p class="text-sm text-gray-500">Belum ada data lembaga/santri aktif.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-xs uppercase text-gray-500">
                            <th class="py-2 pr-2">Lembaga</th>
                            <th class="py-2 px-2 text-center">Santri Aktif</th>
                            <th class="py-2 px-2 text-center">Nilai Rata</th>
                            <th class="py-2 px-2 text-center">Kehadiran %</th>
                            <th class="py-2 px-2 text-center">Total Lampu 🟡</th>
                            <th class="py-2 px-2">Rincian Lampu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            <tr class="border-b last:border-0">
                                <td class="py-1.5 pr-2 font-medium">{{ $b['lembaga'] }}</td>
                                <td class="py-1.5 px-2 text-center">{{ $b['jumlah_santri'] }}</td>
                                <td class="py-1.5 px-2 text-center">{{ $b['nilai_rata'] ?? '—' }}</td>
                                <td class="py-1.5 px-2 text-center">{{ $b['hadir_persen'] !== null ? $b['hadir_persen'].'%' : '—' }}</td>
                                <td class="py-1.5 px-2 text-center {{ $b['total_lampu'] > 0 ? 'bg-warning-50 font-semibold text-warning-700' : 'text-success-600' }}">{{ $b['total_lampu'] }}</td>
                                <td class="py-1.5 px-2 text-xs text-gray-500">
                                    @forelse ($b['lampu'] as $jenis => $jumlah)
                                        <span class="mr-2">{{ $jenis }}: {{ $jumlah }}</span>
                                    @empty
                                        —
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
