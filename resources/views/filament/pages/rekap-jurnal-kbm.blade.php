<x-filament-panels::page>
    <div class="max-w-xs">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="bulan">Bulan</label>
        <input id="bulan" type="month" wire:model.live="bulan"
            class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
    </div>

    @if(count($this->rekap) > 0)
        <div class="mt-6 fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Guru</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Terlaksana</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Kosong</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Diganti</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Total Sesi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach($this->rekap as $row)
                        <tr>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $row['guru'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-success-600">{{ $row['terlaksana'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-danger-600">{{ $row['kosong'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-warning-600">{{ $row['diganti'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">Belum ada jurnal KBM pada bulan ini.</p>
    @endif
</x-filament-panels::page>
