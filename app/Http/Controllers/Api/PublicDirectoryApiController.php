<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Kesantrian\Models\Alumni;
use Modules\Kesantrian\Models\Santri;
use Modules\Payroll\Models\Employee;
use Modules\PPDB\Models\CalonSantri;

/**
 * API publik direktori untuk website tenant (kompatibel kontrak legacy
 * api.asy-syifaa.com): hasil seleksi PPDB, direktori santri/alumni,
 * staff/pengajar, dan hitungan wali. Bentuk respons: {ok, data:[...]}.
 *
 * HANYA data yang memang ditayangkan publik di website pesantren (nama +
 * tahun) — tanpa NIS, kontak, atau data pribadi lain.
 */
class PublicDirectoryApiController extends Controller
{
    /** Hasil seleksi PPDB yang sudah diputuskan (lulus/cadangan/ditolak). */
    public function selectionResults(Request $request): JsonResponse
    {
        abort_unless(tenant(), 404);
        $search = trim((string) $request->query('search', ''));

        $query = CalonSantri::query()
            ->whereIn('status_pendaftaran', ['lulus', 'cadangan', 'ditolak'])
            ->orderBy('nomor_pendaftaran');

        if ($search !== '') {
            $query->where('nomor_pendaftaran', 'ilike', '%'.$search.'%');
        }

        $data = $query->limit(500)->get()->map(fn (CalonSantri $c): array => [
            'registration_number' => (string) $c->nomor_pendaftaran,
            'student_name' => (string) $c->nama,
            'origin_school' => (string) ($c->asal_sekolah ?? '-'),
            'guardian_name' => (string) ($c->nama_ayah ?? '-'),
            'academic_year' => (string) ($c->tahun_ajaran ?? '-'),
            'status' => (string) $c->status_pendaftaran,
        ])->values();

        return $this->ok($data);
    }

    /** Direktori santri: ?status=aktif|alumni&gender=L|P. */
    public function students(Request $request): JsonResponse
    {
        abort_unless(tenant(), 404);
        $status = $request->query('status', 'aktif') === 'alumni' ? 'alumni' : 'aktif';
        $gender = in_array($request->query('gender'), ['L', 'P'], true) ? $request->query('gender') : null;

        $data = Cache::remember("pubdir-students-{$status}-{$gender}-".tenant('id'), 300, function () use ($status, $gender) {
            $query = Santri::query()->orderBy('nama');

            if ($status === 'alumni') {
                try {
                    $alumniIds = Alumni::query()->pluck('santri_id');
                } catch (\Throwable) {
                    $alumniIds = collect(); // tabel alumni belum ada di tenant ini
                }
                // Enum santri.status memakai 'lulus' (bukan 'alumni'); tabel
                // alumni menampung detail kelulusan.
                $query->where(fn ($q) => $q->where('status', 'lulus')->orWhereIn('id', $alumniIds));
            } else {
                $query->where('status', 'aktif');
            }
            if ($gender !== null) {
                $query->where('jenis_kelamin', $gender);
            }

            return $query->limit(2000)->get()->map(fn (Santri $s): array => [
                'full_name' => (string) $s->nama,
                'gender' => (string) $s->jenis_kelamin,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values();
        });

        return $this->ok($data);
    }

    /** Staff/pengajar (Payroll Employee aktif): full_name + role=jabatan. */
    public function staff(): JsonResponse
    {
        abort_unless(tenant(), 404);

        $data = Cache::remember('pubdir-staff-'.tenant('id'), 300, function () {
            try {
                return Employee::query()
                    ->where('aktif', true)
                    ->orderBy('nama')
                    ->limit(500)
                    ->get()
                    ->map(fn ($e): array => [
                        'full_name' => (string) $e->nama,
                        'role' => (string) ($e->jabatan ?? ''),
                    ])->values();
            } catch (\Throwable) {
                return collect(); // modul Payroll belum dipakai tenant ini
            }
        });

        return $this->ok($data);
    }

    /** Orang tua/wali (website hanya memakai hitungan — nama saja). */
    public function guardians(): JsonResponse
    {
        abort_unless(tenant(), 404);

        $data = Cache::remember('pubdir-guardians-'.tenant('id'), 300, function () {
            return User::withoutGlobalScopes()
                ->where('tenant_id', tenant('id'))
                ->whereHas('roles', fn ($q) => $q->where('name', 'wali'))
                ->orderBy('name')
                ->limit(2000)
                ->get()
                ->map(fn ($u): array => ['full_name' => (string) $u->name])
                ->values();
        });

        return $this->ok($data);
    }

    private function ok($data): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $data], 200, [
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
