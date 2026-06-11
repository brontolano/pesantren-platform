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
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Rekomendasi</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Alasan</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Keputusan Rapat</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Kelas Tujuan</th>
                            <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Catatan Rapat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                        @foreach($this->baris as $i => $row)
                            <tr @class(['opacity-60' => $row['final']])>
                                <td class="fi-ta-cell px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $i + 1 }}</td>
                                <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">
                                    {{ $row['nama'] }}
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['nis'] }}</span>
                                    @if($row['final'])
                                        <span class="text-xs font-semibold text-success-600">✔ FINAL</span>
                                    @endif
                                </td>
                                <td class="fi-ta-cell px-3 py-4 text-sm">
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-1 text-xs font-medium',
                                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $row['rekomendasi'] === 'naik',
                                        'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400' => $row['rekomendasi'] === 'lulus',
                                        'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => $row['rekomendasi'] === 'tinggal',
                                    ])>{{ strtoupper($row['rekomendasi']) }}</span>
                                </td>
                                <td class="fi-ta-cell px-3 py-4 text-xs text-gray-600 dark:text-gray-300 max-w-xs">{{ $row['alasan'] }}</td>
                                <td class="fi-ta-cell px-3 py-4">
                                    <select wire:model.live="baris.{{ $i }}.keputusan" @disabled($row['final'])
                                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                        <option value="naik">Naik</option>
                                        <option value="tinggal">Tinggal</option>
                                        <option value="lulus">Lulus</option>
                                    </select>
                                </td>
                                <td class="fi-ta-cell px-3 py-4">
                                    @if($row['keputusan'] === 'naik')
                                        <select wire:model="baris.{{ $i }}.kelas_tujuan_id" @disabled($row['final'])
                                            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                                            <option value="">-- Pilih --</option>
                                            @foreach($this->opsiTujuan as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="fi-ta-cell px-3 py-4">
                                    <input type="text" wire:model="baris.{{ $i }}.catatan" @disabled($row['final'])
                                        class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                                        placeholder="Opsional">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex gap-3">
            <x-filament::button color="gray" wire:click="simpanDraft">
                Simpan Draft Keputusan
            </x-filament::button>
            <x-filament::button color="danger" wire:click="finalisasi"
                wire:confirm="Finalisasi akan mengeksekusi penempatan kelas baru untuk keputusan NAIK dan mengunci semua keputusan. Lanjutkan?">
                Finalisasi Sidang
            </x-filament::button>
        </div>
        @if($this->modePenempatan === 'bebas')
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Mode penempatan <strong>bebas</strong> (mu'adalah): kelas tujuan boleh lompat/turun tingkat.
            </p>
        @endif
    @elseif($this->kelas_id && $this->periode_id)
        <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada santri aktif di kelas ini.</div>
    @else
        <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">Pilih kelas dan periode untuk memuat evaluasi.</div>
    @endif
</x-filament-panels::page>
