<?php

namespace Modules\Kesehatan\Filament\Resources;

use App\Filament\Concerns\ModuleGate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Kesehatan\Filament\Resources\Obat\Pages\CreateObat;
use Modules\Kesehatan\Filament\Resources\Obat\Pages\EditObat;
use Modules\Kesehatan\Filament\Resources\Obat\Pages\ListObat;
use Modules\Kesehatan\Filament\Resources\Obat\RelationManagers\MutasiRelationManager;
use Modules\Kesehatan\Models\Obat;
use Modules\Kesehatan\Services\ObatService;
use Throwable;
use UnitEnum;

/**
 * WP-28 — Stok Obat UKS (tanpa batch/expiry — keputusan G2-14).
 * Stok HANYA berubah via aksi Stok Masuk / Stok Keluar (ObatService),
 * bukan edit kolom langsung — menjaga jejak obat_mutasi.
 */
class ObatResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = Obat::class;

    protected static ?string $slug = 'kesehatan/obat';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Stok Obat';

    protected static ?string $modelLabel = 'Obat';

    protected static ?string $pluralModelLabel = 'Stok Obat';

    protected static UnitEnum|string|null $navigationGroup = 'Kesehatan';

    protected static ?int $navigationSort = 3;

    /** Badge jumlah obat menipis — peringatan di sidebar. */
    public static function getNavigationBadge(): ?string
    {
        $menipis = Obat::query()->menipis()->count();

        return $menipis > 0 ? (string) $menipis : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('nama')
                ->label('Nama Obat')
                ->required()
                ->maxLength(255),
            TextInput::make('satuan')
                ->label('Satuan')
                ->default('pcs')
                ->required()
                ->maxLength(50),
            TextInput::make('stok')
                ->label('Stok Awal')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->disabledOn('edit')
                ->helperText('Setelah dibuat, ubah stok lewat aksi Stok Masuk / Stok Keluar agar mutasi tercatat.'),
            TextInput::make('ambang_menipis')
                ->label('Ambang Menipis')
                ->numeric()
                ->minValue(0)
                ->default(10)
                ->required(),
            Toggle::make('aktif')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama Obat')->searchable()->sortable(),
                TextColumn::make('satuan')->label('Satuan'),
                TextColumn::make('stok')->label('Stok')->badge()
                    ->color(fn (Obat $record): string => $record->isMenipis() ? 'danger' : 'success'),
                TextColumn::make('ambang_menipis')->label('Ambang'),
                IconColumn::make('aktif')->label('Aktif')->boolean(),
            ])
            ->filters([
                Filter::make('menipis')
                    ->label('Stok Menipis')
                    ->query(fn (Builder $query): Builder => $query->menipis()),
            ])
            ->actions([
                Action::make('stok_masuk')
                    ->label('Stok Masuk')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        TextInput::make('jumlah')->label('Jumlah')->numeric()->minValue(1)->required(),
                        TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                    ])
                    ->action(function (Obat $record, array $data): void {
                        try {
                            app(ObatService::class)->masuk($record, (int) $data['jumlah'], [
                                'keterangan' => $data['keterangan'] ?? null,
                                'user_id' => auth()->id(),
                            ]);
                            Notification::make()->success()->title('Stok masuk tercatat')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('stok_keluar')
                    ->label('Stok Keluar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        TextInput::make('jumlah')->label('Jumlah')->numeric()->minValue(1)->required(),
                        TextInput::make('keterangan')->label('Keterangan')->maxLength(255),
                    ])
                    ->action(function (Obat $record, array $data): void {
                        try {
                            app(ObatService::class)->keluar($record, (int) $data['jumlah'], null, [
                                'keterangan' => $data['keterangan'] ?? null,
                                'user_id' => auth()->id(),
                            ]);
                            Notification::make()->success()->title('Stok keluar tercatat')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->defaultSort('nama');
    }

    public static function getRelations(): array
    {
        return [
            MutasiRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListObat::route('/'),
            'create' => CreateObat::route('/create'),
            'edit' => EditObat::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kesehatan') && (auth()->user()?->can('view_kesehatan') ?? false);
    }

    /**
     * Gerbang mutasi (audit RBAC 2026-07 P0-1): `view_*` = baca-saja;
     * create/edit/delete WAJIB permission `manage_kesehatan`.
     */
    public static function canCreate(): bool
    {
        return static::canAccess() && (auth()->user()?->can('manage_kesehatan') ?? false);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }
}
