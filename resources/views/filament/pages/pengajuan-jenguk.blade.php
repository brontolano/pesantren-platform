<x-filament-panels::page>
    @if(count($this->pengajuan) > 0)
        <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead>
                    <tr>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Tanggal</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Santri</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Pengaju</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Catatan</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-end text-sm font-semibold text-gray-950 dark:text-white">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach($this->pengajuan as $p)
                        <tr wire:key="jenguk-{{ $p['id'] }}">
                            <td class="px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $p['tanggal'] }}</td>
                            <td class="px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $p['santri'] }} <span class="text-gray-500">({{ $p['nis'] }})</span></td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $p['pengaju'] }}</td>
                            <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $p['catatan'] ?? '-' }}</td>
                            <td class="px-3 py-3 text-end">
                                <div class="flex justify-end gap-2">
                                    <x-filament::button size="sm" color="success"
                                        wire:click="putuskan({{ $p['id'] }}, 'dikonfirmasi')"
                                        wire:confirm="Konfirmasi pengajuan jenguk ini?">
                                        Konfirmasi
                                    </x-filament::button>
                                    <x-filament::button size="sm" color="danger"
                                        wire:click="putuskan({{ $p['id'] }}, 'ditolak')"
                                        wire:confirm="Tolak pengajuan jenguk ini?">
                                        Tolak
                                    </x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            Tidak ada pengajuan jenguk yang menunggu keputusan.
        </div>
    @endif
</x-filament-panels::page>
