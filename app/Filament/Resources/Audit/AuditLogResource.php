<?php

namespace App\Filament\Resources\Audit;

use App\Filament\Resources\Audit\AuditLog\Pages\ListAuditLog;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\AuditLog;

/**
 * F2 — Penampil jejak audit (read-only). Gated panel tenant + lihat_audit.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationLabel = 'Jejak Audit';

    protected static ?string $modelLabel = 'Jejak Audit';

    protected static ?string $pluralModelLabel = 'Jejak Audit';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 31;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('lihat_audit') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false; // append-only
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('aksi')->label('Aksi')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'info',
                        'approve', 'post' => 'primary',
                        'delete', 'reverse' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('auditable_type')->label('Objek')
                    ->formatStateUsing(fn (?string $state, AuditLog $r): string => class_basename((string) $state).' #'.$r->auditable_id)
                    ->searchable(),
                TextColumn::make('actor.name')->label('Oleh')->placeholder('sistem'),
                TextColumn::make('perubahan')->label('Perubahan')
                    ->state(fn (AuditLog $r): string => static::ringkasPerubahan($r))
                    ->wrap()->limit(80),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('aksi')->options([
                    'create' => 'Buat', 'update' => 'Ubah', 'delete' => 'Hapus',
                    'approve' => 'Setujui', 'post' => 'Posting', 'reverse' => 'Pembalik',
                ]),
                SelectFilter::make('auditable_type')->label('Objek')
                    ->options(fn (): array => AuditLog::query()->distinct()->pluck('auditable_type', 'auditable_type')
                        ->mapWithKeys(fn ($v) => [$v => class_basename((string) $v)])->all()),
                Filter::make('tanggal')->schema([
                    DatePicker::make('dari')->label('Dari'),
                    DatePicker::make('sampai')->label('Sampai'),
                ])->query(fn (Builder $q, array $data): Builder => $q
                    ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                    ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))),
            ])
            ->actions([]);
    }

    private static function ringkasPerubahan(AuditLog $r): string
    {
        $p = $r->perubahan ?? [];
        $after = $p['after'] ?? [];
        if ($after === []) {
            return $r->aksi === 'delete' ? 'dihapus' : '—';
        }
        $keys = array_keys($after);

        return count($keys) <= 4 ? implode(', ', $keys) : count($keys).' field diubah';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLog::route('/'),
        ];
    }
}
