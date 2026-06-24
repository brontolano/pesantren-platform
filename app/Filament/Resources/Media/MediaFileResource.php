<?php

namespace App\Filament\Resources\Media;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Media\Pages\CreateMediaFile;
use App\Filament\Resources\Media\Pages\ListMediaFiles;
use App\Models\MediaFile;
use App\Services\StorageQuotaService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class MediaFileResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = MediaFile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'File Media';

    protected static ?string $modelLabel = 'File Media';

    protected static ?string $pluralModelLabel = 'File Media';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Pengaturan';

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

    /** Sertakan record ter-trash agar filter "Dihapus" berfungsi (tenant scope tetap). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Pilih file media')
                ->disk(StorageQuotaService::diskName())
                ->directory('media')
                ->storeFileNamesIn('original_name')
                ->maxSize(StorageQuotaService::maxFileKb())
                ->helperText('Maks '.config('media.max_file_mb', 100).' MB/file. Total dibatasi kuota tenant.')
                ->required()
                ->columnSpanFull(),
            TextInput::make('folder')
                ->label('Folder (opsional)')
                ->placeholder('mis. galeri, dokumen, brosur')
                ->maxLength(100),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumb')
                    ->label('')
                    ->square()
                    ->size(44)
                    // URL via /_tenant-storage (bukan ->disk()/Storage::url yang 404 di Docker).
                    ->getStateUsing(fn (MediaFile $r): ?string => $r->isImage() ? $r->url() : null),
                TextColumn::make('original_name')->label('Nama File')->searchable()->wrap(),
                TextColumn::make('folder')->label('Folder')->badge()->color('gray')
                    ->placeholder('—')->toggleable(),
                TextColumn::make('mime')->label('Tipe')->badge()->color('info')->toggleable(),
                TextColumn::make('size_bytes')->label('Ukuran')
                    ->formatStateUsing(fn (int $state): string => StorageQuotaService::humanBytes($state))
                    ->sortable(),
                TextColumn::make('created_at')->label('Diunggah')->since()->sortable(),
                TextColumn::make('deleted_at')->label('Dihapus')->since()->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('folder')
                    ->label('Folder')
                    ->options(fn (): array => MediaFile::query()
                        ->whereNotNull('folder')->distinct()->orderBy('folder')
                        ->pluck('folder', 'folder')->all()),
                SelectFilter::make('type')
                    ->label('Tipe')
                    ->options([
                        'image' => 'Gambar',
                        'video' => 'Video',
                        'audio' => 'Audio',
                        'application' => 'Dokumen',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $v = $data['value'] ?? null;

                        return $v ? $query->where('mime', 'like', $v.'/%') : $query;
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Unduh')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (MediaFile $r): ?string => $r->existsOnDisk() ? $r->url() : null, shouldOpenInNewTab: true)
                    ->visible(fn (MediaFile $r): bool => $r->deleted_at === null && $r->existsOnDisk()),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ForceDeleteBulkAction::make(),
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
