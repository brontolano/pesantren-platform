<?php

namespace Modules\AI\Services\Wa;

use App\Models\AccessWali;
use App\Models\User;
use App\Services\PortalWali\PortalWaliIdentifierResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Modules\AI\Models\AiConversation;
use Modules\Kesantrian\Models\Santri;
use Throwable;

/**
 * T-A051 — verifikasi ringan wali sesi pertama (PRD §8.2, Blueprint §6).
 *
 * TANPA AUTH BARU (Aturan Emas #10): pencocokan nomor pengirim memakai
 * PortalWaliIdentifierResolver eksisting (users.phone ber-role wali →
 * santri.no_hp_wali → access_wali), dengan normalisasi +62/62/08 satu pintu
 * di App\Support\PhoneNumber. Kelas ini TIDAK membuat akun/sesi/token —
 * hanya menautkan percakapan WA ke identitas wali yang SUDAH ada.
 *
 * Alur (state ai_conversation.verifikasi_status):
 *  - nomor dikenal  → 'menunggu_tgl_lahir': minta tanggal lahir salah satu
 *    anak (format fleksibel: dd-mm-yyyy / dd mm yyyy / dd/mm/yyyy /
 *    "5 maret 2014"); cocok = 'terverifikasi' (berlaku per sesi percakapan);
 *  - salah 3x → kunci 24 jam (verifikasi_terkunci_sampai) + saran admin;
 *  - nomor tak dikenal → tetap 'belum'; pemanggil hanya melayani info publik.
 *
 * PRIVASI: nomor hanya hidup in-memory selama pencocokan (dekripsi Crypt);
 * tidak pernah di-log/dipersist polos.
 */
class WaliVerifier
{
    public function __construct(
        private readonly PortalWaliIdentifierResolver $resolver,
    ) {}

    /**
     * Kandidat user wali untuk nomor pengirim percakapan ini.
     * null = nomor tidak dikenal (bukan wali terdaftar) ATAU data tak terbaca.
     */
    public function kandidat(AiConversation $conversation): ?User
    {
        $nomor = $this->nomorPengirim($conversation);

        if ($nomor === '') {
            return null;
        }

        try {
            return $this->resolver->resolve($nomor);
        } catch (Throwable) {
            // Skema Kesantrian tidak tersedia / data korup → fail-closed
            // sebagai "tidak dikenal" (hanya info publik), jangan meledak.
            return null;
        }
    }

    /**
     * Daftar anak tertaut wali via AccessWali — urut anak utama dulu
     * (is_primary desc, santri_id asc). Anak pertama = default sesi.
     *
     * @return \Illuminate\Support\Collection<int, Santri>
     */
    public function anakTertaut(User $wali)
    {
        $ids = AccessWali::query()
            ->where('wali_id', $wali->getKey())
            ->orderByDesc('is_primary')
            ->orderBy('santri_id')
            ->pluck('santri_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Santri::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Santri $s) => array_search((int) $s->getKey(), $ids, true))
            ->values();
    }

    /**
     * Cocokkan tanggal yang dikirim wali dengan tanggal_lahir salah satu anak
     * tertaut. Kembalikan santri yang cocok (null = tidak ada yang cocok).
     */
    public function cocokkanTanggalLahir(User $wali, CarbonImmutable $tanggal): ?Santri
    {
        foreach ($this->anakTertaut($wali) as $santri) {
            if ($santri->tanggal_lahir !== null && $santri->tanggal_lahir->isSameDay($tanggal)) {
                return $santri;
            }
        }

        return null;
    }

    /**
     * Parse tanggal FLEKSIBEL dari teks bebas wali (dd-mm-yyyy, dd/mm/yyyy,
     * dd.mm.yyyy, "dd mm yyyy", "5 maret 2014"). null = tidak ada tanggal.
     */
    public static function parseTanggal(string $teks): ?CarbonImmutable
    {
        $teks = mb_strtolower(trim($teks));

        // 1. Angka penuh: dd[-/. ]mm[-/. ]yyyy (pemisah konsisten bebas).
        if (preg_match('/(?<!\d)(\d{1,2})[\s\-\/.](\d{1,2})[\s\-\/.](\d{4})(?!\d)/u', $teks, $m) === 1) {
            return self::buatTanggal((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // 2. Nama bulan Indonesia: "5 maret 2014" / "05 agustus 2013".
        $bulan = [
            'januari' => 1, 'februari' => 2, 'pebruari' => 2, 'maret' => 3,
            'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
            'agustus' => 8, 'september' => 9, 'oktober' => 10,
            'november' => 11, 'nopember' => 11, 'desember' => 12,
        ];

        if (preg_match('/(?<!\d)(\d{1,2})\s+([a-z]+)\s+(\d{4})(?!\d)/u', $teks, $m) === 1
            && isset($bulan[$m[2]])) {
            return self::buatTanggal((int) $m[3], $bulan[$m[2]], (int) $m[1]);
        }

        // 3. Format ISO yyyy-mm-dd (wali yang menyalin dari dokumen).
        if (preg_match('/(?<!\d)(\d{4})-(\d{1,2})-(\d{1,2})(?!\d)/u', $teks, $m) === 1) {
            return self::buatTanggal((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /** Validasi komponen tanggal sungguhan (tolak 32-13-2014 dst). */
    private static function buatTanggal(int $tahun, int $bulan, int $hari): ?CarbonImmutable
    {
        if (! checkdate($bulan, $hari, $tahun)) {
            return null;
        }

        return CarbonImmutable::create($tahun, $bulan, $hari)->startOfDay();
    }

    /**
     * Nomor pengirim percakapan, in-memory saja: dekripsi ciphertext lalu
     * buang suffix JID WhatsApp (628xx@c.us → 628xx).
     */
    private function nomorPengirim(AiConversation $conversation): string
    {
        try {
            $nomor = Crypt::decryptString((string) $conversation->nomor_wa_terenkripsi);
        } catch (Throwable) {
            return '';
        }

        $posisi = strpos($nomor, '@');

        return trim($posisi === false ? $nomor : substr($nomor, 0, $posisi));
    }
}
