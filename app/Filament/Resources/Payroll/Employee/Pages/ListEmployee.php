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
            ...$this->importActions([
                'entitas' => 'pegawai',
                'template' => ['nip', 'nama', 'jabatan', 'status_kepegawaian', 'nik', 'nuptk', 'tanggal_masuk', 'email'],
                'template_contoh' => ['P001', 'Ustadz Ali', 'Guru', 'tetap', '', '', '2020-07-01', 'ali@contoh.test'],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Payroll\Models\Employee::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('user')->orderBy('nama')->get()
                    ->map(fn ($e): array => ['nip' => $e->nip, 'nama' => $e->nama, 'jabatan' => $e->jabatan, 'status_kepegawaian' => $e->status_kepegawaian, 'nik' => $e->nik, 'nuptk' => $e->nuptk, 'tanggal_masuk' => $e->tanggal_masuk?->format('Y-m-d'), 'email' => $e->user?->email]),
                'label' => 'Import Pegawai',
                'service' => PegawaiImportService::class,
                'directory' => 'imports/payroll/pegawai/uploads',
                'ability' => 'manage_payroll',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nip (wajib, unik), nama (wajib), jabatan, status_kepegawaian (tetap/honorer/kontrak), nik, nuptk, tanggal_masuk, email (untuk tautkan akun login). NIP yang sudah ada dilewati. Aman diulang (dry-run dulu).',
            ]),
        ];
    }
}
