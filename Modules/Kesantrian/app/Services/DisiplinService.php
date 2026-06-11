<?php

namespace Modules\Kesantrian\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Kesantrian\Models\EskalasiDisiplin;
use Modules\Kesantrian\Models\JenisPelanggaran;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\SanksiSantri;
use Modules\Kesantrian\Models\Santri;
use RuntimeException;

/**
 * WP-09 — Engine disiplin/ta'zir berbasis poin (Blueprint Akademik A-4).
 *
 * Prinsip:
 *  - Akumulasi poin = QUERY per periode (bukan kolom akumulasi — hindari drift).
 *  - Verifikasi 2-lapis ber-saklar (Setting) — pencatat ≠ verifikator (anti bully).
 *  - Eskalasi SP idempoten: unique DB (santri×periode×label) + cek aplikasi.
 *  - Semua ambang/saklar via Setting per tenant dengan parsing TOLERAN
 *    (pelajaran WP-02: nilai string/typo tidak boleh meledak — jatuh ke default).
 *  - Notifikasi wali fire-and-forget via DisiplinNotifikasiService (modul Notifikasi).
 */
class DisiplinService
{
    /** Saklar verifikasi 2-lapis. true (default) → catat berstatus 'dicatat'. */
    public const SETTING_WAJIB_VERIFIKASI = 'disiplin.wajib_verifikasi';

    /** Ambang eskalasi: JSON [{poin:50,label:'SP1'},{poin:100,label:'SP2'}]. */
    public const SETTING_AMBANG = 'disiplin.ambang';

    /** Kebijakan notifikasi wali: semua|eskalasi|berat|mati (default eskalasi). */
    public const SETTING_NOTIF_WALI = 'disiplin.notif_wali';

    /** Saklar poin positif/prestasi (jenis berkategori 'prestasi'). Default true. */
    public const SETTING_POIN_POSITIF = 'disiplin.poin_positif';

    /** Masa berlaku poin: semester|tahunan|akumulasi (default semester). */
    public const SETTING_MASA_BERLAKU = 'disiplin.masa_berlaku_poin';

    public const NOTIF_WALI_OPTIONS = ['semua', 'eskalasi', 'berat', 'mati'];

    public const MASA_BERLAKU_OPTIONS = ['semester', 'tahunan', 'akumulasi'];

    public const DEFAULT_AMBANG = [
        ['poin' => 50, 'label' => 'SP1'],
        ['poin' => 100, 'label' => 'SP2'],
    ];

    public function __construct(private readonly DisiplinNotifikasiService $notifikasi) {}

    // ------------------------------------------------------------------
    // Alur status
    // ------------------------------------------------------------------

    /**
     * Catat pelanggaran/prestasi. Status awal mengikuti saklar verifikasi:
     * wajib_verifikasi true → 'dicatat' (menunggu ustadz); false → langsung
     * 'diverifikasi' (pencatat = verifikator, hook eskalasi langsung jalan).
     *
     * WP-21: $paksaVerifikasiManual=true MEMAKSA status 'dicatat' walau
     * Setting wajib_verifikasi=false — dipakai pencatat non-ustadz (pengurus
     * santri) yang SELALU butuh verifikasi ustadz (anti penyalahgunaan
     * sesama santri). Default false → perilaku lama TIDAK berubah.
     *
     * @param  array{waktu_kejadian?:mixed, lokasi?:?string, kronologi:string, foto_bukti?:?string}  $data
     */
    public function catat(Santri $santri, JenisPelanggaran $jenis, User $pencatat, array $data, bool $paksaVerifikasiManual = false): PelanggaranSantri
    {
        if (! $jenis->aktif) {
            throw new InvalidArgumentException('Jenis pelanggaran tidak aktif.');
        }

        if ($jenis->isPrestasi() && ! $this->poinPositifAktif()) {
            throw new InvalidArgumentException('Pencatatan poin positif/prestasi dimatikan oleh kebijakan pesantren.');
        }

        $wajibVerifikasi = $paksaVerifikasiManual || $this->wajibVerifikasi();

        $pelanggaran = PelanggaranSantri::create([
            'santri_id' => $santri->id,
            'jenis_pelanggaran_id' => $jenis->id,
            'waktu_kejadian' => $data['waktu_kejadian'] ?? now(),
            'lokasi' => $data['lokasi'] ?? null,
            'kronologi' => $data['kronologi'],
            'foto_bukti' => $data['foto_bukti'] ?? null,
            'pencatat_id' => $pencatat->id,
            'status' => $wajibVerifikasi ? 'dicatat' : 'diverifikasi',
            'verifikator_id' => $wajibVerifikasi ? null : $pencatat->id,
            'verified_at' => $wajibVerifikasi ? null : now(),
        ]);

        if (! $wajibVerifikasi) {
            $this->afterVerified($pelanggaran);
        }

        return $pelanggaran;
    }

    /**
     * Verifikasi oleh ustadz/pengasuhan. Hanya dari status 'dicatat';
     * verifikator TIDAK BOLEH = pencatat (tolak self-verify).
     */
    public function verifikasi(PelanggaranSantri $pelanggaran, User $verifikator): PelanggaranSantri
    {
        if ($pelanggaran->status !== 'dicatat') {
            throw new RuntimeException("Hanya pelanggaran berstatus 'dicatat' yang bisa diverifikasi (status saat ini: {$pelanggaran->status}).");
        }

        if ((int) $pelanggaran->pencatat_id === (int) $verifikator->id) {
            throw new RuntimeException('Pencatat tidak boleh memverifikasi catatannya sendiri.');
        }

        $pelanggaran->update([
            'status' => 'diverifikasi',
            'verifikator_id' => $verifikator->id,
            'verified_at' => now(),
        ]);

        $this->afterVerified($pelanggaran);

        return $pelanggaran;
    }

    /** Batalkan (alasan WAJIB). Poin otomatis kembali karena total = query by status. */
    public function batalkan(PelanggaranSantri $pelanggaran, User $pembatal, string $alasan): PelanggaranSantri
    {
        if (trim($alasan) === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        if (in_array($pelanggaran->status, ['selesai', 'dibatalkan'], true)) {
            throw new RuntimeException("Pelanggaran berstatus '{$pelanggaran->status}' tidak bisa dibatalkan.");
        }

        $pelanggaran->update([
            'status' => 'dibatalkan',
            'alasan_batal' => trim($alasan),
        ]);

        return $pelanggaran;
    }

    /** Catat eksekusi sanksi → status pelanggaran menjadi 'sanksi_dijalankan'. */
    public function catatSanksi(PelanggaranSantri $pelanggaran, User $pelaksana, array $data): SanksiSantri
    {
        if (! in_array($pelanggaran->status, ['diverifikasi', 'sanksi_dijalankan'], true)) {
            throw new RuntimeException('Sanksi hanya bisa dicatat untuk pelanggaran terverifikasi.');
        }

        $sanksi = SanksiSantri::create([
            'pelanggaran_id' => $pelanggaran->id,
            'jenis_sanksi' => $data['jenis_sanksi'] ?? ($pelanggaran->jenis?->sanksi_default ?? 'Pembinaan'),
            'pelaksana_id' => $pelaksana->id,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
            'bukti' => $data['bukti'] ?? null,
            'catatan' => $data['catatan'] ?? null,
        ]);

        $pelanggaran->update(['status' => 'sanksi_dijalankan']);

        return $sanksi;
    }

    /** Tandai selesai (sanksi tuntas / kasus ditutup). */
    public function selesaikan(PelanggaranSantri $pelanggaran): PelanggaranSantri
    {
        if (! in_array($pelanggaran->status, ['diverifikasi', 'sanksi_dijalankan'], true)) {
            throw new RuntimeException('Hanya pelanggaran terverifikasi yang bisa diselesaikan.');
        }

        $pelanggaran->update(['status' => 'selesai']);

        return $pelanggaran;
    }

    // ------------------------------------------------------------------
    // Poin & eskalasi
    // ------------------------------------------------------------------

    /**
     * Total poin santri pada periode (default: periode aktif sesuai kebijakan
     * masa berlaku). SELALU query agregat — bukan kolom akumulasi.
     */
    public function totalPoin(Santri $santri, ?CarbonInterface $acuan = null): int
    {
        [$dari, $sampai] = $this->rentangPeriode($acuan);

        $q = PelanggaranSantri::query()
            ->where('santri_id', $santri->id)
            ->whereIn('status', PelanggaranSantri::STATUS_POIN_BERLAKU)
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id');

        if ($dari !== null && $sampai !== null) {
            $q->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        return (int) $q->sum('jenis_pelanggaran.poin');
    }

    /**
     * Kunci periode poin untuk idempotensi eskalasi.
     * semester → '2026-S1'/'2026-S2'; tahunan → '2026'; akumulasi → 'akumulasi'.
     */
    public function periodeKey(?CarbonInterface $acuan = null): string
    {
        $acuan = $acuan ?? now();

        return match ($this->masaBerlaku()) {
            'tahunan' => (string) $acuan->year,
            'akumulasi' => 'akumulasi',
            default => $acuan->year.'-S'.($acuan->month <= 6 ? '1' : '2'),
        };
    }

    /** @return array{0:?CarbonInterface,1:?CarbonInterface} Rentang waktu_kejadian periode aktif (null,null = akumulasi terus). */
    public function rentangPeriode(?CarbonInterface $acuan = null): array
    {
        $acuan = Carbon::parse($acuan ?? now());

        return match ($this->masaBerlaku()) {
            'tahunan' => [$acuan->copy()->startOfYear(), $acuan->copy()->endOfYear()],
            'akumulasi' => [null, null],
            default => $acuan->month <= 6
                ? [$acuan->copy()->startOfYear(), $acuan->copy()->startOfYear()->addMonths(5)->endOfMonth()->endOfDay()]
                : [$acuan->copy()->startOfYear()->addMonths(6)->startOfDay(), $acuan->copy()->endOfYear()],
        };
    }

    /**
     * Cek ambang eskalasi setelah verifikasi. Setiap ambang terlampaui yang
     * BELUM punya record di periode ini → buat EskalasiDisiplin (idempoten:
     * cek aplikasi + unique DB) + notifikasi wali sesuai kebijakan.
     *
     * @return array<int, EskalasiDisiplin> eskalasi BARU yang tercipta
     */
    public function cekEskalasi(Santri $santri, ?CarbonInterface $acuan = null): array
    {
        $total = $this->totalPoin($santri, $acuan);
        $periode = $this->periodeKey($acuan);
        $baru = [];

        foreach ($this->ambang() as $ambang) {
            if ($total < $ambang['poin']) {
                continue;
            }

            $sudahAda = EskalasiDisiplin::query()
                ->where('santri_id', $santri->id)
                ->where('periode', $periode)
                ->where('label', $ambang['label'])
                ->exists();

            if ($sudahAda) {
                continue;
            }

            try {
                $eskalasi = EskalasiDisiplin::create([
                    'santri_id' => $santri->id,
                    'periode' => $periode,
                    'label' => $ambang['label'],
                    'poin' => $total,
                    'tanggal' => now()->toDateString(),
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23505') {
                    continue;
                }
                throw $e;
            }

            $baru[] = $eskalasi;

            if ($this->notifWali() !== 'mati') {
                $this->notifikasi->eskalasi($santri, $eskalasi);
            }
        }

        return $baru;
    }

    // ------------------------------------------------------------------
    // Kebijakan (Setting per tenant — parsing toleran, pelajaran WP-02)
    // ------------------------------------------------------------------

    public function wajibVerifikasi(): bool
    {
        return $this->boolSetting(self::SETTING_WAJIB_VERIFIKASI, true);
    }

    public function poinPositifAktif(): bool
    {
        return $this->boolSetting(self::SETTING_POIN_POSITIF, true);
    }

    /** Kebijakan notifikasi wali — nilai liar jatuh ke default 'eskalasi'. */
    public function notifWali(): string
    {
        $raw = Setting::getTenantValue(self::SETTING_NOTIF_WALI);
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['text'] ?? (count($raw) === 1 ? reset($raw) : null);
        }
        $val = is_string($raw) ? strtolower(trim($raw)) : null;

        return in_array($val, self::NOTIF_WALI_OPTIONS, true) ? $val : 'eskalasi';
    }

    /** Masa berlaku poin — nilai liar jatuh ke default 'semester'. */
    public function masaBerlaku(): string
    {
        $raw = Setting::getTenantValue(self::SETTING_MASA_BERLAKU);
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['text'] ?? (count($raw) === 1 ? reset($raw) : null);
        }
        $val = is_string($raw) ? strtolower(trim($raw)) : null;

        return in_array($val, self::MASA_BERLAKU_OPTIONS, true) ? $val : 'semester';
    }

    /**
     * Ambang eskalasi terurut naik. Entri tidak valid di-skip; bila SEMUA
     * tidak valid / setting kosong → default SP1=50, SP2=100.
     *
     * @return array<int, array{poin:int,label:string}>
     */
    public function ambang(): array
    {
        $raw = Setting::getTenantValue(self::SETTING_AMBANG);

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        $hasil = [];
        if (is_array($raw)) {
            foreach ($raw as $entri) {
                if (! is_array($entri)) {
                    continue;
                }
                $poin = $entri['poin'] ?? null;
                $label = $entri['label'] ?? null;
                if (is_numeric($poin) && (int) $poin > 0 && is_string($label) && trim($label) !== '') {
                    $hasil[] = ['poin' => (int) $poin, 'label' => trim($label)];
                }
            }
        }

        if ($hasil === []) {
            $hasil = self::DEFAULT_AMBANG;
        }

        usort($hasil, fn (array $a, array $b): int => $a['poin'] <=> $b['poin']);

        return $hasil;
    }

    /**
     * Baca Setting boolean dengan parsing TOLERAN: true/false asli,
     * string 'true'/'false'/'1'/'0'/'on'/'off'/'ya'/'tidak', angka,
     * atau array {value: ...}. Nilai tak dikenal → default.
     */
    private function boolSetting(string $key, bool $default): bool
    {
        $raw = Setting::getTenantValue($key);

        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['text'] ?? (count($raw) === 1 ? reset($raw) : null);
        }

        if (is_bool($raw)) {
            return $raw;
        }
        if (is_int($raw) || is_float($raw)) {
            return (int) $raw !== 0;
        }
        if (is_string($raw)) {
            $val = strtolower(trim($raw));
            if (in_array($val, ['true', '1', 'on', 'ya', 'aktif'], true)) {
                return true;
            }
            if (in_array($val, ['false', '0', 'off', 'tidak', 'mati'], true)) {
                return false;
            }
        }

        return $default;
    }

    // ------------------------------------------------------------------

    /** Hook pasca-verifikasi: notifikasi kejadian (kebijakan) + cek eskalasi. */
    private function afterVerified(PelanggaranSantri $pelanggaran): void
    {
        $pelanggaran->loadMissing(['santri', 'jenis']);
        $santri = $pelanggaran->santri;
        $jenis = $pelanggaran->jenis;

        if ($santri === null || $jenis === null) {
            return;
        }

        $notif = $this->notifWali();
        $kirimKejadian = $notif === 'semua'
            || ($notif === 'berat' && $jenis->kategori === 'berat');

        if ($kirimKejadian) {
            $this->notifikasi->kejadian($pelanggaran);
        }

        // Prestasi (poin negatif) tidak pernah memicu eskalasi baru.
        if (! $jenis->isPrestasi()) {
            $this->cekEskalasi($santri, $pelanggaran->waktu_kejadian);
        }
    }
}
