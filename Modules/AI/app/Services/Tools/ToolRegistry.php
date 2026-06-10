<?php

namespace Modules\AI\Services\Tools;

use App\Models\AccessWali;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Modules\AI\Services\Settings\TenantSettingService;
use Modules\AI\Services\Tools\Adapters\AkademikToolAdapter;
use Modules\AI\Services\Tools\Adapters\KegiatanToolAdapter;
use Modules\AI\Services\Tools\Adapters\KesantrianToolAdapter;
use Modules\AI\Services\Tools\Adapters\KeuanganToolAdapter;
use Modules\AI\Services\Tools\Adapters\LedgerToolAdapter;
use Modules\AI\Services\Tools\Adapters\PpdbToolAdapter;
use Modules\AI\Services\Tools\Adapters\TahfidzToolAdapter;
use Throwable;

/**
 * T-A033 — registry tools data terstruktur resmi (READ-ONLY mutlak).
 *
 * Alur call() (urut MURAH → MAHAL, semua kegagalan = ToolResult santun):
 *   a. tool terdaftar?
 *   b. scope_data pengaturan tenant AKTIF mengizinkan scope_key tool?
 *      (tanpa pengaturan aktif = Wizard belum tuntas → ditolak);
 *   c. RBAC peran spatie pemanggil (peran_map config); pemanggil WALI pada
 *      tool berdimensi santri WAJIB tertaut via App\Models\AccessWali —
 *      santri_id divalidasi kepemilikan SEBELUM adapter disentuh;
 *   d. validasi args vs skema (kunci asing ditolak, fail-closed);
 *   e. panggil adapter → ToolResult {sukses, nilai, cap_waktu, sumber}.
 *      Exception adapter → ToolResult gagal santun + log (tidak pernah bocor
 *      stack trace ke pengguna).
 *
 * ATURAN EMAS: registry & adapter TIDAK PERNAH menulis (lihat docblock tiap
 * adapter); jawaban dirender template (ToolResult::keTeks) TANPA LLM (§7.1);
 * tool dipanggil rule-based oleh T-A051 — TIDAK diekspos sebagai
 * function-calling LLM.
 */
class ToolRegistry
{
    /** @var array<string, ToolDefinition> */
    private array $tools = [];

    public function __construct()
    {
        foreach ($this->bawaan() as $def) {
            $this->daftar($def);
        }
    }

    public function daftar(ToolDefinition $def): void
    {
        $this->tools[$def->nama] = $def;
    }

    /** @return array<string, ToolDefinition> */
    public function semua(): array
    {
        return $this->tools;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function call(string $nama, array $args, ?User $pemanggil = null): ToolResult
    {
        // (a) Tool terdaftar?
        $def = $this->tools[$nama] ?? null;

        if ($def === null) {
            return ToolResult::gagal($nama, $this->pesan('tidak_dikenal'));
        }

        // Fail-closed: tool data SELALU butuh identitas pemanggil (RBAC + tautan wali).
        if ($pemanggil === null) {
            return ToolResult::gagal($nama, $this->pesan('tanpa_identitas'));
        }

        // (b) Gerbang scope_data pengaturan tenant aktif.
        if ($keputusan = $this->cekScope($def)) {
            return $keputusan;
        }

        // (c) RBAC peran + pembatasan wali (AccessWali).
        if ($keputusan = $this->cekPeran($def, $args, $pemanggil)) {
            return $keputusan;
        }

        // (d) Validasi argumen vs skema ringan.
        if ($keputusan = $this->validasiArgs($def, $args)) {
            return $keputusan;
        }

        // (e) Adapter — resolusi container (constructor injection service resmi).
        try {
            /** @var array{0: array<string, mixed>, 1: string} $hasil */
            $hasil = app($def->adapterClass)->{$def->adapterMethod}($args, $pemanggil);

            return ToolResult::ok($def->nama, $hasil[0], $hasil[1]);
        } catch (ModelNotFoundException) {
            return ToolResult::gagal($def->nama, $this->pesan('data_tidak_ditemukan'));
        } catch (QueryException $e) {
            // Tabel modul sumber tidak ada/modul nonaktif di tenant ini —
            // toleran: jawab santun, jangan meledak.
            Log::warning('ToolRegistry: adapter gagal query — modul sumber kemungkinan tidak aktif.', [
                'tool' => $def->nama,
                'adapter' => $def->adapterClass,
                'message' => $e->getMessage(),
            ]);

            return ToolResult::gagal($def->nama, $this->pesan('modul_tidak_aktif'));
        } catch (Throwable $e) {
            Log::error('ToolRegistry: adapter melempar exception.', [
                'tool' => $def->nama,
                'adapter' => $def->adapterClass,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ToolResult::gagal($def->nama, $this->pesan('gagal_internal'));
        }
    }

    // ------------------------------------------------------------------
    // Gerbang
    // ------------------------------------------------------------------

    private function cekScope(ToolDefinition $def): ?ToolResult
    {
        $peta = (array) config('ai.tools.scope_map', []);
        $scopeKey = array_key_exists($def->nama, $peta) ? $peta[$def->nama] : $def->scopeKey;

        if ($scopeKey === null) {
            return null; // tool tanpa dimensi scope (mis. status PPDB pendaftar sendiri)
        }

        // Resolusi FRESH per panggilan (bukan constructor injection) — alasan
        // sama dengan PolicyEngine: memo per-request bisa basi pada worker
        // berumur panjang / pergantian konteks tenant.
        $setting = app(TenantSettingService::class)->aktif();

        if ($setting === null) {
            return ToolResult::gagal($def->nama, $this->pesan('belum_dikonfigurasi'));
        }

        $scopeData = (array) (((array) $setting->payload)['scope_data'] ?? []);

        if (($scopeData[$scopeKey] ?? false) !== true) {
            return ToolResult::gagal($def->nama, $this->pesan('scope_nonaktif'));
        }

        return null;
    }

    /** @param  array<string, mixed>  $args */
    private function cekPeran(ToolDefinition $def, array $args, User $pemanggil): ?ToolResult
    {
        $peta = (array) config('ai.tools.peran_map', []);
        $diizinkan = (array) ($peta[$def->nama] ?? $def->peran);

        $peranUser = method_exists($pemanggil, 'getRoleNames')
            ? $pemanggil->getRoleNames()->all()
            : [];

        $cocok = array_values(array_intersect($peranUser, $diizinkan));

        if ($cocok === []) {
            return ToolResult::gagal($def->nama, $this->pesan('peran_tidak_diizinkan'));
        }

        // Pemanggil yang HANYA lolos lewat peran 'wali' (tanpa peran staf lain
        // yang diizinkan) = terikat anak tertaut pada tool berdimensi santri.
        if ($def->terikatSantri && $cocok === ['wali']) {
            $tertaut = AccessWali::query()
                ->where('wali_id', $pemanggil->getKey())
                ->pluck('santri_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $santriId = $args['santri_id'] ?? null;

            if ($tertaut === [] || ! is_numeric($santriId) || ! in_array((int) $santriId, $tertaut, true)) {
                return ToolResult::gagal($def->nama, $this->pesan('bukan_anak_tertaut'));
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $args */
    private function validasiArgs(ToolDefinition $def, array $args): ?ToolResult
    {
        $asing = array_diff(array_keys($args), array_keys($def->skema));

        if ($asing !== []) {
            return ToolResult::gagal(
                $def->nama,
                $this->pesan('args_invalid').' (parameter tidak dikenal: '.implode(', ', $asing).')',
            );
        }

        foreach ($def->skema as $param => $spec) {
            $wajib = (bool) ($spec['required'] ?? false);
            $ada = array_key_exists($param, $args) && $args[$param] !== null && $args[$param] !== '';

            if (! $ada) {
                if ($wajib) {
                    return ToolResult::gagal(
                        $def->nama,
                        $this->pesan('args_invalid')." (parameter '{$param}' wajib diisi)",
                    );
                }

                continue;
            }

            $nilai = $args[$param];
            $valid = match ((string) ($spec['type'] ?? 'string')) {
                'integer' => is_int($nilai) || (is_string($nilai) && ctype_digit($nilai)),
                'date' => is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) === 1,
                default => is_string($nilai),
            };

            if ($valid && isset($spec['in']) && ! in_array($nilai, (array) $spec['in'], true)) {
                $valid = false;
            }

            if (! $valid) {
                return ToolResult::gagal(
                    $def->nama,
                    $this->pesan('args_invalid')." (parameter '{$param}' tidak sesuai format)",
                );
            }
        }

        return null;
    }

    private function pesan(string $kunci): string
    {
        return (string) config(
            "ai.tools.pesan.{$kunci}",
            'Mohon maaf, permintaan data tidak dapat diproses. Silakan hubungi admin pesantren.',
        );
    }

    // ------------------------------------------------------------------
    // Katalog bawaan (Blueprint §6 — peta titik integrasi resmi)
    // ------------------------------------------------------------------

    /** @return list<ToolDefinition> */
    private function bawaan(): array
    {
        $santriId = ['santri_id' => ['type' => 'integer', 'required' => true, 'deskripsi' => 'ID santri']];

        return [
            new ToolDefinition(
                nama: 'getTagihan',
                deskripsi: 'Daftar tagihan yang belum lunas milik seorang santri.',
                skema: $santriId,
                scopeKey: 'tagihan',
                peran: ['admin_pesantren', 'tu', 'keuangan', 'bendahara', 'bendahara_pusat', 'kepala', 'wali'],
                adapterClass: KeuanganToolAdapter::class,
                adapterMethod: 'getTagihan',
                terikatSantri: true,
            ),
            new ToolDefinition(
                nama: 'getStatusBayar',
                deskripsi: 'Ringkasan status pembayaran tagihan seorang santri.',
                skema: $santriId,
                scopeKey: 'tagihan',
                peran: ['admin_pesantren', 'tu', 'keuangan', 'bendahara', 'bendahara_pusat', 'kepala', 'wali'],
                adapterClass: KeuanganToolAdapter::class,
                adapterMethod: 'getStatusBayar',
                terikatSantri: true,
            ),
            new ToolDefinition(
                nama: 'getRingkasanKeuangan',
                deskripsi: 'Ringkasan neraca, laba-rugi, dan arus kas (hanya jurnal posted).',
                skema: [
                    'dari' => ['type' => 'date', 'required' => false, 'deskripsi' => 'Tanggal awal (Y-m-d)'],
                    'sampai' => ['type' => 'date', 'required' => false, 'deskripsi' => 'Tanggal akhir (Y-m-d)'],
                ],
                scopeKey: 'keuangan',
                peran: ['admin_pesantren', 'keuangan', 'bendahara', 'bendahara_pusat', 'kepala_keuangan', 'kepala', 'dewan_pembina'],
                adapterClass: LedgerToolAdapter::class,
                adapterMethod: 'getRingkasanKeuangan',
            ),
            new ToolDefinition(
                nama: 'getJadwal',
                deskripsi: 'Jadwal pelajaran sebuah kelas (opsional difilter hari).',
                skema: [
                    'kelas_id' => ['type' => 'integer', 'required' => true, 'deskripsi' => 'ID kelas'],
                    'hari' => ['type' => 'string', 'required' => false, 'deskripsi' => 'Nama hari'],
                ],
                scopeKey: 'jadwal',
                peran: ['admin_pesantren', 'tu', 'guru', 'asatidz', 'kepala', 'wali', 'santri'],
                adapterClass: AkademikToolAdapter::class,
                adapterMethod: 'getJadwal',
            ),
            new ToolDefinition(
                nama: 'getNilai',
                deskripsi: 'Nilai akademik seorang santri (opsional per periode).',
                skema: $santriId + [
                    'periode_akademik_id' => ['type' => 'integer', 'required' => false, 'deskripsi' => 'ID periode akademik'],
                ],
                scopeKey: 'nilai',
                peran: ['admin_pesantren', 'tu', 'guru', 'kepala', 'wali'],
                adapterClass: AkademikToolAdapter::class,
                adapterMethod: 'getNilai',
                terikatSantri: true,
            ),
            new ToolDefinition(
                nama: 'getTahfidz',
                deskripsi: 'Ringkasan perkembangan hafalan tahfidz seorang santri.',
                skema: $santriId,
                scopeKey: 'nilai',
                peran: ['admin_pesantren', 'guru_tahfidz', 'guru', 'kepala', 'wali'],
                adapterClass: TahfidzToolAdapter::class,
                adapterMethod: 'getTahfidz',
                terikatSantri: true,
            ),
            new ToolDefinition(
                nama: 'getKehadiran',
                deskripsi: 'Rekap kehadiran kegiatan seorang santri.',
                skema: $santriId,
                scopeKey: 'kehadiran',
                peran: ['admin_pesantren', 'tu', 'pengelola_asrama', 'kepala', 'wali'],
                adapterClass: KegiatanToolAdapter::class,
                adapterMethod: 'getKehadiran',
                terikatSantri: true,
            ),
            new ToolDefinition(
                nama: 'getIzin',
                deskripsi: 'Catatan izin terakhir seorang santri.',
                skema: $santriId,
                scopeKey: 'kehadiran',
                peran: ['admin_pesantren', 'tu', 'pengelola_asrama', 'kepala', 'wali'],
                adapterClass: KegiatanToolAdapter::class,
                adapterMethod: 'getIzin',
                terikatSantri: true,
            ),
            new ToolDefinition(
                nama: 'getStatusPPDB',
                deskripsi: 'Status pendaftaran PPDB berdasarkan nomor pendaftaran.',
                skema: [
                    'nomor_pendaftaran' => ['type' => 'string', 'required' => true, 'deskripsi' => 'Nomor pendaftaran calon santri'],
                ],
                scopeKey: null, // status pendaftaran milik pemohon — tanpa dimensi scope_data Wizard
                peran: ['admin_pesantren', 'tu', 'kepala', 'peserta'],
                adapterClass: PpdbToolAdapter::class,
                adapterMethod: 'getStatusPPDB',
            ),
            new ToolDefinition(
                nama: 'getSantri',
                deskripsi: 'Data ringkas santri — KHUSUS wali, hanya anak tertaut.',
                skema: $santriId,
                scopeKey: null, // profil dasar anak sendiri; pagar = AccessWali, bukan scope_data
                peran: ['wali'],
                adapterClass: KesantrianToolAdapter::class,
                adapterMethod: 'getSantri',
                terikatSantri: true,
            ),
        ];
    }
}
