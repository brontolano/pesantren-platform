<?php

namespace Modules\PPDB\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\StorageQuotaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PPDB\Models\CalonSantri;
use Modules\PPDB\Models\JadwalUjian;
use Modules\PPDB\Services\DaftarUlangService;
use Modules\PPDB\Services\PpdbDocumentUploadService;
use Modules\PPDB\Services\PpdbFormFieldConfigurationService;
use Modules\PPDB\Services\PpdbStatusTrackingService;
use Modules\PPDB\Support\PpdbBiodataFields;
use Modules\PPDB\Support\PpdbDocumentTypes;

class PesertaPortalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PpdbFormFieldConfigurationService $fieldConfigurationService,
        private readonly PpdbStatusTrackingService $statusTrackingService,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());
        $fieldConfiguration = $this->fieldConfigurationService->getConfiguration();
        $tracking = $this->statusTrackingService->issueTrackingLink($calon);

        return Inertia::render('PPDB/Portal/Dashboard', [
            'candidate' => $this->buildCandidatePayload($calon, $fieldConfiguration),
            'fieldConfiguration' => $fieldConfiguration,
            'biodataSections' => PpdbBiodataFields::SECTIONS,
            'statusCard' => $this->buildStatusCard($calon, $tracking['tracking_url']),
            'stepper' => $this->buildStepper($calon),
            'checklist' => $this->buildChecklist($calon, $fieldConfiguration),
            'mainAction' => $this->buildMainAction($calon, $fieldConfiguration),
            'tracking' => [
                'url' => $tracking['tracking_url'],
                'path' => $tracking['tracking_path'],
                'token' => $tracking['tracking_token'],
            ],
            'bolehDaftarUlang' => (string) $calon->status_pendaftaran === 'lulus',
            'timeline' => $this->buildTimeline($calon),
        ]);
    }

    /**
     * DASH-3 — timeline riwayat aktivitas peserta (event nyata bertanggal,
     * urut terbaru dulu). Melengkapi stepper (status) dengan jejak aksi.
     *
     * @return array<int, array{tanggal:string, label:string}>
     */
    private function buildTimeline(CalonSantri $calon): array
    {
        $items = collect([[
            'tanggal' => $calon->created_at,
            'label' => 'Pendaftaran diterima sistem',
        ]]);

        foreach ($calon->dokumen()->get() as $doc) {
            $label = PpdbDocumentTypes::label((string) $doc->jenis_dokumen);
            $items->push(['tanggal' => $doc->created_at, 'label' => "Unggah dokumen: {$label}"]);
            if ($doc->verified_at) {
                $items->push(['tanggal' => $doc->verified_at, 'label' => "Dokumen {$label} diverifikasi"]);
            }
        }

        foreach ($calon->tagihanPpdb()->get() as $tagihan) {
            if ($tagihan->bukti_diunggah_at) {
                $items->push(['tanggal' => $tagihan->bukti_diunggah_at, 'label' => 'Unggah bukti pembayaran']);
            }
            if ((string) $tagihan->status === 'lunas') {
                $items->push(['tanggal' => $tagihan->updated_at, 'label' => 'Pembayaran terverifikasi (lunas)']);
            }
        }

        if ($calon->tanggal_registrasi_ulang) {
            $items->push(['tanggal' => $calon->tanggal_registrasi_ulang, 'label' => 'Daftar ulang selesai']);
        }

        return $items
            ->filter(fn (array $i) => $i['tanggal'] !== null)
            ->sortByDesc('tanggal')
            ->map(fn (array $i) => [
                'tanggal' => $i['tanggal']->timezone(config('app.timezone'))->format('d M Y H:i'),
                'label' => $i['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * Halaman Berkas — checklist kelengkapan dokumen peserta (F2/DOC-4).
     * Hanya-baca: menampilkan tipe dokumen wajib/opsional + status verifikasi
     * tiap dokumen yang sudah diunggah. (Aksi unggah ditangani alur upload tim.)
     */
    public function berkas(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());
        $uploaded = $calon->dokumen()->get()->keyBy('jenis_dokumen');

        $items = [];
        foreach (PpdbDocumentTypes::all() as $jenis => $meta) {
            $doc = $uploaded->get($jenis);

            $items[] = [
                'id' => $doc?->id,
                'jenis' => $jenis,
                'label' => $meta['label'],
                'wajib' => $meta['required'],
                'terunggah' => $doc !== null,
                'status' => $doc?->effectiveStatus(),
                'statusLabel' => $doc?->statusLabel(),
                'catatan' => $doc?->catatan_verifikasi,
            ];
        }

        $kurangWajib = PpdbDocumentTypes::missingRequired($uploaded->keys()->all());

        return Inertia::render('PPDB/Portal/Berkas', [
            'nomorPendaftaran' => (string) $calon->nomor_pendaftaran,
            'items' => $items,
            'kurangWajib' => array_map(
                static fn (string $k): string => PpdbDocumentTypes::label($k),
                $kurangWajib,
            ),
            'lengkapWajib' => $kurangWajib === [],
        ]);
    }

    /**
     * Unggah satu dokumen peserta (F2/DOC-1). Validasi jenis + file (≤5MB,
     * pdf/jpg/png/webp) lalu simpan via PpdbDocumentUploadService (status awal
     * 'pending' utk verifikasi panitia). Dokumen lama jenis sama dihapus agar
     * checklist menampilkan unggahan terbaru saja.
     */
    public function uploadDokumen(Request $request, PpdbDocumentUploadService $uploader): RedirectResponse
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        $validated = $request->validate([
            'jenis_dokumen' => ['required', 'string', Rule::in(PpdbDocumentTypes::keys())],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Batas kuota tenant: cegah over-penyimpanan di server.
        if ($over = $this->kuotaTerlampaui((int) $request->file('file')->getSize())) {
            return back()->withErrors(['file' => $over]);
        }

        // Ganti dokumen jenis sama: buang baris lama (hindari duplikat di checklist).
        $calon->dokumen()
            ->where('jenis_dokumen', $validated['jenis_dokumen'])
            ->get()
            ->each(static fn ($doc) => $doc->delete());

        try {
            $uploader->upload($calon, $validated['jenis_dokumen'], $request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // Notif WA admin (NOTIF-1): berkas baru menunggu verifikasi. Fire-and-forget.
        app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('berkas_ppdb_masuk', [
            'wa' => array_filter([\App\Models\Setting::getTenantStringValue('admission_admin_whatsapp')]),
            'data' => [
                'jenis' => 'Dokumen '.PpdbDocumentTypes::label($validated['jenis_dokumen']),
                'nama_calon' => $calon->nama_lengkap ?? $calon->nama ?? '-',
                'nama_pesantren' => \App\Models\Setting::getTenantStringValue('branding_name') ?? (tenant('name') ?? 'Pesantren'),
            ],
        ]);

        return back()->with('success', 'Dokumen '.PpdbDocumentTypes::label($validated['jenis_dokumen']).' berhasil diunggah.');
    }

    /**
     * Halaman Pengumuman hasil seleksi (F3/ANN). Hanya-baca: menampilkan
     * keputusan (lulus/cadangan/belum lulus) bila sudah dipublikasikan, atau
     * status proses bila belum. Memakai interpretasi status yang sama dgn dashboard.
     */
    public function pengumuman(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());
        $status = (string) $calon->status_pendaftaran;

        $keputusan = match ($status) {
            'lulus' => 'lulus',
            'cadangan' => 'cadangan',
            'ditolak' => 'ditolak',
            default => null,
        };

        return Inertia::render('PPDB/Portal/Pengumuman', [
            'nomorPendaftaran' => (string) $calon->nomor_pendaftaran,
            'nama' => (string) $calon->nama,
            'sudahAda' => $keputusan !== null,
            'keputusan' => $keputusan,
            'badge' => $this->humanizeStatus($status),
            'title' => $this->statusTitle($status),
            'description' => $this->statusDescription($status),
        ]);
    }

    /**
     * Halaman Tagihan PPDB (F2/BILL) — hanya-baca. Menampilkan daftar tagihan
     * peserta (TagihanPpdb) + ringkasan total/lunas/sisa. Aksi bayar & unggah
     * bukti ditangani alur pembayaran tim.
     */
    public function tagihan(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());
        $tagihan = $calon->tagihanPpdb()->orderBy('jatuh_tempo')->get();

        $items = $tagihan->map(static fn ($t): array => [
            'id' => $t->id,
            'tipe' => (string) $t->tipe,
            'nominal' => (float) $t->nominal,
            'jatuhTempo' => $t->jatuh_tempo?->format('Y-m-d'),
            'status' => (string) $t->status,
            'keterangan' => (string) ($t->keterangan ?? ''),
            'lunas' => $t->status === 'lunas',
            'buktiAda' => $t->bukti_transfer !== null,
        ])->values()->all();

        $totalTagihan = (float) $tagihan->sum('nominal');
        $totalLunas = (float) $tagihan->where('status', 'lunas')->sum('nominal');

        return Inertia::render('PPDB/Portal/Tagihan', [
            'nomorPendaftaran' => (string) $calon->nomor_pendaftaran,
            'items' => $items,
            'totalTagihan' => $totalTagihan,
            'totalLunas' => $totalLunas,
            'sisa' => max(0, $totalTagihan - $totalLunas),
        ]);
    }

    /**
     * Unggah bukti transfer untuk satu tagihan PPDB (F2/BILL-2). Kepemilikan
     * ditegakkan via relasi calon (tagihan harus milik peserta ybs). File ≤5MB.
     * Status tagihan → 'menunggu_verifikasi' (antrean verifikasi panitia).
     */
    public function uploadBukti(Request $request, int $tagihan): RedirectResponse
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        // firstOrFail pada relasi = jaminan kepemilikan + isolasi tenant.
        $t = $calon->tagihanPpdb()->whereKey($tagihan)->firstOrFail();

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($over = $this->kuotaTerlampaui((int) $request->file('file')->getSize())) {
            return back()->withErrors(['file' => $over]);
        }

        $disk = config('filesystems.default', 'local');
        $path = $request->file('file')->store(sprintf('ppdb-bukti/%d', $calon->id), $disk);

        $t->update([
            'bukti_transfer' => $path,
            'bukti_diunggah_at' => now(),
            'status' => 'menunggu_verifikasi',
        ]);

        // Notif WA admin (NOTIF-1/BILL-3): bukti tagihan PPDB baru. Fire-and-forget.
        app(\Modules\Notifikasi\Services\NotifDispatcher::class)->kirim('berkas_ppdb_masuk', [
            'wa' => array_filter([\App\Models\Setting::getTenantStringValue('admission_admin_whatsapp')]),
            'data' => [
                'jenis' => 'Bukti pembayaran tagihan PPDB',
                'nama_calon' => $calon->nama_lengkap ?? $calon->nama ?? '-',
                'nama_pesantren' => \App\Models\Setting::getTenantStringValue('branding_name') ?? (tenant('name') ?? 'Pesantren'),
            ],
        ]);

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Menunggu verifikasi panitia.');
    }

    /**
     * Kartu peserta ujian digital (F3/EXAM-2): identitas + jadwal ujian + QR.
     * Read-only; QR (nomor pendaftaran) dirender client-side di Vue.
     */
    public function kartuUjian(Request $request): Response
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        $jadwal = JadwalUjian::query()
            ->orderBy('tanggal')
            ->orderBy('waktu_mulai')
            ->get()
            ->map(static fn (JadwalUjian $j): array => [
                'kategori' => (string) $j->kategori_ujian,
                'tanggal' => $j->tanggal?->format('Y-m-d'),
                'waktuMulai' => $j->waktu_mulai ? substr((string) $j->waktu_mulai, 0, 5) : null,
                'waktuSelesai' => $j->waktu_selesai ? substr((string) $j->waktu_selesai, 0, 5) : null,
                'ruang' => (string) ($j->ruang ?? ''),
                'keterangan' => (string) ($j->keterangan ?? ''),
            ])->values()->all();

        return Inertia::render('PPDB/Portal/KartuUjian', [
            'pesantren' => tenant()?->name ?? 'Pesantren',
            'nomorPendaftaran' => (string) $calon->nomor_pendaftaran,
            'nama' => (string) $calon->nama,
            'jadwal' => $jadwal,
        ]);
    }

    /**
     * Halaman daftar ulang (F3/REREG-1) — hanya peserta LULUS. Ringkasan data
     * + tombol konfirmasi yang memicu konversi ke Santri.
     */
    public function daftarUlang(Request $request): Response|RedirectResponse
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        if ((string) $calon->status_pendaftaran === DaftarUlangService::STATUS_SELESAI) {
            return redirect('/spmb')->with('info', 'Anda sudah menyelesaikan daftar ulang.');
        }

        if ((string) $calon->status_pendaftaran !== 'lulus') {
            return redirect('/spmb')->with('info', 'Daftar ulang dibuka setelah Anda dinyatakan lulus.');
        }

        return Inertia::render('PPDB/Portal/DaftarUlang', [
            'nomorPendaftaran' => (string) $calon->nomor_pendaftaran,
            'nama' => (string) $calon->nama,
            'asalSekolah' => (string) ($calon->asal_sekolah ?? ''),
            'noHpWali' => (string) ($calon->no_hp_wali ?? ''),
        ]);
    }

    /** Proses daftar ulang (F3/REREG-2): konversi lulus → Santri via service. */
    public function prosesDaftarUlang(Request $request, DaftarUlangService $service): RedirectResponse
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        try {
            $service->process($calon);
        } catch (\Throwable $e) {
            return back()->withErrors(['daftar_ulang' => $e->getMessage()]);
        }

        return redirect('/spmb')->with('success', 'Daftar ulang berhasil. Selamat bergabung menjadi santri! 🎉');
    }

    public function updateBiodata(Request $request): RedirectResponse
    {
        $calon = $this->resolveOwnedCandidate($request->user());

        $this->authorize('update', $calon);

        // Validasi HANYA field yang terlihat peserta (mode required|optional).
        // Field 'hidden'/'staf' tak masuk aturan → peserta tak bisa menulisnya.
        $config = $this->fieldConfigurationService->getConfiguration();
        $validated = $request->validate($this->buildValidationRules($config));

        // Kunci field = nama kolom (snake_case) → persist langsung, tanpa pemetaan.
        $calon->update($validated);

        return back()->with('success', 'Biodata pendaftaran berhasil diperbarui.');
    }

    /**
     * Penegakan kuota tenant pada unggahan PPDB (cegah over-penyimpanan server).
     * Kembalikan pesan error bila kuota tak cukup, null bila aman.
     */
    private function kuotaTerlampaui(int $sizeBytes): ?string
    {
        $tenantId = tenant()?->getTenantKey();
        if ($tenantId === null) {
            return null;
        }

        $quota = app(StorageQuotaService::class);
        if (! $quota->canUpload($tenantId, $sizeBytes)) {
            return 'Kuota penyimpanan penuh (sisa '
                .StorageQuotaService::humanBytes($quota->remainingBytes($tenantId))
                .'). Hubungi panitia untuk menambah kuota.';
        }

        return null;
    }

    private function resolveOwnedCandidate(?User $user): CalonSantri
    {
        if ($user === null) {
            throw ValidationException::withMessages([
                'auth' => 'Silakan masuk terlebih dahulu.',
            ]);
        }

        $calon = CalonSantri::query()
            ->where('user_id', $user->id)
            ->first();

        if ($calon === null) {
            throw ValidationException::withMessages([
                'auth' => 'Data pendaftaran Anda belum ditemukan. Silakan hubungi panitia.',
            ]);
        }

        $this->authorize('view', $calon);

        return $calon;
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<string, mixed>
     */
    private function buildCandidatePayload(CalonSantri $calon, array $fieldConfiguration): array
    {
        // Nilai tiap field biodata, dikunci nama kolom (snake_case) agar form Vue
        // bisa mengikat langsung ke candidate.values[key].
        $values = [];
        foreach (PpdbBiodataFields::keys() as $key) {
            $values[$key] = $this->fieldValue($calon, $key);
        }

        return [
            'id' => $calon->id,
            'nomorPendaftaran' => $calon->nomor_pendaftaran,
            'statusPendaftaran' => (string) $calon->status_pendaftaran,
            'nama' => (string) $calon->nama,
            'values' => $values,
            'canEditBiodata' => $this->isEditableStatus((string) $calon->status_pendaftaran),
            'completion' => [
                'filled' => $this->countCompletedRequiredFields($calon, $fieldConfiguration),
                'total' => $this->countRequiredFields($fieldConfiguration),
            ],
        ];
    }

    /** Nilai satu field biodata untuk payload form (tanggal → Y-m-d, null → ''). */
    private function fieldValue(CalonSantri $calon, string $key): mixed
    {
        if ($key === 'tanggal_lahir') {
            return $calon->tanggal_lahir?->format('Y-m-d') ?? '';
        }

        $value = $calon->{$key};

        return $value ?? '';
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<int, array<string, mixed>>
     */
    private function buildChecklist(CalonSantri $calon, array $fieldConfiguration): array
    {
        $requiredTotal = $this->countRequiredFields($fieldConfiguration);
        $requiredFilled = $this->countCompletedRequiredFields($calon, $fieldConfiguration);
        $status = (string) $calon->status_pendaftaran;

        return [
            [
                'title' => 'Lengkapi biodata peserta dan wali',
                'done' => $requiredFilled >= $requiredTotal,
                'description' => $requiredFilled >= $requiredTotal
                    ? 'Semua field wajib pada tahap F1 sudah terisi.'
                    : "Baru {$requiredFilled} dari {$requiredTotal} field wajib yang terisi.",
            ],
            [
                'title' => 'Simpan nomor pendaftaran dan link status',
                'done' => ! empty($calon->nomor_pendaftaran) && ! empty($calon->tracking_token),
                'description' => 'Nomor ini dipakai untuk aktivasi, login, dan pemantauan cepat.',
            ],
            [
                'title' => 'Pantau instruksi panitia berikutnya',
                'done' => in_array($status, ['lulus', 'cadangan', 'ditolak', 'registrasi_ulang'], true),
                'description' => $this->statusHelperText($status),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildStepper(CalonSantri $calon): array
    {
        $current = $this->resolveStepIndex((string) $calon->status_pendaftaran);
        $steps = [
            ['title' => 'Daftar', 'hint' => 'Formulir awal sudah masuk.'],
            ['title' => 'Berkas', 'hint' => 'Tahap unggah dan verifikasi berkas.'],
            ['title' => 'Pembayaran', 'hint' => 'Biaya pendaftaran dan verifikasi transfer.'],
            ['title' => 'Ujian', 'hint' => 'Jadwal ujian dan kartu peserta.'],
            ['title' => 'Pengumuman', 'hint' => 'Keputusan panitia untuk pendaftaran Anda.'],
            ['title' => 'Daftar Ulang', 'hint' => 'Konfirmasi akhir hingga resmi menjadi santri.'],
        ];

        return collect($steps)->map(function (array $step, int $index) use ($current): array {
            return [
                ...$step,
                'state' => $index < $current ? 'done' : ($index === $current ? 'current' : 'upcoming'),
            ];
        })->all();
    }

    private function buildStatusCard(CalonSantri $calon, string $trackingUrl): array
    {
        $status = (string) $calon->status_pendaftaran;

        return [
            'badge' => $this->humanizeStatus($status),
            'title' => $this->statusTitle($status),
            'description' => $this->statusDescription($status),
            'trackingUrl' => $trackingUrl,
        ];
    }

    /**
     * @param  array<string, array{label: string, mode: string}>  $fieldConfiguration
     * @return array<string, string>
     */
    private function buildMainAction(CalonSantri $calon, array $fieldConfiguration): array
    {
        $requiredTotal = $this->countRequiredFields($fieldConfiguration);
        $requiredFilled = $this->countCompletedRequiredFields($calon, $fieldConfiguration);
        $status = (string) $calon->status_pendaftaran;

        if ($requiredFilled < $requiredTotal && $this->isEditableStatus($status)) {
            return [
                'label' => 'Lengkapi biodata sekarang',
                'type' => 'form',
                'helper' => 'Isi dulu data wajib agar panitia mudah memverifikasi.',
            ];
        }

        return [
            'label' => 'Pantau status pendaftaran',
            'type' => 'tracking',
            'helper' => $this->statusHelperText($status),
        ];
    }

    /**
     * Aturan validasi HANYA untuk field yang terlihat peserta (mode
     * required|optional). Field hidden/staf sengaja diabaikan agar peserta tak
     * dapat menulisnya. Kunci aturan = nama kolom (snake_case).
     *
     * @param  array<string, array<string, mixed>>  $config
     * @return array<string, array<int, string>>
     */
    private function buildValidationRules(array $config): array
    {
        $rules = [];

        foreach (PpdbBiodataFields::all() as $key => $def) {
            $mode = $config[$key]['mode'] ?? $def['mode'];

            if (! in_array($mode, ['required', 'optional'], true)) {
                continue; // hidden & staf: di luar jangkauan peserta
            }

            $prefix = $mode === 'required' ? 'required' : 'nullable';
            $rules[$key] = [$prefix, ...$def['rules']];
        }

        return $rules;
    }

    /**
     * @param  array<string, array<string, mixed>>  $config
     */
    private function countRequiredFields(array $config): int
    {
        return collect($config)
            ->where('mode', 'required')
            ->count();
    }

    /**
     * @param  array<string, array<string, mixed>>  $config
     */
    private function countCompletedRequiredFields(CalonSantri $calon, array $config): int
    {
        return collect($config)
            ->filter(fn (array $field): bool => ($field['mode'] ?? null) === 'required')
            ->filter(function (array $field, string $key) use ($calon): bool {
                $value = $calon->{$key};

                return is_string($value) ? trim($value) !== '' : $value !== null;
            })
            ->count();
    }

    private function resolveStepIndex(string $status): int
    {
        return match ($status) {
            'draft', 'pending', 'verifikasi_berkas' => 0,
            'berkas_lengkap' => 1,
            'menunggu_pembayaran', 'pembayaran_pending', 'pembayaran_terverifikasi' => 2,
            'ujian', 'ujian_seleksi', 'hasil_ujian' => 3,
            'lulus', 'cadangan', 'ditolak' => 4,
            'registrasi_ulang' => 5,
            default => 0,
        };
    }

    private function humanizeStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu verifikasi awal',
            'verifikasi_berkas' => 'Verifikasi berkas',
            'berkas_lengkap' => 'Berkas lengkap',
            'menunggu_pembayaran', 'pembayaran_pending' => 'Menunggu pembayaran',
            'pembayaran_terverifikasi' => 'Pembayaran terverifikasi',
            'ujian', 'ujian_seleksi' => 'Tahap ujian',
            'hasil_ujian' => 'Hasil ujian siap',
            'lulus' => 'Lulus',
            'cadangan' => 'Cadangan',
            'ditolak' => 'Belum lulus',
            'registrasi_ulang' => 'Daftar ulang selesai',
            default => 'Pendaftaran diproses',
        };
    }

    private function statusTitle(string $status): string
    {
        return match ($status) {
            'lulus' => 'Alhamdulillah, Anda dinyatakan lulus.',
            'cadangan' => 'Anda masuk daftar cadangan.',
            'ditolak' => 'Status pendaftaran Anda belum lulus.',
            'registrasi_ulang' => 'Daftar ulang Anda sudah masuk.',
            default => 'Pendaftaran Anda sedang diproses panitia.',
        };
    }

    private function statusDescription(string $status): string
    {
        return match ($status) {
            'lulus' => 'Siapkan langkah daftar ulang sesuai instruksi yang akan dibuka panitia.',
            'cadangan' => 'Pantau portal ini secara berkala. Panitia akan memberi kabar bila ada kursi tersedia.',
            'ditolak' => 'Terima kasih sudah mendaftar. Bila ada gelombang berikutnya, panitia akan menginformasikan lebih lanjut.',
            'registrasi_ulang' => 'Data akhir Anda sudah tercatat. Simpan nomor pendaftaran sampai panitia memberi arahan berikutnya.',
            default => 'Lengkapi biodata yang masih kosong dan pantau instruksi tahap berikutnya dari panitia.',
        };
    }

    private function statusHelperText(string $status): string
    {
        return match ($status) {
            'lulus' => 'Panitia sudah menetapkan hasil. Tahap berikutnya adalah daftar ulang.',
            'cadangan' => 'Status Anda masih menunggu keputusan akhir panitia.',
            'ditolak' => 'Tidak ada aksi lanjutan di portal ini untuk saat ini.',
            'registrasi_ulang' => 'Pendaftaran sudah ditutup dan data akhir telah masuk.',
            default => 'Tahap lanjutan akan muncul di portal ini sesuai progres panitia.',
        };
    }

    private function isEditableStatus(string $status): bool
    {
        return ! in_array($status, ['ditolak', 'registrasi_ulang'], true);
    }
}
