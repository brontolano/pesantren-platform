<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="tanggal">Tanggal</label>
            <input id="tanggal" type="date" wire:model.live="tanggal"
                class="fi-input mt-1 block rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200" for="arah">Arah</label>
            <select id="arah" wire:model.live="arah"
                class="fi-input mt-1 block rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                <option value="">Semua</option>
                <option value="keluar">Keluar</option>
                <option value="masuk">Masuk</option>
            </select>
        </div>
        <label class="flex items-center gap-2 pb-2 text-sm text-gray-700 dark:text-gray-200">
            <input type="checkbox" wire:model.live="hanyaTanpaIzin" class="rounded border-gray-300" />
            Hanya tanpa izin
        </label>
    </div>

    @if(count($this->logs) > 0)
        <div class="mt-6 fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Waktu</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Santri</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Arah</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Status</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Petugas</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach($this->logs as $row)
                        <tr>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $row['waktu'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $row['nama'] }} <span class="text-gray-400">({{ $row['nis'] }})</span></td>
                            <td class="fi-ta-cell px-3 py-3 text-sm {{ $row['arah'] === 'keluar' ? 'text-warning-600' : 'text-success-600' }}">{{ $row['arah'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm">
                                @if($row['is_tanpa_izin'])
                                    <span class="rounded-full bg-danger-50 px-2 py-0.5 text-xs font-semibold text-danger-600">TANPA IZIN</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">normal</span>
                                @endif
                            </td>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $row['petugas'] }}</td>
                            <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $row['catatan'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada catatan gerbang pada filter ini.</p>
    @endif
</x-filament-panels::page>
