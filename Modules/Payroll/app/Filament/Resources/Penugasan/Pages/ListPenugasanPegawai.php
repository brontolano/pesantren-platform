<?php

namespace Modules\Payroll\Filament\Resources\Penugasan\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\Penugasan\PenugasanPegawaiResource;
use Modules\Payroll\Models\PenugasanPegawai;
use Modules\Payroll\Services\PenugasanImportService;

class ListPenugasanPegawai extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PenugasanPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Penugasan'),
            ...$this->importActions([
                'entitas' => 'penugasan',
                'template' => ['nip', 'jabatan', 'lembaga', 'tanggal_mulai', 'nomor_sk', 'is_primary', 'keterangan'],
                'template_contoh' => ['P001', 'Wali Kelas', 'Madrasah Aliyah', '2024-07-01', 'SK/001/2024', 'ya', ''],
                'export' => fn (Tenant $t) => PenugasanPegawai::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['employee', 'jabatan', 'lembaga'])->lazy()
                    ->map(fn ($p): array => [
                        'nip' => $p->employee?->nip, 'jabatan' => $p->jabatan?->nama, 'lembaga' => $p->lembaga?->nama,
                        'tanggal_mulai' => $p->tanggal_mulai, 'nomor_sk' => $p->nomor_sk,
                        'is_primary' => $p->is_primary ? 'ya' : 'tidak', 'keterangan' => $p->keterangan,
                    ]),
                'label' => 'Import Penugasan',
                'service' => PenugasanImportService::class,
                'directory' => 'imports/payroll/penugasan/uploads',
                'ability' => 'manage_payroll',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nip (wajib, pegawai harus ada), jabatan (nama, wajib & harus ada), lembaga (opsional), tanggal_mulai (wajib), nomor_sk, is_primary (ya/tidak), keterangan. Penugasan sama dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
