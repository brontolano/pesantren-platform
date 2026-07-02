<?php

namespace Modules\AI\Services\Settings;

/**
 * T-A011 — Ringkasan Kebijakan dalam bahasa manusia.
 *
 * Menyusun kalimat "AI di pesantren ini AKAN ..." dan "TIDAK AKAN ..."
 * MURNI dari nilai pengaturan (template kondisional deterministik —
 * BUKAN LLM, tidak ada konten keagamaan yang dikarang). Dipakai di
 * langkah akhir Wizard dan di halaman persetujuan Kyai/Pimpinan agar
 * keduanya membaca ringkasan yang persis sama.
 */
class RingkasanKebijakan
{
    /** Label scope data dalam bahasa manusia. */
    private const LABEL_SCOPE = [
        'tagihan' => 'data tagihan santri',
        'jadwal' => 'data jadwal kegiatan & pelajaran',
        'nilai' => 'data nilai akademik santri',
        'kehadiran' => 'data kehadiran santri',
        'kesehatan' => 'data kesehatan santri',
        'keuangan' => 'data keuangan pesantren',
    ];

    /** Label fitur per peran dalam bahasa manusia. */
    private const LABEL_FITUR = [
        'asatidz' => [
            'generator_soal' => 'membantu Asatidz menyusun draf soal latihan',
            'generator_rpp' => 'membantu Asatidz menyusun draf RPP/modul ajar',
            'chatbot' => 'melayani tanya-jawab Asatidz',
        ],
        'admin' => [
            'chatbot' => 'melayani tanya-jawab staf administrasi',
            'dokumen_tu' => 'membantu staf TU menyusun draf dokumen administrasi',
        ],
        'bendahara' => [
            'ringkasan_keuangan' => 'membantu Bendahara merangkum laporan keuangan',
        ],
        'wali' => [
            'chatbot' => 'melayani tanya-jawab Wali Santri',
        ],
        'santri' => [
            'tutor' => 'menjadi tutor belajar bagi santri',
        ],
    ];

    /**
     * Susun ringkasan dua kolom: kalimat AKAN dan TIDAK AKAN.
     *
     * @param  array<string, mixed>  $payload
     * @return array{akan: list<string>, tidak_akan: list<string>}
     */
    public function susun(array $payload): array
    {
        $akan = [];
        $tidakAkan = [];

        // --- Batasan jawaban keagamaan (level_fatwa) -------------------
        switch ($payload['level_fatwa'] ?? 'edukatif_only') {
            case 'informatif':
                $akan[] = 'menyampaikan informasi dari daftar rujukan yang telah ditinjau Dewan Ustadz, dengan menyebutkan sumbernya';
                $tidakAkan[] = 'tidak akan memberikan kesimpulan hukum atau fatwa — keputusan hukum tetap kembali kepada Asatidz dan Kyai';
                break;
            case 'khilafiyah':
                $akan[] = 'memaparkan ragam pendapat dari rujukan yang diizinkan beserta sumber masing-masing';
                $tidakAkan[] = 'tidak akan menetapkan satu pendapat sebagai keputusan hukum — pertimbangan akhir tetap pada Kyai dan Asatidz';
                break;
            case 'edukatif_only':
            default:
                $akan[] = 'menjawab pertanyaan seputar materi pelajaran dan administrasi saja';
                $tidakAkan[] = 'tidak akan menjawab pertanyaan hukum agama — setiap pertanyaan demikian dialihkan kepada Asatidz';
                break;
        }

        // --- Topik terlarang -------------------------------------------
        $blacklist = (array) ($payload['blacklist_topik'] ?? []);
        if ($blacklist !== []) {
            $tidakAkan[] = sprintf(
                'tidak akan membahas %d topik yang masuk daftar larangan pesantren',
                count($blacklist),
            );
        }

        // --- Eskalasi --------------------------------------------------
        $eskalasi = (array) ($payload['eskalasi_tujuan'] ?? []);
        if (($eskalasi['penerima'] ?? []) !== []) {
            $akan[] = sprintf(
                'meneruskan pertanyaan yang berada di luar wewenangnya kepada %d penerima eskalasi melalui %s',
                count($eskalasi['penerima']),
                ($eskalasi['kanal'] ?? 'panel') === 'wa' ? 'WhatsApp' : 'panel',
            );
        }

        // --- Fitur per peran -------------------------------------------
        foreach ((array) ($payload['fitur_per_peran'] ?? []) as $peran => $fiturs) {
            foreach ((array) $fiturs as $fitur => $aktif) {
                $label = self::LABEL_FITUR[$peran][$fitur] ?? null;
                if ($label === null) {
                    continue;
                }
                if ($aktif) {
                    $akan[] = $label;
                } else {
                    $tidakAkan[] = 'tidak akan '.$label;
                }
            }
        }

        if (($payload['mode_akses_santri'] ?? 'tanpa_akses') === 'tanpa_akses') {
            $tidakAkan[] = 'tidak akan dapat diakses langsung oleh santri dari perangkat apa pun';
        }

        // --- Data & privasi --------------------------------------------
        foreach ((array) ($payload['scope_data'] ?? []) as $scope => $boleh) {
            $label = self::LABEL_SCOPE[$scope] ?? $scope;
            if ($boleh) {
                $akan[] = 'membaca '.$label.' untuk menjawab pertanyaan yang berwenang';
            } else {
                $tidakAkan[] = 'tidak akan membaca '.$label;
            }
        }

        $retensi = (int) ($payload['retensi_hari'] ?? 90);
        $retensiSantri = (int) ($payload['retensi_hari_santri'] ?? 30);
        $akan[] = sprintf(
            'menghapus riwayat percakapan setelah %d hari (riwayat santri setelah %d hari)',
            $retensi,
            $retensiSantri,
        );

        if (($payload['optin_riset'] ?? false) === true) {
            $akan[] = 'menyertakan data penggunaan anonim untuk riset perbaikan produk';
        } else {
            $tidakAkan[] = 'tidak akan mengirimkan data penggunaan untuk riset produk';
        }

        // --- Chatbot WhatsApp (gap-135 mode uji) ------------------------
        if (($payload['mode_uji_wa'] ?? true) === true) {
            $tidakAkan[] = 'tidak akan menjawab pesan WhatsApp dengan AI selama mode uji coba aktif — penanya hanya menerima balasan template singkat';
        } else {
            $akan[] = 'menjawab pesan WhatsApp wali secara penuh (verifikasi wali, lalu tanya-jawab) karena mode uji coba dimatikan';
        }

        // --- Jam layanan & anggaran -------------------------------------
        $jam = (array) ($payload['jam_layanan'] ?? []);
        $mulai = (string) ($jam['mulai'] ?? '00:00');
        $selesai = (string) ($jam['selesai'] ?? '23:59');
        if ($mulai === '00:00' && in_array($selesai, ['23:59', '24:00'], true)) {
            $akan[] = 'melayani sepanjang hari (24 jam)';
        } else {
            $akan[] = sprintf('melayani hanya pada pukul %s sampai %s', $mulai, $selesai);
            $tidakAkan[] = 'tidak akan menjawab di luar jam layanan — penanya menerima pesan penutup yang santun';
        }

        $anggaran = $payload['anggaran_kredit_bulanan'] ?? null;
        if ($anggaran !== null && $anggaran !== '') {
            $akan[] = sprintf(
                'berhenti melayani bila pemakaian kredit bulan berjalan mencapai batas %s kredit (peringatan pada %d%%)',
                number_format((float) $anggaran, 0, ',', '.'),
                (int) ($payload['ambang_alert_persen'] ?? 80),
            );
        }

        return ['akan' => $akan, 'tidak_akan' => $tidakAkan];
    }

    /**
     * Render ringkasan ke HTML lewat template Blade modul (dipakai Wizard
     * dan halaman persetujuan — satu sumber kebenaran tampilan).
     *
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        return view('ai::filament.partials.ringkasan-kebijakan', [
            'ringkasan' => $this->susun($payload),
        ])->render();
    }
}
