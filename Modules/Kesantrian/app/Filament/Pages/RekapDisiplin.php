<?php

namespace Modules\Kesantrian\Filament\Pages;

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
 * Kolom terpisah: poin_pelanggaran (+), poin_prestasi (−), total_poin (net).
 *
 * Auto-discovered via TenantPanelProvider->discoverPages().
 */
class RekapDisiplin extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri & Pengasuhan';

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

    /** Statistik ringkasan semua santri aktif di periode ini (untuk info card). */
    public function getStatsProperty(): array
    {
        $svc = app(DisiplinService::class);
        [$dari, $sampai] = $svc->rentangPeriode();

        $base = PelanggaranSantri::query()
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id')
            ->join('santri', 'santri.id', '=', 'pelanggaran_santri.santri_id')
            ->where('santri.status', 'aktif')
            ->whereIn('pelanggaran_santri.status', PelanggaranSantri::STATUS_POIN_BERLAKU);

        if ($dari !== null && $sampai !== null) {
            $base->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        $totalPelanggaran = (clone $base)->where('jenis_pelanggaran.poin', '>', 0)->sum('jenis_pelanggaran.poin');
        $totalPrestasi = abs((clone $base)->where('jenis_pelanggaran.poin', '<', 0)->sum('jenis_pelanggaran.poin'));
        $totalNet = $totalPelanggaran - $totalPrestasi;

        return [
            'pelanggaran' => (int) $totalPelanggaran,
            'prestasi' => (int) $totalPrestasi,
            'net' => (int) $totalNet,
        ];
    }

    public function table(Table $table): Table
    {
        $svc = app(DisiplinService::class);
        [$dari, $sampai] = $svc->rentangPeriode();
        $periode = $svc->periodeKey();

        // Subquery poin pelanggaran (poin > 0)
        $pelanggaranSub = PelanggaranSantri::query()
            ->select(DB::raw('COALESCE(SUM(jenis_pelanggaran.poin), 0)'))
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id')
            ->whereColumn('pelanggaran_santri.santri_id', 'santri.id')
            ->whereIn('pelanggaran_santri.status', PelanggaranSantri::STATUS_POIN_BERLAKU)
            ->where('jenis_pelanggaran.poin', '>', 0);

        if ($dari !== null && $sampai !== null) {
            $pelanggaranSub->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        // Subquery poin prestasi (poin < 0) — nilai absolut (positif)
        $prestasiSub = PelanggaranSantri::query()
            ->select(DB::raw('COALESCE(ABS(SUM(jenis_pelanggaran.poin)), 0)'))
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id')
            ->whereColumn('pelanggaran_santri.santri_id', 'santri.id')
            ->whereIn('pelanggaran_santri.status', PelanggaranSantri::STATUS_POIN_BERLAKU)
            ->where('jenis_pelanggaran.poin', '<', 0);

        if ($dari !== null && $sampai !== null) {
            $prestasiSub->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        // Total net (pelanggaran + prestasi gabungan, bisa negatif jika banyak prestasi)
        $totalSub = PelanggaranSantri::query()
            ->select(DB::raw('COALESCE(SUM(jenis_pelanggaran.poin), 0)'))
            ->join('jenis_pelanggaran', 'jenis_pelanggaran.id', '=', 'pelanggaran_santri.jenis_pelanggaran_id')
            ->whereColumn('pelanggaran_santri.santri_id', 'santri.id')
            ->whereIn('pelanggaran_santri.status', PelanggaranSantri::STATUS_POIN_BERLAKU);

        if ($dari !== null && $sampai !== null) {
            $totalSub->whereBetween('pelanggaran_santri.waktu_kejadian', [$dari, $sampai]);
        }

        // Detail catatan: semua pelanggaran & prestasi (nama jenis + poin + status)
        $detailSub = DB::table('pelanggaran_santri as ps')
            ->select(DB::raw(
                "STRING_AGG(jp.nama || ' (' || jp.poin || ')', ' | ' ORDER BY ps.waktu_kejadian DESC)"
            ))
            ->join('jenis_pelanggaran as jp', 'jp.id', '=', 'ps.jenis_pelanggaran_id')
            ->whereColumn('ps.santri_id', 'santri.id')
            ->whereIn('ps.status', PelanggaranSantri::STATUS_POIN_BERLAKU);

        if ($dari !== null && $sampai !== null) {
            $detailSub->whereBetween('ps.waktu_kejadian', [$dari, $sampai]);
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
                    ->selectSub($pelanggaranSub, 'poin_pelanggaran')
                    ->selectSub($prestasiSub, 'poin_prestasi')
                    ->selectSub($totalSub, 'total_poin')
                    ->selectSub($detailSub, 'detail_catatan')
                    ->selectSub($eskalasiSub, 'eskalasi_labels'),
            )
            ->columns([
                TextColumn::make('nis')->label('NIS')->searchable(),
                TextColumn::make('nama')->label('Nama')->searchable()->wrap(),
                TextColumn::make('kelas.nama_kelas')->label('Kelas')->placeholder('—'),

                TextColumn::make('poin_pelanggaran')
                    ->label('⊕ Pelanggaran')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn ($state): string => '+'.((int) $state)),

                TextColumn::make('poin_prestasi')
                    ->label('⊖ Prestasi')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state): string => '-'.((int) $state)),

                TextColumn::make('total_poin')
                    ->label('Total Bersih')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= 50 ? 'danger' : ((int) $state > 0 ? 'warning' : 'success')),

                TextColumn::make('detail_catatan')
                    ->label('Detail Catatan')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn ($state): ?string => $state),

                TextColumn::make('eskalasi_labels')
                    ->label('Eskalasi')
                    ->placeholder('—')
                    ->badge()
                    ->color('danger'),
            ])
            ->defaultSort('total_poin', 'desc')
            ->paginated([25, 50]);
    }
}
