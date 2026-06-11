<x-filament-panels::page>
    <form wire:submit="simpan">
        {{ $this->form }}

        @if(count($this->dataSantri) > 0 && count($this->kriteria) > 0)
            <div class="mt-6 overflow-x-auto">
                <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                        <thead>
                            <tr>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">No</th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Nama Santri</th>
                                @foreach($this->kriteria as $k)
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $k['nama'] }}
                                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">bobot {{ $k['bobot'] }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach($this->dataSantri as $i => $row)
                                <tr>
                                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                    <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">{{ $row['nama'] }}</td>
                                    @foreach($this->kriteria as $k)
                                        <td class="fi-ta-cell px-3 py-4">
                                            <input type="number" min="0" max="100" step="0.5"
                                                wire:model="dataSantri.{{ $i }}.skor.{{ $k['id'] }}"
                                                class="fi-input block w-24 rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                                                placeholder="0-100">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                <x-filament::button type="submit">
                    Simpan Skor Rubrik
                </x-filament::button>
            </div>
        @elseif(count($this->kriteria) === 0)
            <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Belum ada kriteria rubrik aktif untuk periode ini. Buat dulu di menu Rubrik Kriteria.
            </div>
        @else
            <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Pilih periode dan kelas/halaqah untuk memuat daftar santri.
            </div>
        @endif
    </form>
</x-filament-panels::page>
