<?php

namespace Modules\PPDB\Services;

use App\Models\Setting;
use App\Support\PhoneNumber;
use App\Support\TenantCustomizationRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use Modules\Notifikasi\Models\BroadcastWa;
use Modules\Notifikasi\Models\TemplateNotifikasi;
use Modules\Notifikasi\Services\BroadcastWaService;
use Modules\Notifikasi\Services\WaGatewayService;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\TagihanPpdb;

class AlbaKidsRegistrationNotificationService
{
    /** Footer otomatis: WA dikirim dari nomor perusahaan, bukan atas nama pesantren. */
    private const FOOTER = "\n\n_Pesan otomatis ini dikirim dari nomor perusahaan PT Rojo Brontolano._";

    public function __construct(
        private readonly BroadcastWaService $broadcastWaService,
        private readonly WaGatewayService $waGateway,
    ) {}

    /**
     * Prefix kode template dari registry (kunci tahap 2 'notif_template_prefix'
     * — sama dgn filter BroadcastWa/TemplateNotifikasi resource, sehingga
     * template yang dibuat di sini otomatis tampil di resource tenant tsb).
     * Fallback 'albakids_' = nilai legacy kompat data produksi (kode template
     * lama di DB tenant); migrasi kode = keputusan owner (inventaris #10).
     * (tahap 5 #343)
     */
    private function templatePrefix(): string
    {
        return TenantCustomizationRegistry::stringValue('notif_template_prefix') ?? 'albakids_';
    }

    /**
     * Kirim semua notifikasi pendaftaran: ucapan terima kasih ke orang tua +
     * ringkasan & lampiran dokumen ke admin.
     *
     * @return array{guardian: array<int, BroadcastWa>, admin: ?BroadcastWa}
     */
    public function notify(CalonSantri $candidate, array $context = []): array
    {
        // Pastikan template broadcast umum tersedia (dipakai fitur broadcast manual).
        $this->resolveTemplate(
            $this->templatePrefix().'broadcast_general',
            'Broadcast Alba Kids',
            'Assalamu alaikum Bapak/Ibu, {{isi_pesan}}'
        );

        return [
            'guardian' => $this->notifyGuardians($candidate, $context),
            'admin' => $this->notifyAdmin($candidate, $context),
        ];
    }

    /**
     * #1/#3 — Ucapan terima kasih ke orang tua. Disapa personal per peran
     * (Bapak {Ayah} / Ibu {Ibu}); bila nomor sama atau hanya satu → kirim sekali.
     *
     * @return array<int, BroadcastWa>
     */
    public function notifyGuardians(CalonSantri $candidate, array $context = []): array
    {
        $template = $this->guardianTemplate();
        $pesanTambahan = trim((string) ($context['success_message'] ?? $this->defaultSuccessMessage()));

        $sent = [];
        foreach ($this->guardianRecipients($candidate) as $phone => $salam) {
            $broadcast = $this->broadcastWaService->create([
                'template_id' => $template->id,
                'target_group' => 'custom',
                'filter_criteria' => [
                    'targets' => [$phone],
                    'variables' => [
                        'salam' => $salam,
                        'nama_wali' => $salam,
                        'nama_calon' => $candidate->nama,
                        'nomor_pendaftaran' => $candidate->nomor_pendaftaran,
                        'program_diminati' => $candidate->program_diminati,
                        'no_hp_wali' => $candidate->no_hp_wali,
                        'pesan_tambahan' => $pesanTambahan,
                    ],
                ],
                'status' => 'draft',
            ]);
            $this->broadcastWaService->execute($broadcast);
            $sent[] = $broadcast->fresh(['logs']);
        }

        return $sent;
    }

    /**
     * #2 — Ringkasan pendaftar + lampiran dokumen (Bukti/KK/Akta) ke admin panitia.
     */
    public function notifyAdmin(CalonSantri $candidate, array $context = []): ?BroadcastWa
    {
        $adminPhone = $this->normalizePhone(
            (string) ($context['admin_whatsapp'] ?? $this->defaultAdminPhone()),
            false
        );

        if ($adminPhone === null) {
            return null;
        }

        $template = $this->adminTemplate();
        $broadcast = $this->broadcastWaService->create([
            'template_id' => $template->id,
            'target_group' => 'custom',
            'filter_criteria' => [
                'targets' => [$adminPhone],
                'variables' => [
                    'nama_calon' => $candidate->nama,
                    'ringkasan' => $this->buildAdminSummary($candidate),
                    'dokumen' => $this->buildDocumentLinks($candidate),
                ],
            ],
            'status' => 'draft',
        ]);
        $this->broadcastWaService->execute($broadcast);

        // #2: lampirkan FILE dokumen ke admin sebagai media WA (lengkapi teks + link).
        $this->sendAdminDocuments($candidate, $adminPhone);

        return $broadcast->fresh(['logs']);
    }

    private function guardianTemplate(): TemplateNotifikasi
    {
        return $this->resolveTemplate(
            $this->templatePrefix().'registration_received',
            'Pendaftaran Alba Kids diterima',
            'Assalamu alaikum {{salam}}, terima kasih sudah mendaftarkan {{nama_calon}} di Alba Kids Pre-School. Nomor pendaftaran: {{nomor_pendaftaran}}. Bukti transfer sudah kami terima dan sedang diverifikasi. {{pesan_tambahan}}'.self::FOOTER
        );
    }

    private function adminTemplate(): TemplateNotifikasi
    {
        return $this->resolveTemplate(
            $this->templatePrefix().'admin_new_registration',
            'Pendaftar baru Alba Kids',
            "Pendaftar baru Alba Kids masuk.\n\n{{ringkasan}}\n\nDokumen (file terlampir menyusul; link cadangan):\n{{dokumen}}\n\nSegera verifikasi di panel admin.".self::FOOTER
        );
    }

    private function defaultAdminPhone(): string
    {
        return (string) (Setting::getTenantStringValue('admission_admin_whatsapp', '+6282260901399') ?? '+6282260901399');
    }

    private function defaultSuccessMessage(): string
    {
        return (string) (Setting::getTenantStringValue('admission_success_message', 'Panitia akan segera menghubungi Anda melalui WhatsApp.') ?? 'Panitia akan segera menghubungi Anda melalui WhatsApp.');
    }

    private function resolveTemplate(string $code, string $title, string $body): TemplateNotifikasi
    {
        return TemplateNotifikasi::query()->updateOrCreate(
            ['kode_template' => $code],
            [
                'judul' => $title,
                'isi' => $body,
                'variable_dinamis' => [
                    'salam',
                    'nama_wali',
                    'nama_calon',
                    'nomor_pendaftaran',
                    'program_diminati',
                    'no_hp_wali',
                    'nama_ayah',
                    'no_hp_ayah',
                    'nama_ibu',
                    'no_hp_ibu',
                    'alamat',
                    'dokumen',
                    'ringkasan',
                    'pesan_tambahan',
                    'isi_pesan',
                ],
                'channel' => 'wa',
                'is_active' => true,
            ]
        );
    }

    /**
     * Bangun daftar link unduh dokumen (bukti/kk/akta) sebagai signed URL,
     * untuk dilampirkan pada notifikasi WhatsApp admin.
     */
    private function buildDocumentLinks(CalonSantri $candidate): string
    {
        $docs = ['bukti' => 'Bukti Transfer'];
        if (data_get($candidate->data_ortu, 'dokumen.kk')) {
            $docs['kk'] = 'Kartu Keluarga';
        }
        if (data_get($candidate->data_ortu, 'dokumen.akta')) {
            $docs['akta'] = 'Akta Kelahiran';
        }

        $lines = [];
        foreach ($docs as $type => $label) {
            try {
                $url = URL::temporarySignedRoute('tenant.ppdb.signed-doc', now()->addDays(30), [
                    'candidate' => $candidate->getKey(),
                    'type' => $type,
                ]);
                $lines[] = "{$label}: {$url}";
            } catch (\Throwable $e) {
                // Route belum tersedia → lewati link, jangan gagalkan notifikasi.
            }
        }

        return $lines === [] ? '-' : implode("\n", $lines);
    }

    /**
     * Ringkasan LENGKAP data pendaftar untuk admin: semua isian formulir +
     * detail pembayaran (untuk verifikasi). Field kosong ditandai "-".
     */
    private function buildAdminSummary(CalonSantri $candidate): string
    {
        $d = $candidate->data_ortu ?? [];

        $val = static function ($v): string {
            $v = is_string($v) ? trim($v) : $v;

            return ($v === null || $v === '') ? '-' : (string) $v;
        };
        $date = static function ($v): string {
            if ($v === null || $v === '') {
                return '-';
            }
            try {
                return Carbon::parse($v)->translatedFormat('d F Y');
            } catch (\Throwable $e) {
                return (string) $v;
            }
        };

        $jenisKelamin = match ((string) $candidate->jenis_kelamin) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '-',
        };

        $lines = [
            'Nama: '.$val($candidate->nama),
            'Jenis Kelamin: '.$jenisKelamin,
            'Anak ke-: '.$val(data_get($d, 'child_order')),
            'Tempat Lahir: '.$val($candidate->tempat_lahir),
            'Tanggal Lahir: '.$date($candidate->tanggal_lahir),
            '',
            'Nomor: '.$val($candidate->nomor_pendaftaran),
            'Program: '.$val($candidate->program_diminati),
            'Kelas: '.$val(data_get($d, 'kelas_tujuan')),
            '',
            'Ayah: '.$val($candidate->nama_ayah),
            'Pekerjaan: '.$val(data_get($d, 'pekerjaan_ayah')),
            'No. WhatsApp Ayah: '.$val(data_get($d, 'no_hp_ayah')),
            '',
            'Ibu: '.$val($candidate->nama_ibu),
            'Pekerjaan: '.$val(data_get($d, 'pekerjaan_ibu')),
            'No. WhatsApp Ibu: '.$val(data_get($d, 'no_hp_ibu')),
        ];

        // Wali hanya bila diisi terpisah (bukan sekadar duplikat Ayah/Ibu).
        $namaWali = trim((string) data_get($d, 'nama_wali'));
        if ($namaWali !== '' && $namaWali !== trim((string) $candidate->nama_ayah) && $namaWali !== trim((string) $candidate->nama_ibu)) {
            $lines[] = '';
            $lines[] = 'Wali: '.$namaWali.' ('.$val(data_get($d, 'hubungan_wali')).')';
            $lines[] = 'No. WhatsApp Wali: '.$val($candidate->no_hp_wali);
        }

        $lines[] = '';
        $lines[] = 'Alamat Lengkap: '.$val($candidate->alamat);

        $bayar = data_get($d, 'pembayaran', []);
        $nominal = data_get($bayar, 'nominal_transfer');
        $lines[] = '';
        $lines[] = 'Pembayaran:';
        $lines[] = '- Pengirim: '.$val(data_get($bayar, 'nama_pengirim_transfer'));
        $lines[] = '- Bank: '.$val(data_get($bayar, 'bank_pengirim'));
        $lines[] = '- Nominal: '.(is_numeric($nominal) ? 'Rp '.number_format((float) $nominal, 0, ',', '.') : '-');
        $lines[] = '- Tanggal: '.$date(data_get($bayar, 'tanggal_transfer'));

        return implode("\n", $lines);
    }

    /**
     * Kirim file dokumen (bukti/KK/akta) ke admin sebagai lampiran WhatsApp.
     * Per-file gagal tidak menggagalkan pendaftaran — teks + link cadangan sudah terkirim.
     */
    private function sendAdminDocuments(CalonSantri $candidate, string $adminPhone): void
    {
        $disk = config('filesystems.default', 'local');
        $caption = sprintf('%s — %s', $candidate->nama, $candidate->nomor_pendaftaran);

        foreach ($this->collectDocuments($candidate) as $label => $path) {
            try {
                if (! Storage::disk($disk)->exists($path)) {
                    continue;
                }

                $contents = Storage::disk($disk)->get($path);
                if ($contents === null || $contents === '') {
                    continue;
                }

                $this->waGateway->sendFile(
                    $adminPhone,
                    [
                        'data' => base64_encode($contents),
                        'mimetype' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
                        'filename' => basename($path),
                    ],
                    $label.' — '.$caption,
                );
            } catch (\Throwable $e) {
                // Lewati lampiran yang gagal; jangan ganggu alur utama.
            }
        }
    }

    /**
     * Peta dokumen pendaftaran: label tampil => path di storage.
     *
     * @return array<string, string>
     */
    private function collectDocuments(CalonSantri $candidate): array
    {
        $docs = [];

        $bukti = TagihanPpdb::query()
            ->where('calon_santri_id', $candidate->getKey())
            ->whereNotNull('bukti_transfer')
            ->latest('id')
            ->value('bukti_transfer');
        if (is_string($bukti) && $bukti !== '') {
            $docs['Bukti Transfer'] = $bukti;
        }

        if (($kk = data_get($candidate->data_ortu, 'dokumen.kk')) && is_string($kk)) {
            $docs['Kartu Keluarga'] = $kk;
        }
        if (($akta = data_get($candidate->data_ortu, 'dokumen.akta')) && is_string($akta)) {
            $docs['Akta Kelahiran'] = $akta;
        }

        return $docs;
    }

    /**
     * Penerima ucapan terima kasih: [nomor => sapaan]. Ayah & Ibu disapa sesuai
     * peran; nomor wali dipakai bila berbeda. Dedup: nomor sama → satu kiriman
     * (urutan Ayah → Ibu → Wali menentukan sapaan yang dipakai).
     *
     * @return array<string, string>
     */
    private function guardianRecipients(CalonSantri $candidate): array
    {
        $ayah = trim((string) ($candidate->nama_ayah ?? ''));
        $ibu = trim((string) ($candidate->nama_ibu ?? ''));
        $namaWali = trim((string) (data_get($candidate->data_ortu, 'nama_wali') ?: ($ayah ?: $ibu)));

        $candidates = [
            [data_get($candidate->data_ortu, 'no_hp_ayah'), $ayah !== '' ? 'Bapak '.$ayah : 'Bapak/Ibu Wali'],
            [data_get($candidate->data_ortu, 'no_hp_ibu'), $ibu !== '' ? 'Ibu '.$ibu : 'Bapak/Ibu Wali'],
            [$candidate->no_hp_wali, $namaWali !== '' ? 'Bapak/Ibu '.$namaWali : 'Bapak/Ibu Wali'],
        ];

        $recipients = [];
        foreach ($candidates as [$rawPhone, $salam]) {
            $phone = $this->normalizePhone((string) $rawPhone, false);
            if ($phone === null || isset($recipients[$phone])) {
                continue;
            }
            $recipients[$phone] = $salam;
        }

        return $recipients;
    }

    private function normalizePhone(?string $value, bool $required = true): ?string
    {
        // Delegasi ke sumber kebenaran tunggal (idempoten, anti country-code ganda).
        $phone = PhoneNumber::normalize($value);

        if ($phone === '') {
            if ($required) {
                throw new InvalidArgumentException('Nomor WhatsApp tujuan notifikasi wajib diisi.');
            }

            return null;
        }

        return $phone;
    }
}
