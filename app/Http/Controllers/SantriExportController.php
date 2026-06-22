<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SantriExportController extends Controller
{
    public function csv(Request $request): StreamedResponse
    {
        $this->authorizeExport();

        $kelas = $this->getKelas($request);
        $santri = $this->getSantriQuery($kelas->id, $request->string('status')->toString())->get();
        $filename = 'santri-' . str($kelas->nama_kelas)->slug() . '.csv';

        return response()->streamDownload(function () use ($santri): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'NIS',
                'Nama Lengkap',
                'L/P',
                'Tanggal Lahir',
                'Kelas',
                'Status',
                'Angkatan',
                'Nama Ayah',
                'Nama Ibu',
                'No. HP Wali',
            ]);

            foreach ($santri as $item) {
                fputcsv($handle, [
                    $item->nis,
                    $item->nama,
                    $item->jenis_kelamin,
                    optional($item->tanggal_lahir)?->format('Y-m-d'),
                    optional($item->kelas)->nama_kelas,
                    $item->status,
                    $item->tahun_angkatan,
                    $item->nama_ayah,
                    $item->nama_ibu,
                    $item->no_hp_wali,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Unduh template Excel/CSV kosong dengan header yang dikenali importer.
     * Header memakai nama kanonik HEADER_ALIASES SantriImportService.
     */
    public function template(): StreamedResponse
    {
        $this->authorizeExport();

        $headers = [
            'nis', 'nama', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'nik', 'nisn', 'agama', 'kelas', 'tingkat', 'jurusan',
            'tahun_angkatan', 'status', 'asal_sekolah',
            'alamat', 'rt_rw', 'desa_kelurahan', 'kecamatan', 'kabupaten_kota', 'provinsi', 'kode_pos',
            'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu',
            'nama_wali', 'no_hp_wali', 'email_wali',
        ];

        // Satu baris contoh agar staf paham format isian (terutama tanggal & L/P).
        $contoh = [
            '24250001', 'Ahmad Fulan', 'L', 'Bandung', '15/01/2010',
            '', '', 'Islam', '1-A', '1', '',
            '2024', 'aktif', 'SDN Contoh',
            'Jl. Contoh No. 1', '001/002', 'Sukamaju', 'Cibiru', 'Kota Bandung', 'Jawa Barat', '40123',
            'Bapak Fulan', 'Wiraswasta', 'Ibu Fulanah', 'Ibu Rumah Tangga',
            'Bapak Fulan', '081234567890', 'wali@example.com',
        ];

        return response()->streamDownload(function () use ($headers, $contoh): void {
            $handle = fopen('php://output', 'w');
            // BOM agar Excel membaca UTF-8 dengan benar.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            fputcsv($handle, $contoh);
            fclose($handle);
        }, 'template-import-santri.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function print(Request $request)
    {
        $this->authorizeExport();

        $kelas = $this->getKelas($request);
        $status = $request->string('status')->toString();
        $santri = $this->getSantriQuery($kelas->id, $status)->get();

        return response()->view('exports.santri-per-kelas-print', [
            'tenantName' => tenant('name') ?? config('app.name'),
            'kelas' => $kelas,
            'status' => $status,
            'santri' => $santri,
            'printedAt' => now(),
        ]);
    }

    private function authorizeExport(): void
    {
        abort_unless(auth()->user()?->canAny(['view_santri', 'manage_santri']), 403);
    }

    private function getKelas(Request $request): Kelas
    {
        $kelasId = (int) $request->integer('kelas_id');

        // Tanpa kelas_id valid (mis. URL diakses langsung tanpa lewat tombol
        // export), kembalikan ke daftar santri dengan pesan ramah — bukan 404
        // mentah. Alur normal selalu mengirim kelas_id (field required).
        $kelas = $kelasId > 0 ? Kelas::query()->find($kelasId) : null;
        if ($kelas === null) {
            abort(redirect()->back(303, [], '/admin/santri/santris')
                ->with('error', 'Pilih kelas terlebih dahulu untuk mengekspor data santri.'));
        }

        return $kelas;
    }

    private function getSantriQuery(int $kelasId, string $status)
    {
        return Santri::query()
            ->with('kelas')
            ->where('kelas_id', $kelasId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('nama');
    }
}
