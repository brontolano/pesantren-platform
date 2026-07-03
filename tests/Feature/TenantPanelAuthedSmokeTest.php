<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithPpdbTestDatabase;
use Tests\TestCase;

/**
 * Smoke panel admin tenant TER-AUTENTIKASI: render dashboard + resource inti
 * harus < 500. Menangkap regresi konfigurasi panel (mis. navigationGroups
 * berikon bentrok dgn item berikon di Filament v5) yang TIDAK terlihat dari
 * halaman login (login tetap 200 walau dashboard 500).
 */
class TenantPanelAuthedSmokeTest extends TestCase
{
    use InteractsWithPpdbTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePpdbTestingSchemaReady([
            'tenants', 'domains', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
            'santri', 'tagihan', 'pembayaran', 'calon_santri', 'gelombang_ppdb',
            'jenis_pelanggaran', 'pelanggaran_santri', 'sanksi_santri', 'eskalasi_disiplin',
            'kitab', 'sorogan_setoran', 'ngaji_sesi', 'ngaji_sesi_peserta',
        ]);
        $this->truncatePpdbTables(['domains', 'tenants']);
    }

    public function test_dashboard_dan_resource_inti_tidak_500_untuk_user_login(): void
    {
        $tenant = $this->createRuntimeTenant('smoke', 'Pesantren Smoke');
        tenancy()->initialize($tenant);
        DB::connection('tenant')->table('tenants')->updateOrInsert(
            ['id' => $tenant->id],
            ['name' => $tenant->name, 'is_active' => true, 'data' => json_encode([]),
                'created_at' => now(), 'updated_at' => now()],
        );

        // Provisioning seeder lengkap (role + permission) agar user bisa lihat semua menu.
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder', '--force' => true]);

        $user = User::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'name' => 'Admin Smoke',
            'email' => 'smoke@panel.test', 'password' => 'password123',
        ]);
        if (Schema::connection('tenant')->hasTable('roles')
            && DB::connection('tenant')->table('roles')->where('name', 'super_admin')->exists()) {
            $user->assignRole('super_admin');
        }
        Auth::login($user);

        $host = $tenant->id.'.localhost';
        $kernel = app(Kernel::class);

        // Sebagian modul default 'development' di registry test → tersembunyi utk
        // tenant non-dev. Promosikan ke released + aktifkan agar halaman/resource-nya
        // benar-benar ter-render (assert 200 di bawah), bukan 403 yang lolos diam-diam.
        // Ditulis SEBELUM request pertama agar cache modul memuat nilai terbaru.
        foreach (['kegiatan', 'notifikasi', 'kesehatan', 'tahfidz', 'mutu', 'asrama', 'pajak', 'akademik', 'ppdb', 'ai', 'keuangan'] as $modul) {
            DB::connection('pgsql')->table('module_registry')->where('key', $modul)->update(['status' => 'released']);
            DB::connection('pgsql')->table('tenant_modules')->insert([
                'tenant_id' => $tenant->id, 'module_name' => $modul, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 1 kegiatan agar halaman EDIT-nya (yang merender PresensiRelationManager,
        // WP-26) ikut ter-render. RelationManager TAK tampil di halaman list → tanpa
        // ini tak teruji. Diuji terpisah dengan assert 200 di bawah.
        $kegId = DB::connection('tenant')->table('kegiatan')->insertGetId([
            'tenant_id' => $tenant->id, 'nama' => 'Kegiatan Smoke', 'jenis' => 'umum',
            'mulai' => now(), 'status' => 'terjadwal',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 1 halaqah agar halaman EDIT-nya (merender KeanggotaanRelationManager, gap 89)
        // ikut ter-render. musyrif_id NOT NULL → pakai user smoke.
        $halaqahId = DB::connection('tenant')->table('halaqah')->insertGetId([
            'tenant_id' => $tenant->id, 'nama' => 'Halaqah Smoke', 'musyrif_id' => $user->id,
            'jenis' => 'tahfidz', 'aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 1 obat agar halaman EDIT-nya (merender MutasiRelationManager, gap 102) teruji.
        $obatId = DB::connection('tenant')->table('obat')->insertGetId([
            'tenant_id' => $tenant->id, 'nama' => 'Obat Smoke',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Regresi gap-126: PengaturanKeuangan menyimpan keuangan.jenis_lembaga
        // TER-BUNGKUS (['text'=>..]). Halaman Pusat Kebijakan (admin/pengaturan/
        // kebijakan, di $paths) membangun Select dari nilai ini — tanpa unwrap
        // defensif di stateDariSetting() → "Array to string conversion" 500.
        // Seed nilai ter-bungkus agar render Kebijakan menguji jalur ini.
        Setting::updateOrCreate(
            ['key' => 'keuangan.jenis_lembaga'],
            ['value' => ['text' => 'madrasah']],
        );

        $paths = ['admin', 'admin/santri/santris', 'admin/penempatan-asrama/penempatan-asramas', 'admin/tagihan/tagihans', 'admin/halaqah/halaqahs', 'admin/tahfidz/munaqasyah/munaqasyahs', 'admin/hafalan-tahfidzs', 'admin/akademik/kitab/kitabs', 'admin/akademik/lembaga/lembagas', 'admin/akademik/pelajaran/pelajarans', 'admin/akademik/jadwal-pelajaran/jadwal-pelajarans', 'admin/akademik/nilai/nilais', 'admin/akademik/kurikulum/kurikulums', 'admin/akademik/kalender-akademik/kalender-akademiks', 'admin/payroll/jabatan/jabatans', 'admin/payroll/penugasan/penugasan-pegawais', 'admin/payroll/employee/employees', 'admin/jenis-pelanggaran/jenis-pelanggarans', 'admin/pelanggaran-santri/pelanggaran-santris', 'admin/rekap-disiplin', 'admin/kesiapan-emis', 'admin/sidang-kenaikan', 'admin/grid-nilai', 'admin/rekap-jurnal-kbm', 'admin/alumni/alumnis', 'admin/payroll/generate-thr', 'admin/akademik/rapor-diniyah', 'admin/akademik/rapor-diniyah/isi-rubrik', 'admin/payroll/kontrak/kontrak-kerjas', 'admin/payroll/pinjaman/pinjaman-pegawais', 'admin/log-gerbang', 'admin/pengajuan-jenguk', 'admin/moderasi-pesan-wali', 'admin/profil-mutu-santri', 'admin/mutu-kelas', 'admin/mutu-lembaga', 'admin/pengaturan/kebijakan', 'admin/notifikasi/pengaturan', 'admin/pusat-modul', 'admin/setup-pesantren', 'admin/setup-unit', 'admin/setup-unit?unit=kesantrian', 'admin/setup-unit?unit=ppdb', 'admin/setup-unit?unit=akademik', 'admin/setup-unit?unit=keuangan', 'admin/setup-unit?unit=akuntansi', 'admin/setup-unit?unit=kepegawaian', 'admin/setup-unit?unit=kelembagaan', 'admin/setup-unit?unit=pengaturan', 'admin/tabungan/tabungans', 'admin/ledger/akuns', 'admin/keuangan/kasir-kantin', 'admin/keuangan/rekon-kantin', 'admin/akademik/paket-soal-akademik', 'admin/akademik/sesi-ujian-akademik', 'admin/kesehatan/obat', 'admin/kesehatan/kunjungan-uks', 'admin/sarpras/laporan-kerusakan', 'admin/sarpras/area-piket', 'admin/sarpras/piket-hari-ini', 'admin/laporan', 'admin/ledger/buku-besar', 'admin/ledger/bku', 'admin/ledger/jurnal-manual', 'admin/ledger/neraca-saldo', 'admin/ledger/varians-anggaran', 'admin/ledger/buku-pembantu-pihak', 'admin/ledger/laporan-kehalalan', 'admin/pajak/buku-pembantu', 'admin/laporan/rkam', 'admin/laporan/dana-bos-bop', 'admin/laporan/dana-terikat', 'admin/laporan/dana-kebajikan', 'admin/laporan/keuangan-pokok', 'admin/keuangan/loket-kas', 'admin/tunggakan-alumni', 'admin/pajak/kalkulator', 'admin/pajak/kalkulator-pph21', 'admin/payroll/pratinjau-khidmah'];
        foreach ($paths as $path) {
            $req = Request::create("http://{$host}/{$path}", 'GET');
            $req->headers->set('Host', $host);
            $status = $kernel->handle($req)->getStatusCode();

            $this->assertLessThan(500, $status, "Render /{$path} mengembalikan {$status} (>=500). Panel rusak utk user login.");
        }

        // Halaman edit kegiatan WAJIB 200 (super_admin lolos gerbang) → bukti
        // PresensiRelationManager ter-mount tanpa error (bukan sekadar <500, agar
        // slug salah/akses ditolak/RM rusak benar-benar tertangkap).
        $editReq = Request::create("http://{$host}/admin/kegiatans/{$kegId}/edit", 'GET');
        $editReq->headers->set('Host', $host);
        $editStatus = $kernel->handle($editReq)->getStatusCode();
        $this->assertSame(200, $editStatus, "Edit kegiatan (PresensiRelationManager) status {$editStatus}, harusnya 200.");

        // Log Notifikasi (resource read-only baru) WAJIB 200 → bukti resource ter-render.
        $logReq = Request::create("http://{$host}/admin/komunikasi/log-notifikasi", 'GET');
        $logReq->headers->set('Host', $host);
        $logStatus = $kernel->handle($logReq)->getStatusCode();
        $this->assertSame(200, $logStatus, "Log Notifikasi (LogNotifikasiResource) status {$logStatus}, harusnya 200.");

        // Kunjungan UKS (KunjunganUksResource) WAJIB 200 → bukti resource TERDAFTAR di
        // panel (panel pakai daftar eksplisit; resource tak terdaftar = 404 senyap).
        $uksReq = Request::create("http://{$host}/admin/kesehatan/kunjungan-uks", 'GET');
        $uksReq->headers->set('Host', $host);
        $uksStatus = $kernel->handle($uksReq)->getStatusCode();
        $this->assertSame(200, $uksStatus, "Kunjungan UKS (KunjunganUksResource) status {$uksStatus}, harusnya 200.");

        // Form Buat Hafalan WAJIB 200 → bukti Select halaqah_id (relationship) ter-render
        // tanpa error (form hanya tampil di halaman create/edit, bukan list).
        $hafalanReq = Request::create("http://{$host}/admin/hafalan-tahfidzs/create", 'GET');
        $hafalanReq->headers->set('Host', $host);
        $hafalanStatus = $kernel->handle($hafalanReq)->getStatusCode();
        $this->assertSame(200, $hafalanStatus, "Buat Hafalan (Select halaqah) status {$hafalanStatus}, harusnya 200.");

        // Edit Halaqah WAJIB 200 → bukti KeanggotaanRelationManager (anggota halaqah)
        // ter-mount tanpa error (RM hanya tampil di halaman edit).
        $halaqahReq = Request::create("http://{$host}/admin/halaqah/halaqahs/{$halaqahId}/edit", 'GET');
        $halaqahReq->headers->set('Host', $host);
        $halaqahStatus = $kernel->handle($halaqahReq)->getStatusCode();
        $this->assertSame(200, $halaqahStatus, "Edit Halaqah (KeanggotaanRelationManager) status {$halaqahStatus}, harusnya 200.");

        // Issue #330 — Sorogan & Ngaji Kitab WAJIB 200 → bukti resource TERDAFTAR
        // + form penilaian (create) & halaman progres per santri ter-render.
        foreach ([
            'admin/setoran-sorogan' => 'ListSetoranSorogan',
            'admin/setoran-sorogan/create' => 'CreateSetoranSorogan (form penilaian)',
            'admin/setoran-sorogan/progres' => 'ProgresSorogan (progres per santri)',
            'admin/ngaji-sesi' => 'ListNgajiSesi',
            'admin/ngaji-sesi/create' => 'CreateNgajiSesi',
        ] as $soroganPath => $soroganLabel) {
            $soroganReq = Request::create("http://{$host}/{$soroganPath}", 'GET');
            $soroganReq->headers->set('Host', $host);
            $soroganStatus = $kernel->handle($soroganReq)->getStatusCode();
            $this->assertSame(200, $soroganStatus, "Sorogan/Ngaji Kitab ({$soroganLabel}) status {$soroganStatus}, harusnya 200.");
        }

        // Edit Sesi Ngaji WAJIB 200 → bukti PesertaRelationManager ter-mount
        // (RM hanya tampil di halaman edit).
        $kitabSmokeId = DB::connection('tenant')->table('kitab')->insertGetId([
            'tenant_id' => $tenant->id, 'nama' => 'Kitab Smoke',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ngajiSesiId = DB::connection('tenant')->table('ngaji_sesi')->insertGetId([
            'tenant_id' => $tenant->id, 'kitab_id' => $kitabSmokeId, 'tanggal' => now()->toDateString(),
            'metode' => 'bandongan', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ngajiEditReq = Request::create("http://{$host}/admin/ngaji-sesi/{$ngajiSesiId}/edit", 'GET');
        $ngajiEditReq->headers->set('Host', $host);
        $ngajiEditStatus = $kernel->handle($ngajiEditReq)->getStatusCode();
        $this->assertSame(200, $ngajiEditStatus, "Edit Sesi Ngaji (PesertaRelationManager) status {$ngajiEditStatus}, harusnya 200.");

        // Sima'an (SimaAnResource baru, slug eksplisit tahfidz/simaan) WAJIB 200 →
        // bukti resource TERDAFTAR di panel.
        $simaanReq = Request::create("http://{$host}/admin/tahfidz/simaan", 'GET');
        $simaanReq->headers->set('Host', $host);
        $simaanStatus = $kernel->handle($simaanReq)->getStatusCode();
        $this->assertSame(200, $simaanStatus, "Sima'an (SimaAnResource) status {$simaanStatus}, harusnya 200.");

        // Daftar Pelanggaran/Ta'zir WAJIB 200 → bukti aksi catatSanksi/selesaikan
        // (transisi status, gap 91) ter-konstruksi tanpa error saat tabel dirender.
        $tazirReq = Request::create("http://{$host}/admin/pelanggaran-santri/pelanggaran-santris", 'GET');
        $tazirReq->headers->set('Host', $host);
        $tazirStatus = $kernel->handle($tazirReq)->getStatusCode();
        $this->assertSame(200, $tazirStatus, "Daftar Ta'zir (aksi siklus status) status {$tazirStatus}, harusnya 200.");

        // Mutu per Kelas WAJIB 200 → bukti empty-state instruktif (tanpa kelas dipilih)
        // ter-render, bukan halaman kosong/rusak (gap 119).
        $mutuKelasReq = Request::create("http://{$host}/admin/mutu-kelas", 'GET');
        $mutuKelasReq->headers->set('Host', $host);
        $mutuKelasStatus = $kernel->handle($mutuKelasReq)->getStatusCode();
        $this->assertSame(200, $mutuKelasStatus, "Mutu per Kelas (empty-state) status {$mutuKelasStatus}, harusnya 200.");

        // Edit Obat WAJIB 200 → bukti MutasiRelationManager (riwayat stok, gap 102)
        // ter-mount tanpa error (RM hanya tampil di halaman edit).
        $obatReq = Request::create("http://{$host}/admin/kesehatan/obat/{$obatId}/edit", 'GET');
        $obatReq->headers->set('Host', $host);
        $obatStatus = $kernel->handle($obatReq)->getStatusCode();
        $this->assertSame(200, $obatStatus, "Edit Obat (MutasiRelationManager) status {$obatStatus}, harusnya 200.");

        // Gelombang PPDB (GelombangPpdbResource baru, slug eksplisit gelombang-ppdb,
        // gap-130) WAJIB 200 → bukti resource TERDAFTAR di panel + tabel master ada
        // (panel pakai daftar eksplisit; resource tak terdaftar = 404 senyap).
        $gelombangReq = Request::create("http://{$host}/admin/gelombang-ppdb", 'GET');
        $gelombangReq->headers->set('Host', $host);
        $gelombangStatus = $kernel->handle($gelombangReq)->getStatusCode();
        $this->assertSame(200, $gelombangStatus, "Gelombang PPDB (GelombangPpdbResource) status {$gelombangStatus}, harusnya 200.");

        // Pengaturan PPDB (Page baru, slug pengaturan-ppdb, gap-125) WAJIB 200 →
        // bukti halaman rumah-kanonik PPDB ter-render (membaca Setting admission_*).
        $pengaturanPpdbReq = Request::create("http://{$host}/admin/pengaturan-ppdb", 'GET');
        $pengaturanPpdbReq->headers->set('Host', $host);
        $pengaturanPpdbStatus = $kernel->handle($pengaturanPpdbReq)->getStatusCode();
        $this->assertSame(200, $pengaturanPpdbStatus, "Pengaturan PPDB (PengaturanPpdb) status {$pengaturanPpdbStatus}, harusnya 200.");

        // Field Kustom Santri (custom field per-tenant, Page baru) WAJIB 200 →
        // bukti halaman kelola definisi ter-render (membaca Setting custom_fields.santri).
        $fieldKustomReq = Request::create("http://{$host}/admin/field-kustom-santri", 'GET');
        $fieldKustomReq->headers->set('Host', $host);
        $fieldKustomStatus = $kernel->handle($fieldKustomReq)->getStatusCode();
        $this->assertSame(200, $fieldKustomStatus, "Field Kustom Santri (FieldKustomSantri) status {$fieldKustomStatus}, harusnya 200.");

        // Regresi custom field: dengan minimal 1 definisi field tersimpan, form
        // Santri (create) yang merender seksi dinamis "Data Tambahan" WAJIB 200.
        Setting::updateOrCreate(
            ['key' => 'custom_fields.santri'],
            ['value' => ['fields' => [
                ['key' => 'golongan_darah', 'label' => 'Golongan Darah', 'type' => 'select', 'options' => ['A', 'B', 'AB', 'O'], 'required' => false],
                ['key' => 'hobi', 'label' => 'Hobi', 'type' => 'text', 'options' => [], 'required' => false],
            ]]],
        );
        $santriCreateReq = Request::create("http://{$host}/admin/santri/santris/create", 'GET');
        $santriCreateReq->headers->set('Host', $host);
        $santriCreateStatus = $kernel->handle($santriCreateReq)->getStatusCode();
        $this->assertSame(200, $santriCreateStatus, "Buat Santri (seksi Data Tambahan dinamis) status {$santriCreateStatus}, harusnya 200.");

        // Daftar Kamar Asrama WAJIB 200 → bukti OkupansiAsramaWidget (getStats →
        // getOccupancySummary, gap 102) ter-render di header tanpa error.
        $kamarReq = Request::create("http://{$host}/admin/kamar-asrama/kamar-asramas", 'GET');
        $kamarReq->headers->set('Host', $host);
        $kamarStatus = $kernel->handle($kamarReq)->getStatusCode();
        $this->assertSame(200, $kamarStatus, "Kamar Asrama (OkupansiAsramaWidget) status {$kamarStatus}, harusnya 200.");

        // Form Buat Biaya Asrama WAJIB 200 → bukti Select periode terkontrol (gap 133)
        // + opsi periode ter-bangun tanpa error (form hanya di halaman create/edit).
        $biayaReq = Request::create("http://{$host}/admin/biaya-asrama/biaya-asramas/create", 'GET');
        $biayaReq->headers->set('Host', $host);
        $biayaStatus = $kernel->handle($biayaReq)->getStatusCode();
        $this->assertSame(200, $biayaStatus, "Buat Biaya Asrama (Select periode) status {$biayaStatus}, harusnya 200.");

        // Pusat Pengaturan (Page indeks baru, gap 124) WAJIB 200 → bukti Page TERDAFTAR
        // (auto-discover app/Filament/Pages) & getGroups() resolve tanpa error.
        $pusatReq = Request::create("http://{$host}/admin/pusat-pengaturan", 'GET');
        $pusatReq->headers->set('Host', $host);
        $pusatStatus = $kernel->handle($pusatReq)->getStatusCode();
        $this->assertSame(200, $pusatStatus, "Pusat Pengaturan (PusatPengaturan Page) status {$pusatStatus}, harusnya 200.");

        // Laporan Pajak (Page read-only baru, gap 88) WAJIB 200 → bukti Page render
        // tanpa error pada tenant kosong (delegasi ke service pajak existing) + modul
        // pajak diaktifkan di test. Page auto-discover Modules/Pajak/app/Filament/Pages.
        $pajakReq = Request::create("http://{$host}/admin/pajak/laporan", 'GET');
        $pajakReq->headers->set('Host', $host);
        $pajakStatus = $kernel->handle($pajakReq)->getStatusCode();
        $this->assertSame(200, $pajakStatus, "Laporan Pajak (LaporanPajak Page) status {$pajakStatus}, harusnya 200.");

        // Komponen Nilai (Resource baru gap-85, slug default komponen-nilais) WAJIB 200
        // → bukti Resource TERDAFTAR eksplisit di TenantPanelProvider (panel pakai daftar
        // eksplisit; resource tak terdaftar = 404 senyap) + modul akademik diaktifkan.
        $komponenReq = Request::create("http://{$host}/admin/komponen-nilai/komponen-nilais", 'GET');
        $komponenReq->headers->set('Host', $host);
        $komponenStatus = $kernel->handle($komponenReq)->getStatusCode();
        $this->assertSame(200, $komponenStatus, "Komponen Nilai (KomponenNilaiResource) status {$komponenStatus}, harusnya 200.");

        // Halaman modul AI Khadim (dirilis 2026-06-25, gap-83) WAJIB benar-benar
        // RENDER 200 untuk tenant yang mengaktifkan AI — bukan cuma route terdaftar.
        // Menangkap error render (mis. status infra Ollama tak tertangkap, dependensi
        // hilang) pada halaman yang kini LIVE. statusInfra() toleran bila Ollama mati.
        foreach ([
            'admin/pengaturan-ai',
            'admin/sumber-pengetahuan-ai',
            'admin/persetujuan-ai',
            'admin/eskalasi-tanya-ustadz',
            'admin/kotak-masuk-wa',
            'admin/usulan-faq-ai',
            'admin/ikhtisar-kitab-ai',
            'admin/generator-soal-ai',
            'admin/generator-rpp-ai',
        ] as $aiPath) {
            $aiReq = Request::create("http://{$host}/{$aiPath}", 'GET');
            $aiReq->headers->set('Host', $host);
            $aiStatus = $kernel->handle($aiReq)->getStatusCode();
            $this->assertSame(200, $aiStatus, "Halaman AI /{$aiPath} status {$aiStatus}, harusnya 200 (modul AI released).");
        }

        // Tagihan Rutin (mesin langganan SPP/makan/asrama — jalur uang, issue #335)
        // WAJIB 200: daftar aturan + form create (Select target lembaga/kelas,
        // sumber dana, divisi — hanya ter-render di halaman create/edit).
        foreach (['admin/tagihan-rutin/tagihan-rutins', 'admin/tagihan-rutin/tagihan-rutins/create'] as $trPath) {
            $trReq = Request::create("http://{$host}/{$trPath}", 'GET');
            $trReq->headers->set('Host', $host);
            $trStatus = $kernel->handle($trReq)->getStatusCode();
            $this->assertSame(200, $trStatus, "Tagihan Rutin /{$trPath} status {$trStatus}, harusnya 200.");
        }

        // Tampilan & Tema (PengaturanTampilan) WAJIB 200 → bukti seksi PAKET TEMA
        // (kartu bundel + thumbnail SVG generatif + badge peran) & seksi Custom
        // ter-render tanpa error untuk user login (auth()->getRoleNames, x-filament::icon).
        $tampilanReq = Request::create("http://{$host}/admin/pengaturan/tampilan", 'GET');
        $tampilanReq->headers->set('Host', $host);
        $tampilanStatus = $kernel->handle($tampilanReq)->getStatusCode();
        $this->assertSame(200, $tampilanStatus, "Tampilan & Tema (PengaturanTampilan + Paket Tema) status {$tampilanStatus}, harusnya 200.");

        // CAKUPAN MENYELURUH (issue #335): render halaman INDEX dari SEMUA resource
        // yang terdaftar di panel tenant, secara DINAMIS — resource baru otomatis
        // ikut teruji tanpa perlu menambah path manual. Assert <500 (403 modul
        // nonaktif boleh; 500 = panel rusak — regresi ikon nav/statePath/dll).
        $sudah = array_map(fn (string $p) => strtok($p, '?'), $paths);
        $panel = Filament::getPanel('tenant');
        $resourceDicek = 0;
        foreach ($panel->getResources() as $resourceClass) {
            try {
                $url = $resourceClass::getUrl('index', panel: 'tenant');
            } catch (\Throwable) {
                continue; // resource tanpa halaman index (mis. simple/no pages)
            }
            $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            if ($path === '' || in_array($path, $sudah, true)) {
                continue; // sudah teruji di daftar eksplisit di atas
            }
            $sudah[] = $path;

            $rReq = Request::create("http://{$host}/{$path}", 'GET');
            $rReq->headers->set('Host', $host);
            $rStatus = $kernel->handle($rReq)->getStatusCode();
            $this->assertLessThan(500, $rStatus, "Render /{$path} ({$resourceClass}) mengembalikan {$rStatus} (>=500). Panel rusak utk user login.");
            $resourceDicek++;
        }
        $this->assertGreaterThan(50, $resourceDicek, 'Sweep dinamis resource panel terlalu sedikit — kemungkinan panel tidak ter-boot.');

        tenancy()->end();
    }
}
