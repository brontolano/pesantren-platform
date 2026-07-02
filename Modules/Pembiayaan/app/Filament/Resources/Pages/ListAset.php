<?php

namespace Modules\Pembiayaan\Filament\Resources\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pembiayaan\Filament\Resources\AsetResource;
use Modules\Pembiayaan\Models\Aset;
use Modules\Pembiayaan\Services\AsetImportService;

class ListAset extends ListRecords
{
    use HasImportAction;

    protected static string $resource = AsetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...$this->importActions([
                'entitas' => 'aset',
                'template' => ['nama_aset', 'jenis', 'kategori', 'tanggal_perolehan', 'nilai', 'lokasi', 'nomor_sertifikat', 'is_wakaf', 'metode_penyusutan', 'umur_manfaat_bulan', 'akumulasi_penyusutan'],
                'template_contoh' => ['Tanah Pondok Blok A', 'tanah', 'tanah', '2018-05-10', '1.500.000.000', 'Kp. Sukamaju', 'AH.123456', 'ya', 'none', '', ''],
                'export' => fn (Tenant $t) => Aset::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->orderBy('nama_aset')->lazy()
                    ->map(fn ($a): array => [
                        'nama_aset' => $a->nama_aset,
                        'jenis' => $a->jenis,
                        'kategori' => $a->kategori,
                        'tanggal_perolehan' => optional($a->tanggal_perolehan)->toDateString(),
                        'nilai' => $a->nilai,
                        'lokasi' => $a->lokasi,
                        'nomor_sertifikat' => $a->nomor_sertifikat,
                        'is_wakaf' => $a->is_wakaf ? 'ya' : 'tidak',
                        'metode_penyusutan' => $a->metode_penyusutan,
                        'umur_manfaat_bulan' => $a->umur_manfaat_bulan,
                        'akumulasi_penyusutan' => $a->akumulasi_penyusutan,
                    ]),
                'label' => 'Import Aset',
                'service' => AsetImportService::class,
                'directory' => 'imports/pembiayaan/aset/uploads',
                'ability' => 'manage_pembiayaan',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama_aset (wajib), jenis, kategori, tanggal_perolehan, nilai (boleh format 1.500.000), lokasi, nomor_sertifikat, is_wakaf (ya/tidak), metode_penyusutan (none/garis_lurus/saldo_menurun), umur_manfaat_bulan, akumulasi_penyusutan. Aset wakaf otomatis tanpa penyusutan. Aset yang sudah ada dilewati (tak ditimpa); impor TIDAK memicu jurnal/penyusutan. Dry-run dulu.',
            ]),
        ];
    }
}
