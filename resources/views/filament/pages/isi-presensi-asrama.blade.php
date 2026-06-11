<x-filament-panels::page>
    <form wire:submit="simpan">
        {{ $this->form }}

        @if(count($this->dataSantri) > 0)
            <div class="mt-6">
                <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                        <thead>
                            <tr>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">No</th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Nama Santri</th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Status</th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach($this->dataSantri as $i => $row)
                                <tr>
                                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $row['nama'] }}</td>
                                    <td class="fi-ta-cell px-3 py-4">
                                        <select wire:model="dataSantri.{{ $i }}.status"
                                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                            <option value="">-- Pilih --</option>
                                            <option value="hadir">Hadir</option>
                                            <option value="alpha">Alpha</option>
                                            <option value="izin">Izin</option>
                                            <option value="sakit">Sakit</option>
                                            <option value="terlambat">Terlambat</option>
                                        </select>
                                    </td>
                                    <td class="fi-ta-cell px-3 py-4">
                                        <input type="text" wire:model="dataSantri.{{ $i }}.keterangan"
                                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                                            placeholder="Opsional">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                <x-filament::button type="submit">
                    Simpan Presensi
                </x-filament::button>
            </div>
        @else
            <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Pilih kamar atau halaqah untuk memuat daftar santri.
            </div>
        @endif
    </form>
</x-filament-panels::page>
