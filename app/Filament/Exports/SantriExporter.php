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
            // Identitas
            ExportColumn::make('nis')->label('NIS'),
            ExportColumn::make('nama')->label('Nama Lengkap'),
            ExportColumn::make('nama_panggilan')->label('Nama Panggilan'),
            ExportColumn::make('jenis_kelamin')->label('L/P'),
            ExportColumn::make('nik')->label('NIK'),
            ExportColumn::make('nisn')->label('NISN'),
            ExportColumn::make('tempat_lahir')->label('Tempat Lahir'),
            ExportColumn::make('tanggal_lahir')->label('Tanggal Lahir'),
            ExportColumn::make('agama')->label('Agama'),
            ExportColumn::make('golongan_darah')->label('Gol. Darah'),

            // Status & akademik
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('is_mukim')->label('Mukim'),
            ExportColumn::make('kelas.nama_kelas')->label('Kelas'),
            ExportColumn::make('tahun_angkatan')->label('Angkatan'),
            ExportColumn::make('asal_sekolah')->label('Asal Sekolah'),
            ExportColumn::make('jenjang_asal')->label('Jenjang Asal'),
            ExportColumn::make('tahun_lulus')->label('Tahun Lulus Asal'),

            // Alamat santri
            ExportColumn::make('alamat')->label('Alamat'),
            ExportColumn::make('rt_rw')->label('RT/RW'),
            ExportColumn::make('desa_kelurahan')->label('Desa/Kelurahan'),
            ExportColumn::make('kecamatan')->label('Kecamatan'),
            ExportColumn::make('kabupaten_kota')->label('Kabupaten/Kota'),
            ExportColumn::make('provinsi')->label('Provinsi'),
            ExportColumn::make('kode_pos')->label('Kode Pos'),

            // Orang tua / wali
            ExportColumn::make('nama_ayah')->label('Nama Ayah'),
            ExportColumn::make('pekerjaan_ayah')->label('Pekerjaan Ayah'),
            ExportColumn::make('no_hp_ayah')->label('No. HP Ayah'),
            ExportColumn::make('nama_ibu')->label('Nama Ibu'),
            ExportColumn::make('pekerjaan_ibu')->label('Pekerjaan Ibu'),
            ExportColumn::make('no_hp_ibu')->label('No. HP Ibu'),
            ExportColumn::make('nama_wali')->label('Nama Wali'),
            ExportColumn::make('hubungan_wali')->label('Hubungan Wali'),
            ExportColumn::make('no_hp_wali')->label('No. HP Wali'),
            ExportColumn::make('email_wali')->label('Email Wali'),
            ExportColumn::make('pembiaya')->label('Pembiaya'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Export {$count} data santri telah selesai.";
    }
}
