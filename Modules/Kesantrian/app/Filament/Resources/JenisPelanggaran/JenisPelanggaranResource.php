<?php

namespace Modules\Kesantrian\Filament\Resources\JenisPelanggaran;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\Pages\CreateJenisPelanggaran;
use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\Pages\EditJenisPelanggaran;
use Modules\Kesantrian\Filament\Resources\JenisPelanggaran\Pages\ListJenisPelanggaran;
use Modules\Kesantrian\Models\JenisPelanggaran;
use UnitEnum;

/**
 * WP-09 — Master jenis pelanggaran/prestasi (poin bebas diubah tenant).
 */
class JenisPelanggaranResource extends Resource
{
    protected static ?string $model = JenisPelanggaran::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';

    protected static UnitEnum|string|null $navigationGroup = 'Kedisiplinan';

    protected static ?int $navigationSort = 8;

    public const KATEGORI_OPTIONS = [
        'ringan' => 'Ringan',
        'sedang' => 'Sedang',
        'berat' => 'Berat',
        'prestasi' => 'Prestasi (poin negatif)',
    ];

    public static function getNavigationLabel(): string
    {
        return 'Jenis '.istilah('takzir');
    }

    public static function getModelLabel(): string
    {
        return 'Jenis '.istilah('takzir');
    }

    public static function getPluralModelLabel(): string
    {
        return 'Jenis '.istilah('takzir');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->can('manage_kedisiplinan') ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_kedisiplinan', 'manage_kedisiplinan']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_kedisiplinan') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_kedisiplinan') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_kedisiplinan') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jenis '.istilah('takzir'))->schema([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', tenant()?->getTenantKey())),
                Select::make('kategori')
                    ->options(self::KATEGORI_OPTIONS)
                    ->default('ringan')
                    ->required()
                    ->live()
                    ->helperText('Prestasi memakai poin NEGATIF (mengurangi total poin).'),
                TextInput::make('poin')
                    ->numeric()
                    ->required()
                    ->integer()
                    ->minValue(fn (callable $get): int => $get('kategori') === 'prestasi' ? -1000 : 0)
                    ->maxValue(fn (callable $get): int => $get('kategori') === 'prestasi' ? 0 : 1000)
                    ->helperText('Pelanggaran: poin >= 0. Prestasi: poin <= 0 (mis. -10).'),
                TextInput::make('sanksi_default')
                    ->label('Sanksi Default')
                    ->maxLength(255)
                    ->nullable(),
                Toggle::make('aktif')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada jenis '.istilah('takzir'))
            ->emptyStateDescription('Contoh umum dibuat otomatis saat provisioning — poin & daftar bebas disesuaikan kebijakan pesantren.')
            ->columns([
                TextColumn::make('nama')->searchable()->wrap(),
                TextColumn::make('kategori')->badge()
                    ->formatStateUsing(fn (string $state): string => self::KATEGORI_OPTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'ringan' => 'info',
                        'sedang' => 'warning',
                        'berat' => 'danger',
                        'prestasi' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('poin')->sortable(),
                TextColumn::make('sanksi_default')->label('Sanksi Default')->placeholder('—')->wrap(),
                IconColumn::make('aktif')->boolean(),
            ])
            ->defaultSort('kategori')
            ->filters([
                SelectFilter::make('kategori')->options(self::KATEGORI_OPTIONS),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJenisPelanggaran::route('/'),
            'create' => CreateJenisPelanggaran::route('/create'),
            'edit' => EditJenisPelanggaran::route('/{record}/edit'),
        ];
    }
}
