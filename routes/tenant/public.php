<?php

declare(strict_types=1);

use App\Http\Controllers\DemoAutoLoginController;
use App\Support\TenantBranding;
use App\Support\UiMode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Modules\CMS\Http\Controllers\ArticleDetailController;
use Modules\CMS\Http\Controllers\SitemapController;
use Modules\Keuangan\Models\Donasi;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\KontrakKerja;
use Modules\Payroll\Services\RaporUstadzService;

/*
 * Rute tenant "publik/lintas-peran" — di-require dari routes/tenant.php di dalam
 * grup middleware tenancy (web + PreventAccessFromCentralDomains + InitializeTenancyByDomain).
 */

/*
 * Tenant storage file serve — dipakai blade Settings & Branding untuk
 * menampilkan preview logo. Storage::url('public') menghasilkan URL
 * APP_URL/storage/{path} yang tidak ter-resolve ke path tenant di Docker
 * (tidak ada symlink tenant-specific). Route ini men-serve file langsung
 * dari PHP menggunakan disk 'public' yang sudah ter-bootstrap untuk tenant,
 * sehingga path-nya selalu benar tanpa memerlukan symlink.
 *
 * Akses hanya untuk pengguna yang sudah login (middleware 'auth' via
 * Filament panel — request dari blade Livewire sudah terautentikasi).
 * Fallback: 404 jika file tidak ada.
 */
Route::get('/_tenant-storage/{path}', function (string $path) {
    $disk = Storage::disk('public');
    // Sanitasi path — cegah directory traversal
    $path = ltrim(str_replace('..', '', $path), '/');

    // Direktori dokumen sensitif (arsip HR/kesantrian) WAJIB login —
    // sisanya (logo, galeri landing) tetap publik.
    // 'donasi' = bukti transfer unggahan publik — hanya admin yang perlu
    // melihat balik (anti stored-XSS / anti enumerasi bukti orang lain).
    $direktoriSensitif = ['kontrak-kerja', 'mutasi', 'disiplin', 'sertifikasi', 'donasi', 'kerusakan', 'pelamar'];
    $segmenPertama = explode('/', $path, 2)[0];
    if (in_array($segmenPertama, $direktoriSensitif, true)) {
        abort_unless(auth()->check(), 403);
    }

    // Berkas HR (CV/ijazah pelamar, kontrak/SK) lebih ketat: akun santri/
    // wali ikut "login" — wajib hak payroll/keuangan, bukan sekadar authed.
    if (in_array($segmenPertama, ['pelamar', 'kontrak-kerja'], true)) {
        abort_unless(
            auth()->user()?->canAny(['run_payroll', 'manage_payroll', 'manage_keuangan']) ?? false,
            403,
        );
    }

    abort_unless($disk->exists($path), 404);

    return response()->file(
        $disk->path($path),
        ['Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream'],
    );
})->where('path', '.*')->name('tenant.storage.file');

/*
 * Auto-login demo untuk PWA/TWA showcase — melogin user demo lalu redirect
 * ke PWA terkait TANPA kredensial. Dikunci ganda di controller: flag
 * DEMO_AUTOLOGIN + tenant harus 'demo' (selain itu 404). Lihat config/demo.php.
 * peran: guru | wali | keuangan | keuangan-lapangan | pimpinan
 */
Route::get('/demo-masuk/{peran}', DemoAutoLoginController::class)
    ->name('tenant.demo.autologin');

// 2c Baitul Maal — kuitansi donasi (pola print blade). HANYA donasi yang
// sudah dikonfirmasi bendahara; akses wajib login (dipanggil dari panel).
Route::get('/keuangan/donasi/{donasi}/kuitansi', function (int $donasi) {
    // Gate sama dengan DonasiResource::canAccess — kuitansi memuat PII
    // donatur dan id sekuensial; user tanpa hak keuangan (santri/wali)
    // tidak boleh bisa enumerasi kuitansi orang lain.
    abort_unless(
        auth()->user()?->canAny(['view_keuangan', 'manage_keuangan']) ?? false,
        403,
    );

    $record = Donasi::query()
        ->with(['program.sumberDana', 'konfirmator'])
        ->findOrFail($donasi);

    abort_unless($record->status === Donasi::STATUS_DIKONFIRMASI, 404);

    return view('exports.kuitansi-donasi-print', [
        'donasi' => $record,
        'namaPesantren' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
    ]);
})->middleware('auth')->name('tenant.donasi.kuitansi');

// WP-20 — Rapor Ustadz Tahunan (pola print blade). Gate: mudir/atasan
// (manage_payroll/run_payroll) ATAU pegawai ybs (lihat rapornya sendiri,
// read-only). Selain itu 403 — rapor memuat penilaian & rekap kinerja.
Route::get('/payroll/rapor-ustadz/{employee}/{tahun}', function (int $employee, int $tahun) {
    abort_unless($tahun >= 2000 && $tahun <= 2100, 404);

    $record = Employee::query()->findOrFail($employee);
    $user = auth()->user();

    abort_unless($user !== null && app(RaporUstadzService::class)->bolehLihat($record, $user), 403);

    return view('exports.rapor-ustadz-print', [
        'employee' => $record,
        'rekap' => app(RaporUstadzService::class)->rekap($record, $tahun),
        // Tautan arsip kontrak/SK (WP-16) — konteks masa kerja.
        'kontrak' => KontrakKerja::query()
            ->where('employee_id', $record->id)
            ->orderByDesc('mulai')
            ->get(),
        'namaPesantren' => TenantBranding::name(tenant('name') ?? 'Pesantren'),
    ]);
})->middleware('auth')->name('tenant.payroll.rapor-ustadz');

Route::get('/artikel/{slug}', ArticleDetailController::class)->name('tenant.cms.articles.show');
Route::get('/sitemap.xml', SitemapController::class)->name('tenant.cms.sitemap');

// Switch mode tampilan panel (simple <-> advanced) — preferensi per user.
Route::post('/ui-mode/toggle', function () {
    $user = auth()->user();
    abort_unless($user !== null, 403);
    $user->forceFill([
        'ui_mode' => UiMode::simple() ? 'advanced' : 'simple',
    ])->save();

    return back();
})->middleware('auth')->name('tenant.ui-mode.toggle');
