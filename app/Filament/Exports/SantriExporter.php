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
        // PERSIS field form "Buat Santri" & template import (urutan & header selaras
        // agar bisa round-trip: export → koreksi di Excel → impor ulang).
        return [
            // Data Pribadi
            ExportColumn::make('nis')->label('nis'),
            ExportColumn::make('nama')->label('nama'),
            ExportColumn::make('tanggal_lahir')->label('tanggal_lahir'),
            ExportColumn::make('tempat_lahir')->label('tempat_lahir'),
            ExportColumn::make('jenis_kelamin')->label('jenis_kelamin'),
            ExportColumn::make('agama')->label('agama'),
            ExportColumn::make('nik')->label('nik'),
            ExportColumn::make('nisn')->label('nisn'),
            ExportColumn::make('asal_sekolah')->label('asal_sekolah'),
            // Alamat Santri
            ExportColumn::make('alamat')->label('alamat'),
            ExportColumn::make('rt_rw')->label('rt_rw'),
            ExportColumn::make('kode_pos')->label('kode_pos'),
            ExportColumn::make('provinsi')->label('provinsi'),
            ExportColumn::make('kabupaten_kota')->label('kabupaten_kota'),
            ExportColumn::make('kecamatan')->label('kecamatan'),
            ExportColumn::make('desa_kelurahan')->label('desa_kelurahan'),
            // Data Ayah (+ Alamat Ayah)
            ExportColumn::make('nama_ayah')->label('nama_ayah'),
            ExportColumn::make('pekerjaan_ayah')->label('pekerjaan_ayah'),
            ExportColumn::make('no_hp_ayah')->label('no_hp_ayah'),
            ExportColumn::make('alamat_ayah')->label('alamat_ayah'),
            ExportColumn::make('rt_rw_ayah')->label('rt_rw_ayah'),
            ExportColumn::make('kode_pos_ayah')->label('kode_pos_ayah'),
            ExportColumn::make('provinsi_ayah')->label('provinsi_ayah'),
            ExportColumn::make('kabupaten_kota_ayah')->label('kabupaten_kota_ayah'),
            ExportColumn::make('kecamatan_ayah')->label('kecamatan_ayah'),
            ExportColumn::make('desa_kelurahan_ayah')->label('desa_kelurahan_ayah'),
            // Data Ibu (+ Alamat Ibu)
            ExportColumn::make('nama_ibu')->label('nama_ibu'),
            ExportColumn::make('pekerjaan_ibu')->label('pekerjaan_ibu'),
            ExportColumn::make('no_hp_ibu')->label('no_hp_ibu'),
            ExportColumn::make('alamat_ibu')->label('alamat_ibu'),
            ExportColumn::make('rt_rw_ibu')->label('rt_rw_ibu'),
            ExportColumn::make('kode_pos_ibu')->label('kode_pos_ibu'),
            ExportColumn::make('provinsi_ibu')->label('provinsi_ibu'),
            ExportColumn::make('kabupaten_kota_ibu')->label('kabupaten_kota_ibu'),
            ExportColumn::make('kecamatan_ibu')->label('kecamatan_ibu'),
            ExportColumn::make('desa_kelurahan_ibu')->label('desa_kelurahan_ibu'),
            // Akun Wali (+ Alamat Wali / Domisili Keluarga)
            ExportColumn::make('no_hp_wali')->label('no_hp_wali'),
            ExportColumn::make('email_wali')->label('email_wali'),
            ExportColumn::make('alamat_wali')->label('alamat_wali'),
            ExportColumn::make('rt_rw_wali')->label('rt_rw_wali'),
            ExportColumn::make('kode_pos_wali')->label('kode_pos_wali'),
            ExportColumn::make('provinsi_wali')->label('provinsi_wali'),
            ExportColumn::make('kabupaten_kota_wali')->label('kabupaten_kota_wali'),
            ExportColumn::make('kecamatan_wali')->label('kecamatan_wali'),
            ExportColumn::make('desa_kelurahan_wali')->label('desa_kelurahan_wali'),
            // Akademik
            ExportColumn::make('kelas.nama_kelas')->label('kelas'),
            ExportColumn::make('tahun_angkatan')->label('tahun_angkatan'),
            ExportColumn::make('status')->label('status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);

        return "Export {$count} data santri telah selesai.";
    }
}
