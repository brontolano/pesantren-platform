<x-filament-panels::page>
    {{ $this->form }}

    @if($this->santri_id)
        <div class="mt-6">
            <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">Rekap per Kitab</h3>
            @if(count($this->progres) > 0)
                <div class="overflow-x-auto">
                    <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                            <thead>
                                <tr>
                                    @foreach(['Kitab', 'Setoran', 'Posisi Terakhir', 'Terakhir', 'Status', 'Nilai Rata'] as $h)
                                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach($this->progres as $p)
                                    <tr>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $p['kitab'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $p['jumlah_setoran'] }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $p['posisi_terakhir'] }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $p['tanggal_terakhir'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm">
                                            @if(!empty($p['khatam']))
                                                <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">Khatam</span>
                                            @else
                                                <span class="text-gray-500 dark:text-gray-400">{{ $p['status_terakhir'] }}</span>
                                            @endif
                                        </td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $p['nilai_rata'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada setoran sorogan untuk santri ini.</p>
            @endif
        </div>

        @if(count($this->riwayat) > 0)
            <div class="mt-6">
                <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">Riwayat Setoran (50 terakhir)</h3>
                <div class="overflow-x-auto">
                    <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                            <thead>
                                <tr>
                                    @foreach(['Tanggal', 'Kitab', 'Bab', 'Hal.', 'Metode', 'Penyimak', 'Kelancaran', 'Makna', "Nahwu/I'rab", 'Rata', 'Status', 'Catatan'] as $h)
                                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">{{ $h }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                                @foreach($this->riwayat as $r)
                                    <tr>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['tanggal'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-950 dark:text-white">{{ $r['kitab'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['bab'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['halaman'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['metode'] }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['ustadz'] ?? '-' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['nilai_kelancaran'] ?? '—' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['nilai_makna'] ?? '—' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['nilai_nahwu'] ?? '—' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm font-medium text-gray-950 dark:text-white">{{ $r['nilai_rata'] ?? '—' }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['status'] }}</td>
                                        <td class="fi-ta-cell px-3 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $r['catatan'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @else
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Pilih santri untuk melihat progres baca kitabnya.</p>
    @endif
</x-filament-panels::page>
