<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\CreateMediaFile;
use App\Filament\Resources\Media\Pages\ListMediaFiles;
use App\Models\MediaFile;
use App\Services\StorageQuotaService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class MediaFileResource extends Resource
{
    use \App\Filament\Concerns\ModuleGate;

    protected static ?string $model = MediaFile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'File Media';

    protected static ?string $modelLabel = 'File Media';

    protected static ?string $pluralModelLabel = 'File Media';

    protected static string|\UnitEnum|null $navigationGroup = 'File Storage';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::moduleVisible('file_storage')
            && (auth()->user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Pilih file media')
                ->disk('public')
                ->directory('media')
                ->storeFileNamesIn('original_name')
                ->maxSize(102400) // 100 MB / file
                ->helperText('Maks 100 MB per file. Total dibatasi kuota tenant.')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_name')->label('Nama File')->searchable()->wrap(),
                TextColumn::make('mime')->label('Tipe')->badge()->color('gray')->toggleable(),
                TextColumn::make('size_bytes')->label('Ukuran')
                    ->formatStateUsing(fn (int $state): string => StorageQuotaService::humanBytes($state))
                    ->sortable(),
                TextColumn::make('created_at')->label('Diunggah')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (MediaFile $r): ?string => Storage::disk('public')->exists($r->path) ? Storage::disk('public')->url($r->path) : null, shouldOpenInNewTab: true)
                    ->visible(fn (MediaFile $r): bool => Storage::disk('public')->exists($r->path)),
                DeleteAction::make()
                    ->before(fn (MediaFile $r) => Storage::disk('public')->delete($r->path)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaFiles::route('/'),
            'create' => CreateMediaFile::route('/create'),
        ];
    }
}
