<x-filament-panels::page>
    @php
        $baris = function (string $label, int $isi, int $total, float $persen, string $url): array {
            return ['label' => $label, 'isi' => $isi, 'total' => $total, 'persen' => $persen, 'url' => $url];
        };
        $kelas = fn (float $p): string => $p >= 90 ? 'text-success-600' : ($p >= 60 ? 'text-warning-600' : 'text-danger-600');
    @endphp

    <div class="rounded-lg border p-4 text-sm text-gray-500">
        Rekap kelengkapan data identitas untuk pelaporan EMIS. Klik tautan
        <em>"yang kurang"</em> untuk langsung memperbaiki datanya. Ekspor CSV
        ada di halaman <strong>Ekspor EMIS</strong> (grup Pelaporan).
    </div>

    {{-- Santri --}}
    <section class="rounded-lg border p-4 space-y-3">
        <h2 class="text-base font-semibold">Santri Aktif ({{ $stat['santri']['total'] }})</h2>
        <table class="w-full text-sm">
            <tbody>
                @foreach ([
                    $baris('Ber-NIK', $stat['santri']['ber_nik'], $stat['santri']['total'], $stat['santri']['persen_nik'], $url['santri_tanpa_nik']),
                    $baris('Ber-NISN', $stat['santri']['ber_nisn'], $stat['santri']['total'], $stat['santri']['persen_nisn'], $url['santri_tanpa_nisn']),
                ] as $b)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $b['label'] }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $b['isi'] }} / {{ $b['total'] }}</td>
                        <td class="py-2 text-right font-semibold tabular-nums {{ $kelas($b['persen']) }}">{{ $b['persen'] }}%</td>
                        <td class="py-2 text-right">
                            <a href="{{ $b['url'] }}" class="text-primary-600 hover:underline">
                                Lihat {{ $b['total'] - $b['isi'] }} yang kurang &rarr;
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- Pegawai --}}
    <section class="rounded-lg border p-4 space-y-3">
        <h2 class="text-base font-semibold">Pegawai Aktif ({{ $stat['pegawai']['total'] }})</h2>
        <table class="w-full text-sm">
            <tbody>
                @foreach ([
                    $baris('Ber-NIK', $stat['pegawai']['ber_nik'], $stat['pegawai']['total'], $stat['pegawai']['persen_nik'], $url['pegawai_tanpa_nik']),
                    $baris('Ber-NUPTK', $stat['pegawai']['ber_nuptk'], $stat['pegawai']['total'], $stat['pegawai']['persen_nuptk'], $url['pegawai_tanpa_nuptk']),
                    $baris('Sertifikasi terdata', $stat['pegawai']['ber_sertifikasi'], $stat['pegawai']['total'], $stat['pegawai']['persen_sertifikasi'], $url['pegawai_tanpa_sertifikasi']),
                ] as $b)
                    <tr class="border-b last:border-0">
                        <td class="py-2">{{ $b['label'] }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $b['isi'] }} / {{ $b['total'] }}</td>
                        <td class="py-2 text-right font-semibold tabular-nums {{ $kelas($b['persen']) }}">{{ $b['persen'] }}%</td>
                        <td class="py-2 text-right">
                            @if ($bisaLihatPegawai)
                                <a href="{{ $b['url'] }}" class="text-primary-600 hover:underline">
                                    Lihat {{ $b['total'] - $b['isi'] }} yang kurang &rarr;
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
