@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @if ($perluReview)
            <p class="text-sm text-warning-600">
                ⚠️ Laporan pajak ini wajib ditinjau penasihat pajak sebelum dipakai/disetor resmi.
                Format CSV Coretax dapat berubah — validasi terhadap template terbaru DJP.
            </p>
        @endif

        {{-- ── SPT Masa PPh 21 ─────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">SPT Masa PPh 21 (1721) — Masa {{ $masa }}</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Penerima</div>
                    <div class="mt-1 text-xl font-semibold">{{ number_format($sptMasa['bagian_a']['total']['jumlah_penerima'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Bruto</div>
                    <div class="mt-1 text-xl font-semibold">{{ $rp($sptMasa['bagian_a']['total']['bruto']) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">PPh 21 Dipotong</div>
                    <div class="mt-1 text-xl font-bold text-primary-600">{{ $rp($sptMasa['bagian_a']['total']['pph21']) }}</div>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4">Kategori</th>
                            <th class="py-2 pr-4 text-right">Penerima</th>
                            <th class="py-2 pr-4 text-right">Bruto</th>
                            <th class="py-2 text-right">PPh 21</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr>
                            <td class="py-2 pr-4">Pegawai Tetap</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($sptMasa['bagian_a']['pegawai_tetap']['jumlah_penerima'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-4 text-right">{{ $rp($sptMasa['bagian_a']['pegawai_tetap']['bruto']) }}</td>
                            <td class="py-2 text-right">{{ $rp($sptMasa['bagian_a']['pegawai_tetap']['pph21']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pr-4">Pegawai Tidak Tetap</td>
                            <td class="py-2 pr-4 text-right">{{ number_format($sptMasa['bagian_a']['pegawai_tidak_tetap']['jumlah_penerima'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-4 text-right">{{ $rp($sptMasa['bagian_a']['pegawai_tidak_tetap']['bruto']) }}</td>
                            <td class="py-2 text-right">{{ $rp($sptMasa['bagian_a']['pegawai_tidak_tetap']['pph21']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-gray-400">
                Setoran masa — Kode MAP {{ $sptMasa['bagian_b']['kode_map'] }} / KJS {{ $sptMasa['bagian_b']['kode_kjs'] }}.
                PPh 21 disetor: {{ $rp($sptMasa['bagian_b']['pph21_disetor']) }}.
            </p>
        </x-filament::section>

        {{-- ── e-Bupot PPh 21 (Coretax) ───────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">e-Bupot PPh 21 — Masa {{ $masa }}</x-slot>
            <x-slot name="description">Bukti potong per pegawai. Gunakan tombol "Ekspor e-Bupot CSV (Coretax)" di kanan atas untuk unduh.</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Jumlah Bukti Potong</div>
                    <div class="mt-1 text-xl font-semibold">{{ number_format($eBupot['summary']['jumlah_bukti_potong'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total Bruto</div>
                    <div class="mt-1 text-xl font-semibold">{{ $rp($eBupot['summary']['total_bruto']) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total PPh 21</div>
                    <div class="mt-1 text-xl font-bold text-primary-600">{{ $rp($eBupot['summary']['total_pph21']) }}</div>
                </div>
            </div>

            @if (count($eBupot['rows']) > 0)
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">No</th>
                                <th class="py-2 pr-4">Nama</th>
                                <th class="py-2 pr-4">NPWP/NIK</th>
                                <th class="py-2 pr-4">Objek</th>
                                <th class="py-2 pr-4 text-right">Bruto</th>
                                <th class="py-2 text-right">PPh 21</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($eBupot['rows'] as $row)
                                <tr>
                                    <td class="py-2 pr-4">{{ $row['nomor_urut'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['nama'] }}</td>
                                    <td class="py-2 pr-4">{{ $row['npwp'] ?: $row['nik'] ?: '—' }}</td>
                                    <td class="py-2 pr-4">{{ $row['kode_objek_pajak'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $rp($row['bruto']) }}</td>
                                    <td class="py-2 text-right">{{ $rp($row['pph21_dipotong']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    Belum ada bukti potong (slip gaji final dengan PPh 21 &gt; 0) untuk masa ini.
                </p>
            @endif
        </x-filament::section>

        {{-- ── SPT Tahunan Badan 1771 ─────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">SPT Tahunan Badan 1771 — Tahun {{ $tahunBadan }}</x-slot>
            <x-slot name="description">Pendapatan pendidikan dikecualikan (Pasal 4 ayat 3); pendapatan komersial kena PPh Badan.</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pendapatan Pendidikan ({{ $sptBadan['pendapatan']['pendidikan']['status_pajak'] }})</div>
                    <div class="mt-1 text-lg font-semibold">{{ $rp($sptBadan['pendapatan']['pendidikan']['nominal']) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Pendapatan Komersial ({{ $sptBadan['pendapatan']['komersial']['status_pajak'] }})</div>
                    <div class="mt-1 text-lg font-semibold">{{ $rp($sptBadan['pendapatan']['komersial']['nominal']) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Penghasilan Kena Pajak</div>
                    <div class="mt-1 text-lg font-semibold">{{ $rp($sptBadan['penghasilan_kena_pajak']) }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">PPh Badan Terutang (tarif {{ $sptBadan['tarif_pph_badan'] }}%)</div>
                    <div class="mt-1 text-lg font-bold text-primary-600">{{ $rp($sptBadan['pph_badan_terutang']) }}</div>
                </div>
            </div>

            <p class="mt-3 text-xs text-gray-400">
                Kode MAP {{ $sptBadan['kode_map'] }} / KJS {{ $sptBadan['kode_kjs'] }}.
                {{ $sptBadan['koreksi_fiskal']['catatan'] }}
            </p>
        </x-filament::section>

        {{-- ── Sisa Lebih Yayasan (PMK 68/2020) ───────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Sisa Lebih Yayasan (PMK 68/2020)</x-slot>
            <x-slot name="description">Surplus pendidikan bebas PPh Badan bila direinvestasi ke sarana/prasarana dalam 4 tahun.</x-slot>

            @if (count($sisaLebih) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4">Tahun Fiskal</th>
                                <th class="py-2 pr-4 text-right">Surplus</th>
                                <th class="py-2 pr-4 text-right">Teralokasi</th>
                                <th class="py-2 pr-4 text-right">Sisa</th>
                                <th class="py-2 pr-4">Batas Reinvestasi</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($sisaLebih as $s)
                                <tr>
                                    <td class="py-2 pr-4">{{ $s['tahun_fiskal'] }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $rp($s['surplus']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $rp($s['teralokasi']) }}</td>
                                    <td class="py-2 pr-4 text-right">{{ $rp($s['sisa']) }}</td>
                                    <td class="py-2 pr-4">{{ $s['batas'] }}</td>
                                    <td class="py-2">
                                        @php
                                            $warna = match ($s['status']) {
                                                'terpenuhi' => 'text-success-600',
                                                'kena_pajak' => 'text-danger-600',
                                                default => 'text-warning-600',
                                            };
                                        @endphp
                                        <span class="{{ $warna }} font-medium">{{ str_replace('_', ' ', $s['status']) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada catatan sisa lebih yayasan.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
