<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ModuleGate;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Modules\Payroll\Models\KontrakKerja;

/**
 * WP-16 — Kontrak/SK aktif yang `selesai` ≤ X hari lagi.
 * X = Setting per-tenant `payroll.kontrak_reminder_hari` (default 60).
 */
class KontrakAkanHabisWidget extends TableWidget
{
    use ModuleGate;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::moduleVisible('payroll')
            && (auth()->user()?->can('run_payroll') ?? false)
            && KontrakKerja::query()->mendekatiHabis()->exists();
    }

    public function table(Table $table): Table
    {
        $hari = KontrakKerja::reminderHari();

        return $table
            ->heading("Kontrak/SK Akan Habis (≤ {$hari} hari)")
            ->query(KontrakKerja::query()->mendekatiHabis()->with('employee')->orderBy('selesai'))
            ->paginated(false)
            ->columns([
                TextColumn::make('employee.nama')->label('Pegawai'),
                TextColumn::make('jenis')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => KontrakKerja::JENIS[$state] ?? $state),
                TextColumn::make('nomor')->label('Nomor'),
                TextColumn::make('selesai')->label('Berakhir')->date('d M Y')
                    ->color(fn (KontrakKerja $r): string => $r->selesai?->isPast() ? 'danger' : 'warning'),
            ])
            ->emptyStateHeading('Tidak ada kontrak mendekati habis');
    }
}
