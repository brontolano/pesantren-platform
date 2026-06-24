<?php

namespace App\Filament\Resources\Document;

use App\Filament\Resources\Document\Document\Pages\CreateDocument;
use App\Filament\Resources\Document\Document\Pages\ListDocument;
use App\Services\StorageQuotaService;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Document;

/**
 * F5 — Repositori Dokumen. Gated panel tenant + kelola_dokumen.
 */
class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Repositori Dokumen';

    protected static ?string $modelLabel = 'Dokumen';

    protected static ?string $pluralModelLabel = 'Repositori Dokumen';

    protected static string|\UnitEnum|null $navigationGroup = 'Kelembagaan & Pengaturan';

    protected static ?int $navigationSort = 32;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('kelola_dokumen') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return false; // versioning: unggah baru, bukan edit
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dokumen')->schema([
                TextInput::make('judul')->label('Judul')->required()->maxLength(255),
                Select::make('kategori')->label('Kategori')
                    ->options([
                        'sertifikat' => 'Sertifikat', 'ijazah' => 'Ijazah', 'mou' => 'MoU/MoA',
                        'sk' => 'SK', 'dokumen_halal' => 'Dokumen Kehalalan', 'medis' => 'Medis', 'lainnya' => 'Lainnya',
                    ])->native(false),
                FileUpload::make('path')->label('Berkas')
                    ->disk(StorageQuotaService::diskName())
                    ->directory('documents')
                    ->storeFileNamesIn('nama_asli')
                    ->required(),
                Toggle::make('is_dokumen_halal')->label('Dokumen terkait kehalalan')->inline(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('judul')->label('Judul')->searchable(),
                TextColumn::make('kategori')->label('Kategori')->badge()->placeholder('—'),
                TextColumn::make('versi')->label('Versi')->badge()->color('gray'),
                IconColumn::make('is_dokumen_halal')->label('Halal')->boolean(),
                TextColumn::make('uploader.name')->label('Diunggah oleh')->placeholder('—'),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('kategori')->options([
                    'sertifikat' => 'Sertifikat', 'ijazah' => 'Ijazah', 'mou' => 'MoU/MoA',
                    'sk' => 'SK', 'dokumen_halal' => 'Dokumen Kehalalan', 'medis' => 'Medis', 'lainnya' => 'Lainnya',
                ]),
            ])
            ->actions([
                Action::make('unduh')->label('Unduh')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $r): string => $r->url, shouldOpenInNewTab: true),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocument::route('/'),
            'create' => CreateDocument::route('/create'),
        ];
    }
}
