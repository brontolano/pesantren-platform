<x-filament-panels::page>
    {{ $this->form }}

    @if(count($this->baris) > 0)
        <div class="mt-6">
            <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-x-auto">
                <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                    <thead>
                        <tr>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">No</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Santri</th>
                            @if(count($this->komponen) > 0)
                                @foreach($this->komponen as $k)
                                    <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $k['nama'] }}
                                        <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $k['bobot'] }}%</span>
                                    </th>
                                @endforeach
                            @else
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Harian</th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">UTS</th>
                                <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">UAS</th>
                            @endif
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Nilai Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach($this->baris as $i => $row)
                            <tr>
                                <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">
                                    {{ $row['nama'] }}
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['nis'] }}</span>
                                </td>
                                @if(count($this->komponen) > 0)
                                    @foreach($this->komponen as $k)
                                        <td class="fi-ta-cell px-3 py-3">
                                            <input type="number" min="0" max="100" step="0.01"
                                                wire:model="baris.{{ $i }}.nilai_komponen.{{ $k['id'] }}"
                                                class="fi-input block w-24 rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                        </td>
                                    @endforeach
                                @else
                                    @foreach(['nilai_harian', 'nilai_uts', 'nilai_uas'] as $kolom)
                                        <td class="fi-ta-cell px-3 py-3">
                                            <input type="number" min="0" max="100" step="0.01"
                                                wire:model="baris.{{ $i }}.{{ $kolom }}"
                                                class="fi-input block w-24 rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                        </td>
                                    @endforeach
                                @endif
                                <td class="fi-ta-cell px-3 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $row['nilai_akhir'] !== null ? number_format((float) $row['nilai_akhir'], 2) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <x-filament::button wire:click="simpanSemua" wire:loading.attr="disabled">
                    Simpan Semua
                </x-filament::button>
                <span class="text-sm text-gray-500 dark:text-gray-400" wire:loading wire:target="simpanSemua">Menyimpan…</span>
            </div>
        </div>
    @elseif($this->kelas_id && $this->pelajaran_id && $this->periode_id)
        <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada santri aktif di kelas ini.</p>
    @endif
</x-filament-panels::page>
