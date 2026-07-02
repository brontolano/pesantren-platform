<x-filament-panels::page>
    @php
        $kelas = fn (float $p): string => $p >= 90 ? 'text-success-600' : ($p >= 60 ? 'text-warning-600' : 'text-danger-600');
    @endphp

    {{-- Kesiapan keseluruhan (Refs #327) --}}
    <section class="rounded-lg border p-4 space-y-1">
        <h2 class="text-base font-semibold">Kesiapan EMIS Keseluruhan</h2>
        <p class="text-3xl font-bold tabular-nums {{ $kelas($stat['keseluruhan']['persen']) }}">
            {{ $stat['keseluruhan']['persen'] }}%
        </p>
        <p class="text-sm text-gray-500">
            {{ $stat['keseluruhan']['sel_terisi'] }} dari {{ $stat['keseluruhan']['sel_total'] }} isian wajib EMIS sudah terisi
            (field santri + NIK/NUPTK pegawai + legalitas lembaga). Target laporan: &ge;95%.
        </p>
        <p class="text-sm text-gray-500">
            Klik tautan <em>"yang kurang"</em> untuk langsung memperbaiki datanya.
            Ekspor CSV ada di halaman <strong>Ekspor EMIS</strong> (grup Pelaporan).
        </p>
    </section>

    {{-- Santri: seluruh field wajib EMIS 4.0 --}}
    <section class="rounded-lg border p-4 space-y-3">
        <h2 class="text-base font-semibold">
            Santri Aktif ({{ $stat['santri']['total'] }}) —
            <span class="{{ $kelas($stat['santri']['persen_lengkap']) }}">{{ $stat['santri']['lengkap'] }} lengkap ({{ $stat['santri']['persen_lengkap'] }}%)</span>
        </h2>
        <table class="w-full text-sm">
            <tbody>
                @foreach ($stat['santri']['fields'] as $key => $f)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $f['label'] }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $f['isi'] }} / {{ $stat['santri']['total'] }}</td>
                        <td class="py-2 text-right font-semibold tabular-nums {{ $kelas($f['persen']) }}">{{ $f['persen'] }}%</td>
                        <td class="py-2 text-right">
                            @if ($stat['santri']['total'] - $f['isi'] > 0)
                                <a href="{{ $urlSantri[$key] }}" class="text-primary-600 hover:underline">
                                    Lihat {{ $stat['santri']['total'] - $f['isi'] }} yang kurang &rarr;
                                </a>
                            @else
                                <span class="text-success-600">Lengkap ✓</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- Rincian per lembaga (Refs #327) --}}
    <section class="rounded-lg border p-4 space-y-3">
        <h2 class="text-base font-semibold">Kesiapan per Lembaga</h2>
        @if (count($stat['per_lembaga']) === 0)
            <p class="text-sm text-gray-500">Belum ada santri aktif.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-500">
                        <th class="py-2 font-medium">Lembaga</th>
                        <th class="py-2 font-medium text-right">Santri</th>
                        <th class="py-2 font-medium text-right">Data lengkap</th>
                        <th class="py-2 font-medium text-right">Tanpa NIK</th>
                        <th class="py-2 font-medium text-right">Tanpa NISN</th>
                        <th class="py-2 font-medium text-right">Kesiapan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stat['per_lembaga'] as $l)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $l['nama'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $l['total'] }}</td>
                            <td class="py-2 text-right tabular-nums">{{ $l['lengkap'] }}</td>
                            <td class="py-2 text-right tabular-nums {{ $l['tanpa_nik'] > 0 ? 'text-danger-600' : '' }}">{{ $l['tanpa_nik'] }}</td>
                            <td class="py-2 text-right tabular-nums {{ $l['tanpa_nisn'] > 0 ? 'text-danger-600' : '' }}">{{ $l['tanpa_nisn'] }}</td>
                            <td class="py-2 text-right font-semibold tabular-nums {{ $kelas($l['persen']) }}">{{ $l['persen'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Pegawai --}}
    <section class="rounded-lg border p-4 space-y-3">
        <h2 class="text-base font-semibold">Pegawai Aktif ({{ $stat['pegawai']['total'] }})</h2>
        <table class="w-full text-sm">
            <tbody>
                @foreach ([
                    ['label' => 'Ber-NIK', 'isi' => $stat['pegawai']['ber_nik'], 'persen' => $stat['pegawai']['persen_nik'], 'url' => $url['pegawai_tanpa_nik']],
                    ['label' => 'Ber-NUPTK', 'isi' => $stat['pegawai']['ber_nuptk'], 'persen' => $stat['pegawai']['persen_nuptk'], 'url' => $url['pegawai_tanpa_nuptk']],
                    ['label' => 'Sertifikasi terdata', 'isi' => $stat['pegawai']['ber_sertifikasi'], 'persen' => $stat['pegawai']['persen_sertifikasi'], 'url' => $url['pegawai_tanpa_sertifikasi']],
                ] as $b)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $b['label'] }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $b['isi'] }} / {{ $stat['pegawai']['total'] }}</td>
                        <td class="py-2 text-right font-semibold tabular-nums {{ $kelas($b['persen']) }}">{{ $b['persen'] }}%</td>
                        <td class="py-2 text-right">
                            @if ($bisaLihatPegawai)
                                <a href="{{ $b['url'] }}" class="text-primary-600 hover:underline">
                                    Lihat {{ $stat['pegawai']['total'] - $b['isi'] }} yang kurang &rarr;
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- Lembaga --}}
    <section class="rounded-lg border p-4 space-y-3">
        <h2 class="text-base font-semibold">Lembaga ({{ $stat['lembaga']['total'] }})</h2>
        @if ($stat['lembaga']['tanpa_legalitas'] === 0)
            <p class="text-sm text-success-600">Semua lembaga sudah punya NSM/NSPP dan izin operasional. ✓</p>
        @else
            <p class="text-sm">
                <span class="font-semibold text-danger-600">{{ $stat['lembaga']['tanpa_legalitas'] }}</span>
                lembaga belum punya NSM/NSPP atau izin operasional (IJOP).
                <a href="{{ $url['lembaga_tanpa_legalitas'] }}" class="text-primary-600 hover:underline">Lihat daftarnya &rarr;</a>
            </p>
        @endif
    </section>
</x-filament-panels::page>
