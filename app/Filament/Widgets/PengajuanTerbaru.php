<?php

namespace App\Filament\Widgets;

use App\Support\PanelTheme;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Modules\Core\Models\ApprovalRequest;

/**
 * Pengajuan Terbaru (F1 Approval Workflow) — penekanan KEUANGAN tema
 * Baitul Maal (referensi desain TU Pesantren). Hanya tampil saat tema
 * baitul-maal aktif.
 */
class PengajuanTerbaru extends TableWidget
{
    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 4;

    protected static ?string $heading = 'Pengajuan Terbaru';

    public static function canView(): bool
    {
        return PanelTheme::theme() === 'baitul-maal'
            && (auth()->user()?->can('view_laporan_keuangan') ?? false)
            && class_exists(ApprovalRequest::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ApprovalRequest::query()->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->date('d M Y'),
                TextColumn::make('approvable_type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('current_step')
                    ->label('Tahap'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        default => 'warning', // menunggu / proses
                    }),
            ])
            ->emptyStateHeading('Belum ada pengajuan')
            ->emptyStateDescription('Pengajuan persetujuan (belanja, jurnal, dll.) akan tampil di sini.');
    }
}
