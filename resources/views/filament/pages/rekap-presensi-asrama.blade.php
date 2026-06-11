<x-filament-panels::page>
    <form wire:submit="lihatRekap">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Lihat Rekap
            </x-filament::button>
        </div>
    </form>

    @if(count($this->rekap) > 0)
        <div class="mt-6">
            <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Kamar</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-center text-sm font-semibold text-green-600">Hadir</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-center text-sm font-semibold text-red-600">Alpha</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-center text-sm font-semibold text-yellow-600">Izin</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-center text-sm font-semibold text-blue-600">Sakit</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-center text-sm font-semibold text-yellow-600">Terlambat</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-center text-sm font-semibold text-gray-950 dark:text-white">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach($this->rekap as $row)
                            <tr>
                                <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $row['kamar'] }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-center text-sm text-green-600 font-medium">{{ $row['hadir'] }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-center text-sm text-red-600 font-medium">{{ $row['alpha'] }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-center text-sm text-yellow-600 font-medium">{{ $row['izin'] }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-center text-sm text-blue-600 font-medium">{{ $row['sakit'] }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-center text-sm text-yellow-600 font-medium">{{ $row['terlambat'] }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-center text-sm font-bold text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif($this->rekap !== null && count($this->rekap) === 0)
        <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
            Klik "Lihat Rekap" untuk menampilkan data.
        </div>
    @endif
</x-filament-panels::page>
