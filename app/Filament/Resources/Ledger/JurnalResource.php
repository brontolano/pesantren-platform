<?php

namespace App\Filament\Resources\Ledger;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Ledger\Pages\ListJurnal;
use App\Services\AuditLogger;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Ledger\Models\Jurnal;
use Modules\Ledger\Services\JurnalService;

class JurnalResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Jurnal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Jurnal Umum';

    protected static ?string $modelLabel = 'Jurnal';

    protected static ?string $pluralModelLabel = 'Jurnal Umum';

    protected static string|\UnitEnum|null $navigationGroup = 'Akuntansi';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ledger') && (auth()->user()?->canAny(['view_ledger', 'manage_ledger']) ?? false);
    }

    // Jurnal immutable — tidak ada create/edit/delete via UI (Pagar #2).
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
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nomor')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('tanggal')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('deskripsi')->label('Deskripsi')->limit(40)->wrap(),
                TextColumn::make('tipe_sumber')->label('Sumber')->badge(),
                TextColumn::make('baris_sum_debit')->label('Nilai')
                    ->money('IDR')
                    ->state(fn (Jurnal $record): float => (float) $record->baris()->sum('debit')),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'posted' => 'success',
                        'reversed' => 'warning',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'posted' => 'Posted', 'reversed' => 'Reversed', 'draft' => 'Draft',
                ]),
                SelectFilter::make('tipe_sumber')->options([
                    'manual' => 'Manual', 'syahriyah' => 'Syahriyah', 'belanja' => 'Belanja',
                    'payroll' => 'Payroll', 'jasa' => 'Jasa', 'kebajikan' => 'Dana Kebajikan',
                    'pembalik' => 'Pembalik',
                ]),
            ])
            ->actions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Jurnal $record): string => 'Jurnal '.$record->nomor)
                    ->modalContent(fn (Jurnal $record) => view('filament.ledger.jurnal-detail', [
                        'jurnal' => $record->load(['baris.akun', 'baris.divisi', 'baris.sumberDana']),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('pembalik')
                    ->label('Jurnal Pembalik')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Buat Jurnal Pembalik')
                    ->modalDescription('Jurnal asal tidak diubah/dihapus — dibuat jurnal kebalikannya untuk koreksi.')
                    ->schema([
                        Textarea::make('alasan')->label('Alasan koreksi')->required()->rows(2),
                    ])
                    ->visible(fn (Jurnal $record): bool => $record->status === 'posted' && (auth()->user()?->can('manage_ledger') ?? false))
                    ->action(function (Jurnal $record, array $data): void {
                        $pembalik = app(JurnalService::class)->reverseJurnal($record, $data['alasan'] ?? null, auth()->id());
                        app(AuditLogger::class)->log(
                            action: 'ledger.jurnal_pembalik',
                            user: auth()->user(),
                            subject: $record,
                            metadata: ['asal' => $record->nomor, 'pembalik' => $pembalik->nomor],
                        );
                        Notification::make()->title('Jurnal pembalik dibuat: '.$pembalik->nomor)->success()->send();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('baris');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJurnal::route('/'),
        ];
    }
}
