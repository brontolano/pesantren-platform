<?php

namespace App\Filament\Resources\Tahfidz\Halaqah\Pages;

use App\Filament\Concerns\HasImportAction;
use App\Filament\Resources\Tahfidz\Halaqah\HalaqahResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Tahfidz\Models\Halaqah;
use Modules\Tahfidz\Models\HalaqahSantri;
use Modules\Tahfidz\Services\HalaqahImportService;
use Modules\Tahfidz\Services\HalaqahSantriImportService;

class ListHalaqah extends ListRecords
{
    use HasImportAction;

    protected static string $resource = HalaqahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Halaqah'),
            Action::make('cetakHalaqah')
                ->label('Cetak Daftar')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('tenant.cetak.halaqah', [], false))
                ->openUrlInNewTab()
                ->visible(fn (): bool => tenancy()->initialized && (auth()->user()?->can('manage_santri') ?? false)),
            ...$this->importActions([
                'entitas' => 'halaqah',
                'template' => ['nama', 'musyrif', 'jenis'],
                'template_contoh' => ['Halaqah A', 'musyrif@contoh.test', 'tahfidz'],
                'export' => fn (Tenant $t) => Halaqah::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->with('musyrif')->orderBy('nama')->get()
                    ->map(fn ($h): array => ['nama' => $h->nama, 'musyrif' => $h->musyrif?->email, 'jenis' => $h->jenis]),
                'label' => 'Import Halaqah',
                'service' => HalaqahImportService::class,
                'directory' => 'imports/tahfidz/halaqah/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nama (wajib), musyrif (email/nama akun musyrif, wajib), jenis (tahfidz/kitab/diniyah). Nama yang sudah ada dilewati. Dry-run dulu.',
            ]),
            ...$this->importActions([
                'entitas' => 'keanggotaan_halaqah',
                'label' => 'Import Anggota',
                'template' => ['nis', 'halaqah', 'tanggal_gabung'],
                'template_contoh' => ['2024001', 'Halaqah A', '2026-01-15'],
                'export' => fn (Tenant $t) => HalaqahSantri::withoutGlobalScopes()
                    ->where('tenant_id', $t->id)->whereNull('left_at')->with(['santri', 'halaqah'])
                    ->get()
                    ->map(fn ($k): array => [
                        'nis' => $k->santri?->nis,
                        'halaqah' => $k->halaqah?->nama,
                        'tanggal_gabung' => optional($k->joined_at)->toDateString(),
                    ])
                    ->filter(fn ($r): bool => filled($r['nis']) && filled($r['halaqah'])),
                'service' => HalaqahSantriImportService::class,
                'directory' => 'imports/tahfidz/keanggotaan/uploads',
                'ability' => 'manage_santri',
                'petunjuk' => 'Unggah .xlsx/.csv/.ods kolom: nis (wajib), halaqah (nama halaqah, wajib), tanggal_gabung (opsional, default hari ini). Santri yang sudah jadi anggota aktif halaqah itu dilewati. Tidak menutup keanggotaan lain. Dry-run dulu.',
            ]),
        ];
    }
}
