<?php

namespace Modules\Payroll\Filament\Resources\Employee\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Filament\Resources\Employee\EmployeeResource;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Services\PegawaiImportService;

class ListEmployee extends ListRecords
{
    use HasImportAction;

    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Pegawai'),
            Action::make('cetakPegawai')
                ->label('Cetak Daftar')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('tenant.cetak.pegawai', [], false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('run_payroll') ?? false)),
            ...$this->importActions([
                'entitas' => 'pegawai',
                // Template selaras field skalar form panel admin (Data Pegawai +
                // Identitas EMIS). Struktur gaji & arsip sertifikasi = repeater,
                // dikelola via UI terpisah (tak bisa template datar). Hanya nip & nama WAJIB.
                'template' => [
                    'nip', 'nama', 'jabatan', 'status_kepegawaian', 'email',
                    'npwp', 'ptkp_status', 'tanggal_masuk', 'tanggal_selesai', 'aktif',
                    'nik', 'nuptk', 'nrg', 'pendidikan_terakhir', 'is_mukim', 'asal_alumni',
                ],
                'template_contoh' => [
                    'P001', 'Ustadz Ali', 'Guru', 'tetap', 'ali@contoh.test',
                    '12.345.678.9-012.000', 'TK0', '2020-07-01', '', 'Ya',
                    '3273010101800001', '1234567890123456', '987654321', 'S1', 'Ya', 'Tidak',
                ],
                'export' => fn (Tenant $t) => Employee::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('user')->orderBy('nama')->lazy()
                    ->map(fn ($e): array => [
                        'nip' => $e->nip, 'nama' => $e->nama, 'jabatan' => $e->jabatan,
                        'status_kepegawaian' => $e->status_kepegawaian, 'email' => $e->user?->email,
                        'npwp' => $e->npwp, 'ptkp_status' => $e->ptkp_status,
                        'tanggal_masuk' => $e->tanggal_masuk?->format('Y-m-d'),
                        'tanggal_selesai' => $e->tanggal_selesai?->format('Y-m-d'),
                        'aktif' => $e->aktif ? 'Ya' : 'Tidak',
                        'nik' => $e->nik, 'nuptk' => $e->nuptk, 'nrg' => $e->nrg,
                        'pendidikan_terakhir' => $e->pendidikan_terakhir,
                        'is_mukim' => $e->is_mukim ? 'Ya' : 'Tidak',
                        'asal_alumni' => $e->asal_alumni ? 'Ya' : 'Tidak',
                    ]),
                'label' => 'Import Pegawai',
                'service' => PegawaiImportService::class,
                'directory' => 'imports/payroll/pegawai/uploads',
                'ability' => 'run_payroll',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods. WAJIB: nip (unik), nama. Opsional: jabatan, status_kepegawaian (tetap/honorer/kontrak), email (tautkan akun login), npwp, ptkp_status (mis. TK0/K1), tanggal_masuk, tanggal_selesai, nik, nuptk, nrg, pendidikan_terakhir, dan kolom Ya/Tidak: aktif, is_mukim, asal_alumni. NIP yang sudah ada dilewati. Aman diulang (dry-run dulu). Struktur gaji & sertifikasi diisi lewat form pegawai, bukan import.',
            ]),
        ];
    }
}
