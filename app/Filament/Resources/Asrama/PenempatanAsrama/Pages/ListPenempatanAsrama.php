<?php

namespace App\Filament\Resources\Asrama\PenempatanAsrama\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Asrama\PenempatanAsrama\PenempatanAsramaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Asrama\Services\AsramaImportService;
use Modules\Asrama\Services\PenempatanOtomatisService;

class ListPenempatanAsrama extends ListRecords
{
    use HasImportAction;

    protected static string $resource = PenempatanAsramaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('cetakPenempatanBlok')
                ->label('Cetak per Blok')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('tenant.cetak.penempatan-blok', [], false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('manage_santri') ?? false)),
            ...$this->importActions([
                'entitas' => 'asrama',
                'template' => ['nis', 'nama', 'blok', 'kamar', 'kapasitas', 'gender_blok', 'tanggal_masuk', 'keterangan'],
                'template_contoh' => ['2024001', 'Ahmad', 'Blok A', 'A-1', '8', 'putra', '2026-07-14', ''],
                'export' => fn (\App\Models\Tenant $t) => \Modules\Asrama\Models\PenempatanAsrama::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['santri', 'kamar.blok'])->get()
                    ->map(fn ($p): array => ['nis' => $p->santri?->nis, 'nama' => $p->santri?->nama, 'blok' => $p->kamar?->blok?->nama_blok, 'kamar' => $p->kamar?->nomor_kamar, 'kapasitas' => $p->kamar?->kapasitas, 'gender_blok' => $p->kamar?->blok?->gender, 'tanggal_masuk' => $p->tanggal_masuk, 'keterangan' => $p->keterangan]),
                'label' => 'Import Asrama',
                'service' => AsramaImportService::class,
                'directory' => 'imports/asrama/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods berisi kolom: nis, blok, kamar, kapasitas, gender_blok (putra/putri/campur), tanggal_masuk, keterangan. Blok & kamar dibuat otomatis. Aman diulang (dry-run dulu).',
                'extraSummary' => fn (array $s): string => sprintf('Blok baru: %d  •  Kamar baru: %d', $s['created_blok'] ?? 0, $s['created_kamar'] ?? 0),
            ]),
            Action::make('penempatan_otomatis')
                ->label('Penempatan Otomatis')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->modalDescription('Menempatkan santri yang BELUM punya kamar aktif ke kamar cocok (gender + kapasitas + seangkatan). Idempoten — re-run aman.')
                ->schema([
                    TextInput::make('tahun_angkatan')->label('Tahun Angkatan (opsional)')->numeric(),
                    Select::make('gender')->label('Gender (opsional)')->native(false)
                        ->options(['putra' => 'Putra', 'putri' => 'Putri']),
                ])
                ->action(function (array $data): void {
                    $opts = [];
                    if (! empty($data['tahun_angkatan'])) {
                        $opts['tahun_angkatan'] = (int) $data['tahun_angkatan'];
                    }
                    if (! empty($data['gender'])) {
                        $opts['gender'] = $data['gender'];
                    }
                    $hasil = app(PenempatanOtomatisService::class)->tempatkanOtomatis($opts);
                    Notification::make()
                        ->title("{$hasil['ditempatkan']} santri ditempatkan, {$hasil['gagal']} tanpa kamar")
                        ->success()->send();
                }),
        ];
    }
}

