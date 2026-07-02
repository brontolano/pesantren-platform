<?php

namespace Modules\Keuangan\Filament\Resources\TagihanRutin\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Keuangan\Filament\Resources\TagihanRutin\TagihanRutinResource;
use Modules\Keuangan\Models\TagihanRutin;
use Modules\Keuangan\Support\SyahriahKomponen;

class ListTagihanRutin extends ListRecords
{
    protected static string $resource = TagihanRutinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // #332: template syahriah multi-komponen siap-pakai — preset komponen
            // umum pesantren (makan, listrik, madrasah diniyah, kas kamar,
            // tabungan wajib, bisyaroh) yang bisa diedit sebelum disimpan.
            Action::make('buatDariTemplate')
                ->label('Buat dari Template Syahriah')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('manage_tagihan') ?? false)
                ->modalHeading('Template Syahriah Multi-Komponen')
                ->modalDescription('Syahriah bulanan berlapis dalam satu tagihan. Sesuaikan nama & nominal komponen di bawah — total tagihan dihitung otomatis dari jumlah komponen. Komponen "Masuk Tabungan" disetor otomatis ke tabungan santri saat lunas.')
                ->modalSubmitActionLabel('Simpan Aturan Syahriah')
                ->form([
                    TextInput::make('nama')->label('Nama Aturan')->required()
                        ->default('Syahriah Bulanan'),
                    Select::make('jenis_tagihan')->label('Jenis Tagihan')->native(false)
                        ->options(TagihanRutinResource::JENIS)->default('spp')->required(),
                    TagihanRutinResource::komponenRepeater()
                        ->default(SyahriahKomponen::templateSyahriah()),
                    TextInput::make('tanggal_terbit')->label('Tanggal Terbit (tiap bulan)')
                        ->numeric()->minValue(1)->maxValue(28)->default(1)->required(),
                    TextInput::make('tempo_hari')->label('Jatuh Tempo (hari)')
                        ->numeric()->minValue(0)->maxValue(60)->default(10)->required(),
                ])
                ->action(function (array $data): void {
                    $komponen = SyahriahKomponen::normalize($data['komponen'] ?? []);

                    if ($komponen === []) {
                        Notification::make()->title('Minimal satu komponen harus diisi.')->danger()->send();

                        return;
                    }

                    $aturan = TagihanRutin::create([
                        'nama' => $data['nama'],
                        'jenis_tagihan' => $data['jenis_tagihan'],
                        'frekuensi' => 'bulanan',
                        'nominal' => SyahriahKomponen::total($komponen),
                        'komponen' => $komponen,
                        'target_tipe' => 'semua',
                        'tanggal_terbit' => (int) $data['tanggal_terbit'],
                        'tempo_hari' => (int) $data['tempo_hari'],
                        'aktif' => true,
                    ]);

                    Notification::make()
                        ->title('Aturan syahriah dibuat')
                        ->body(count($komponen).' komponen, total Rp '.number_format((float) $aturan->nominal, 0, ',', '.').'. Target: semua santri aktif — ubah lewat Edit bila perlu.')
                        ->success()
                        ->send();
                }),
            CreateAction::make()->label('Tambah Tagihan Rutin'),
        ];
    }
}
