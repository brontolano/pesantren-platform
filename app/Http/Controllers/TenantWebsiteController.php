<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PPDB\Services\PpdbRegistrationNumberService;
use Stancl\Tenancy\Database\Models\Domain;

class TenantWebsiteController extends Controller
{
    /**
     * Resolve tenant dari host saat ini dan inisialisasi tenancy.
     */
    protected function resolveTenant(Request $request)
    {
        $host = $request->getHost();

        // Central domain tidak punya website tenant
        abort_if(in_array($host, config('tenancy.central_domains', []), true), 404);

        $domain = Domain::where('domain', $host)->first();
        abort_unless($domain, 404);

        $tenant = $domain->tenant;
        abort_unless($tenant, 404);
        abort_unless($tenant->is_active, 503, 'Website pesantren sedang tidak aktif. Silakan hubungi admin.');

        if (! tenancy()->initialized || tenant('id') !== $tenant->id) {
            tenancy()->initialize($tenant);
        }

        return $tenant;
    }

    /**
     * Ambil settings tenant sebagai map key => text.
     */
    protected function settings(string $tenantId): array
    {
        return DB::table('settings')
            ->where('tenant_id', $tenantId)
            ->pluck('value', 'key')
            ->map(function ($v) {
                $decoded = json_decode($v, true);

                return is_array($decoded) ? ($decoded['text'] ?? $v) : $v;
            })
            ->toArray();
    }

    public function home(Request $request)
    {
        $tenant = $this->resolveTenant($request);
        $settings = $this->settings($tenant->id);

        $stats = [
            'santri' => DB::table('santri')->where('tenant_id', $tenant->id)->where('status', 'aktif')->count(),
            'kelas' => DB::table('kelas')->where('tenant_id', $tenant->id)->count(),
            'guru' => DB::table('users')->where('tenant_id', $tenant->id)->count(),
            'pelajaran' => DB::table('pelajaran')->where('tenant_id', $tenant->id)->count(),
        ];

        return view('tenant.landing', [
            'tenant' => $tenant,
            's' => $settings,
            'stats' => $stats,
        ]);
    }

    public function ppdbForm(Request $request)
    {
        $tenant = $this->resolveTenant($request);
        $settings = $this->settings($tenant->id);

        // Tenant dengan pendaftaran via website eksternal (mis. asy-syifaa) →
        // alihkan "Isi Formulir Pendaftaran" ke URL itu (Setting ppdb_external_url).
        // Guard: jika /ppdb di-embed iframe (Sec-Fetch-Dest: iframe), tetap render
        // form agar tidak terjadi loop bila website mengembed halaman ini.
        $external = trim((string) (Setting::getTenantStringValue('ppdb_external_url') ?? ''));
        if ($external !== '' && $request->header('Sec-Fetch-Dest') !== 'iframe') {
            return redirect()->away($external);
        }

        return view('tenant.ppdb-form', [
            'tenant' => $tenant,
            's' => $settings,
            'success' => session('ppdb_success'),
        ]);
    }

    public function ppdbStore(Request $request)
    {
        $tenant = $this->resolveTenant($request);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before:today'],
            'asal_sekolah' => ['nullable', 'string', 'max:255'],
            'program_diminati' => ['required', 'string', 'max:50'],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'no_hp_wali' => ['required', 'string', 'max:30'],
            'email_wali' => ['nullable', 'email', 'max:255'],
            'alamat' => ['required', 'string', 'max:1000'],
            'setuju_kebijakan' => ['accepted'],
        ], [
            'setuju_kebijakan.accepted' => 'Centang persetujuan Kebijakan Privasi Sistem untuk melanjutkan.',
        ], [
            'nama' => 'nama lengkap',
            'jenis_kelamin' => 'jenis kelamin',
            'tempat_lahir' => 'tempat lahir',
            'tanggal_lahir' => 'tanggal lahir',
            'program_diminati' => 'program',
            'nama_ayah' => 'nama ayah/wali',
            'no_hp_wali' => 'nomor HP wali',
        ]);

        // Nomor + insert satu transaksi via service (race-safe, retry saat tabrakan).
        // Menggantikan count()+1 lama yang bisa duplikat (race & baris terhapus).
        $calon = app(PpdbRegistrationNumberService::class)->createWithNomor([
            'tenant_id' => $tenant->id,
            'tracking_token' => Str::random(40),
            'tracking_token_generated_at' => now(),
            'nama' => $data['nama'],
            'tanggal_lahir' => $data['tanggal_lahir'],
            'tempat_lahir' => $data['tempat_lahir'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'nama_ayah' => $data['nama_ayah'],
            'nama_ibu' => $data['nama_ibu'] ?? null,
            'no_hp_wali' => $data['no_hp_wali'],
            'email_wali' => $data['email_wali'] ?? null,
            'alamat' => $data['alamat'],
            'asal_sekolah' => $data['asal_sekolah'] ?? null,
            'program_diminati' => $data['program_diminati'],
            'status_pendaftaran' => 'pending',
            'consent_kebijakan_at' => now(), // persetujuan Kebijakan Privasi Sistem (UU PDP)
        ]);

        return redirect()->route('tenant.ppdb.form')->with('ppdb_success', [
            'nomor' => $calon->nomor_pendaftaran,
            'nama' => $data['nama'],
        ]);
    }
}
