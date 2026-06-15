<?php
namespace App\Filament\Resources\Payroll\Employee\Pages;
use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Payroll\Employee\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Services\PegawaiImportService;
class ListEmployee extends ListRecords
{
    use HasImportAction;

    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pegawai'),
            $this->buildImportAction([
                'entitas' => 'pegawai',
                'label' => 'Import Pegawai',
                'service' => PegawaiImportService::class,
                'directory' => 'imports/payroll/pegawai/uploads',
                'ability' => 'manage_payroll',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nip (wajib, unik), nama (wajib), jabatan, status_kepegawaian (tetap/honorer/kontrak), nik, nuptk, tanggal_masuk, email (untuk tautkan akun login). NIP yang sudah ada dilewati. Aman diulang (dry-run dulu).',
            ]),
        ];
    }
}
