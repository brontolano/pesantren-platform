<?php

namespace Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Filament\Resources\HafalanTahfidzResource;
use Modules\Tahfidz\Models\HafalanTahfidz;
use Modules\Tahfidz\Services\HafalanImportService;

class ListHafalanTahfidz extends ListRecords
{
    use HasImportAction;

    protected static string $resource = HafalanTahfidzResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('cetakHafalan')
                ->label('Cetak Progres')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('tenant.cetak.hafalan', [], false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('manage_santri') ?? false)),
            ...$this->importActions([
                'entitas' => 'hafalan',
                'template' => ['nis', 'halaqah', 'surah', 'ayat_awal', 'ayat_akhir', 'status', 'tanggal_target', 'tanggal_selesai', 'catatan'],
                'template_contoh' => ['2024001', 'Halaqah A', 'Al-Baqarah', '1', '5', 'berjalan', '', '', ''],
                'export' => fn (Tenant $t) => HafalanTahfidz::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with(['santri', 'halaqah'])->lazy()
                    ->map(fn ($h): array => ['nis' => $h->santri?->nis, 'halaqah' => $h->halaqah?->nama, 'surah' => $h->surah, 'ayat_awal' => $h->ayat_awal, 'ayat_akhir' => $h->ayat_akhir, 'status' => $h->status, 'tanggal_target' => $h->tanggal_target, 'tanggal_selesai' => $h->tanggal_selesai, 'catatan' => $h->catatan]),
                'label' => 'Import Hafalan',
                'service' => HafalanImportService::class,
                'directory' => 'imports/tahfidz/hafalan/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib), halaqah (nama, opsional), surah, ayat_awal, ayat_akhir, status (direncanakan/berjalan/selesai), tanggal_target, tanggal_selesai, catatan. Baris (santri+surah+ayat) yang sama dilewati. Dry-run dulu.',
            ]),
        ];
    }
}
