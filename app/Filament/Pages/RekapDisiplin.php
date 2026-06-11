<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Modules\Kesantrian\Models\PelanggaranSantri;
use Modules\Kesantrian\Models\Santri;
use Modules\Kesantrian\Services\DisiplinService;

/**
 * WP-09 — Rekap Disiplin: total poin periode aktif per santri (QUERY agregat,
 * bukan kolom) + daftar eskalasi SP periode berjalan.
 *
 * Auto-discovered via TenantPanelProvider->discoverPages().
 */
class RekapDisiplin extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.rekap-disiplin';

    public static function getNavigationLabel(): string
    {
        return 'Rekap Disiplin';
    }

    public function getTitle(): string
    {
        return 'Rekap Disiplin ('.istilah('takzir').')';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        $svc = app(DisiplinService::class);
        $ambang = collect($svc->ambang())
            ->map(fn (array $a): string => "{$a['label']} = {$a['poin']} poin")
            ->implode(', ');

        return 'Periode poin: '.$svc->periodeKey().' ('.$svc->masaBerlaku().') — ambang eskalasi: '.$ambang.'.';
    }

    public function table(Table $table): Table
    {
        $svc = app(DisiplinService::class);
        [$dari, $sampai] = $svc->rentangPeriode();
        $periode = $svc->periodeKey();

        $poinSub = PelanggaranSantri::query()
            ->select(DB::raw('COALESCE(SUM(jenis_pelanggaran.poin), 0)'))
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id')
            ->whereColumn('pelanggaran_santri.santri_id', 'santri.id')
            ->whereIn('pelanggaran_santri.status', PelanggaranSantri::STATUS_POIN_BERLAKU);

        if ($dari !== null && $sampai !== null) {
            $poinSub->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        $eskalasiSub = DB::table('eskalasi_disiplin')
            ->select(DB::raw("STRING_AGG(label, ', ' ORDER BY poin)"))
            ->whereColumn('eskalasi_disiplin.santri_id', 'santri.id')
            ->where('eskalasi_disiplin.periode', $periode);

        return $table
            ->query(
                Santri::query()
                    ->where('santri.status', 'aktif')
                    ->select('santri.*')
                    ->selectSub($poinSub, 'total_poin')
                    ->selectSub($eskalasiSub, 'eskalasi_labels'),
            )
            ->columns([
                TextColumn::make('nis')->label('NIS')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('kelas.nama_kelas')->label('Kelas')->placeholder('—'),
                TextColumn::make('total_poin')
                    ->label('Total Poin Periode Aktif')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= 50 ? 'danger' : ((int) $state > 0 ? 'warning' : 'success')),
                TextColumn::make('eskalasi_labels')
                    ->label('Eskalasi Periode Ini')
                    ->placeholder('—')
                    ->badge()
                    ->color('danger'),
            ])
            ->defaultSort('total_poin', 'desc')
            ->paginated([25, 50]);
    }
}
