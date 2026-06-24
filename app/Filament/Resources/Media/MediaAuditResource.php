<?php

namespace App\Filament\Resources\Media;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Media\Pages\ListMediaAudit;
use App\Models\MediaAudit;
use App\Services\StorageQuotaService;
use App\Support\UiMode;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaAuditResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = MediaAudit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Riwayat Media';

    protected static ?string $modelLabel = 'Riwayat Media';

    protected static ?string $pluralModelLabel = 'Riwayat Media';

    protected static string|\UnitEnum|null $navigationGroup = 'Audit & Arsip';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return static::moduleVisible('file_storage')
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('action')->label('Aksi')->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'upload' => 'success',
                        'pulihkan' => 'info',
                        'hapus' => 'warning',
                        'kosongkan' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('file_name')->label('File')->searchable()->wrap(),
                TextColumn::make('size_bytes')->label('Ukuran')
                    ->formatStateUsing(fn (int $state): string => StorageQuotaService::humanBytes($state)),
                TextColumn::make('user.name')->label('Oleh')->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Aksi')->options([
                    'upload' => 'Upload',
                    'hapus' => 'Hapus',
                    'pulihkan' => 'Pulihkan',
                    'kosongkan' => 'Kosongkan',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAudit::route('/'),
        ];
    }
}
