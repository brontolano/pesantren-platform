<?php

namespace Modules\AI\Services\Tools;

/**
 * T-A033 — kontrak respons baku semua tool data resmi (Blueprint §6):
 * {sukses, nilai, cap_waktu, sumber, pesan}. Agen TIDAK PERNAH menjawab
 * angka tanpa objek ini.
 *
 * keTeks() = formatter TEMPLATE per tool (Blueprint §7.1 template-first):
 * jawaban tool dirender string Indonesia santun TANPA LLM — dipakai langsung
 * chatbot rule-based (T-A051) untuk mayoritas trafik wali.
 */
final class ToolResult
{
    /**
     * @param  array<string, mixed>  $nilai
     */
    public function __construct(
        public readonly bool $sukses,
        public readonly string $tool,
        public readonly array $nilai,
        public readonly ?string $capWaktu,
        public readonly ?string $sumber,
        public readonly ?string $pesan = null,
    ) {}

    /** @param  array<string, mixed>  $nilai */
    public static function ok(string $tool, array $nilai, string $sumber): self
    {
        return new self(
            sukses: true,
            tool: $tool,
            nilai: $nilai,
            capWaktu: now()->toIso8601String(),
            sumber: $sumber,
        );
    }

    public static function gagal(string $tool, string $pesan): self
    {
        return new self(
            sukses: false,
            tool: $tool,
            nilai: [],
            capWaktu: now()->toIso8601String(),
            sumber: null,
            pesan: $pesan,
        );
    }

    /** @return array<string, mixed> */
    public function keArray(): array
    {
        return [
            'sukses' => $this->sukses,
            'tool' => $this->tool,
            'nilai' => $this->nilai,
            'cap_waktu' => $this->capWaktu,
            'sumber' => $this->sumber,
            'pesan' => $this->pesan,
        ];
    }

    /**
     * Render ringkas Indonesia santun per tool — string template sederhana,
     * TANPA LLM. Gagal → kembalikan pesan santun apa adanya.
     */
    public function keTeks(): string
    {
        if (! $this->sukses) {
            return (string) ($this->pesan ?? 'Mohon maaf, permintaan tidak dapat diproses.');
        }

        $teks = match ($this->tool) {
            'getTagihan' => $this->teksTagihan(),
            'getStatusBayar' => $this->teksStatusBayar(),
            'getRingkasanKeuangan' => $this->teksRingkasanKeuangan(),
            'getJadwal' => $this->teksJadwal(),
            'getNilai' => $this->teksNilai(),
            'getTahfidz' => $this->teksTahfidz(),
            'getKehadiran' => $this->teksKehadiran(),
            'getIzin' => $this->teksIzin(),
            'getStatusPPDB' => $this->teksStatusPpdb(),
            'getSantri' => $this->teksSantri(),
            default => $this->teksGenerik(),
        };

        return $teks."\n(Sumber: {$this->sumber}, per {$this->capWaktu})";
    }

    private static function rupiah(float|int|string|null $n): string
    {
        return 'Rp '.number_format((float) $n, 0, ',', '.');
    }

    private function teksTagihan(): string
    {
        $daftar = (array) ($this->nilai['daftar'] ?? []);

        if ($daftar === []) {
            return 'Alhamdulillah, tidak ada tagihan yang belum lunas saat ini.';
        }

        $baris = [];
        foreach ($daftar as $i => $t) {
            $baris[] = sprintf(
                '%d. %s — %s (sisa %s, jatuh tempo %s)',
                $i + 1,
                (string) ($t['jenis'] ?? '-'),
                self::rupiah($t['nominal'] ?? 0),
                self::rupiah($t['sisa'] ?? 0),
                (string) ($t['jatuh_tempo'] ?? '-'),
            );
        }

        return "Berikut tagihan yang belum lunas:\n".implode("\n", $baris)
            ."\nTotal sisa tagihan: ".self::rupiah($this->nilai['total_sisa'] ?? 0).'.';
    }

    private function teksStatusBayar(): string
    {
        return sprintf(
            'Status pembayaran: %d tagihan lunas, %d belum lunas. Total tagihan %s, sudah dibayar (terverifikasi) %s, sisa %s.',
            (int) ($this->nilai['jumlah_lunas'] ?? 0),
            (int) ($this->nilai['jumlah_belum_lunas'] ?? 0),
            self::rupiah($this->nilai['total_tagihan'] ?? 0),
            self::rupiah($this->nilai['total_dibayar'] ?? 0),
            self::rupiah($this->nilai['total_sisa'] ?? 0),
        );
    }

    private function teksRingkasanKeuangan(): string
    {
        $neraca = (array) ($this->nilai['neraca'] ?? []);
        $lr = (array) ($this->nilai['laba_rugi'] ?? []);
        $kas = (array) ($this->nilai['arus_kas'] ?? []);

        return 'Ringkasan keuangan: total aset '.self::rupiah($neraca['total_aset'] ?? 0)
            .' (neraca '.((($neraca['seimbang'] ?? false) === true) ? 'seimbang' : 'TIDAK seimbang').'). '
            .'Pendapatan '.self::rupiah($lr['total_pendapatan'] ?? 0)
            .', beban '.self::rupiah($lr['total_beban'] ?? 0)
            .', surplus/defisit '.self::rupiah($lr['surplus_defisit'] ?? 0).'. '
            .'Kas: masuk '.self::rupiah($kas['total_masuk'] ?? 0)
            .', keluar '.self::rupiah($kas['total_keluar'] ?? 0)
            .', saldo akhir '.self::rupiah($kas['saldo_akhir'] ?? 0).'.';
    }

    private function teksJadwal(): string
    {
        $daftar = (array) ($this->nilai['daftar'] ?? []);

        if ($daftar === []) {
            return 'Belum ada jadwal pelajaran yang terdaftar untuk kelas ini.';
        }

        $baris = [];
        foreach ($daftar as $j) {
            $baris[] = sprintf(
                '- %s %s–%s: %s%s',
                (string) ($j['hari'] ?? '-'),
                (string) ($j['jam_mulai'] ?? '-'),
                (string) ($j['jam_selesai'] ?? '-'),
                (string) ($j['pelajaran'] ?? '-'),
                ($j['ruangan'] ?? null) ? ' (ruang '.$j['ruangan'].')' : '',
            );
        }

        return "Jadwal pelajaran:\n".implode("\n", $baris);
    }

    private function teksNilai(): string
    {
        $daftar = (array) ($this->nilai['daftar'] ?? []);

        if ($daftar === []) {
            return 'Belum ada nilai yang tercatat.';
        }

        $baris = [];
        foreach ($daftar as $n) {
            $baris[] = sprintf(
                '- %s: nilai akhir %s',
                (string) ($n['pelajaran'] ?? '-'),
                $n['nilai_akhir'] !== null ? (string) $n['nilai_akhir'] : 'belum tersedia',
            );
        }

        $rata = $this->nilai['rata_rata'] ?? null;

        return "Nilai akademik:\n".implode("\n", $baris)
            .($rata !== null ? "\nRata-rata nilai akhir: {$rata}." : '');
    }

    private function teksTahfidz(): string
    {
        $rata = $this->nilai['rata_rata_nilai'] ?? null;

        return sprintf(
            'Perkembangan tahfidz: %d hafalan tercatat (%d selesai, %d berjalan, %d direncanakan).%s',
            (int) ($this->nilai['total'] ?? 0),
            (int) ($this->nilai['selesai'] ?? 0),
            (int) ($this->nilai['berjalan'] ?? 0),
            (int) ($this->nilai['direncanakan'] ?? 0),
            $rata !== null ? " Rata-rata nilai penilaian: {$rata}." : '',
        );
    }

    private function teksKehadiran(): string
    {
        $rekap = (array) ($this->nilai['rekap'] ?? []);

        if ($rekap === []) {
            return 'Belum ada catatan kehadiran kegiatan.';
        }

        $bagian = [];
        foreach ($rekap as $status => $jumlah) {
            $bagian[] = "{$status}: {$jumlah}";
        }

        return 'Rekap kehadiran kegiatan — '.implode(', ', $bagian).'.';
    }

    private function teksIzin(): string
    {
        $daftar = (array) ($this->nilai['daftar'] ?? []);

        if ($daftar === []) {
            return 'Tidak ada catatan izin.';
        }

        $baris = [];
        foreach ($daftar as $iz) {
            $baris[] = sprintf(
                '- %s (%s s.d. %s) — status %s',
                (string) ($iz['jenis'] ?? '-'),
                (string) ($iz['tanggal_mulai'] ?? '-'),
                (string) ($iz['tanggal_selesai'] ?? '-'),
                (string) ($iz['status'] ?? '-'),
            );
        }

        return "Catatan izin terakhir:\n".implode("\n", $baris);
    }

    private function teksStatusPpdb(): string
    {
        return sprintf(
            'Status pendaftaran atas nama %s (nomor %s): %s, gelombang %s, tahun ajaran %s.',
            (string) ($this->nilai['nama'] ?? '-'),
            (string) ($this->nilai['nomor_pendaftaran'] ?? '-'),
            (string) ($this->nilai['status_pendaftaran'] ?? '-'),
            (string) ($this->nilai['gelombang'] ?? '-'),
            (string) ($this->nilai['tahun_ajaran'] ?? '-'),
        );
    }

    private function teksSantri(): string
    {
        return sprintf(
            'Data santri: %s (NIS %s), jenis kelamin %s, status %s.',
            (string) ($this->nilai['nama'] ?? '-'),
            (string) ($this->nilai['nis'] ?? '-'),
            (string) ($this->nilai['jenis_kelamin'] ?? '-'),
            (string) ($this->nilai['status'] ?? '-'),
        );
    }

    private function teksGenerik(): string
    {
        return 'Data: '.json_encode($this->nilai, JSON_UNESCAPED_UNICODE);
    }
}
