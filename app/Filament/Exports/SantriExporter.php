<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Kesantrian\Models\Santri;

class SantriExporter extends Exporter
{
    protected static ?string $model = Santri::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('nis')->label('NIS'),
            ExportColumn::make('nama')->label('Nama Lengkap'),
            ExportColumn::make('jenis_kelamin')->label('L/P'),
            ExportColumn::make('tempat_lahir')->label('Tempat Lahir'),
            ExportColumn::make('tanggal_lahir')->label('Tanggal Lahir'),
            ExportColumn::make('kelas.nama_kelas')->label('Kelas'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('tahun_angkatan')->label('Angkatan'),
            ExportColumn::make('asal_sekolah')->label('Asal Sekolah'),
            ExportColumn::make('nama_ayah')->label('Nama Ayah'),
            ExportColumn::make('nama_ibu')->label('Nama Ibu'),
            ExportColumn::make('no_hp_wali')->label('No. HP Wali'),
            ExportColumn::make('alamat')->label('Alamat'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Export {$count} data santri telah selesai.";
    }
}
