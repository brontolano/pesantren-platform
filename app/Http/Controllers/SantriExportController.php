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

        return Kelas::query()->findOrFail($kelasId);
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
