<?php

namespace App\Filament\Resources\Santri;

use App\Filament\Resources\Santri\Pages\CreateSantri;
use App\Filament\Resources\Santri\Pages\EditSantri;
use App\Filament\Resources\Santri\Pages\ListSantri;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Kelas;
use Modules\Kesantrian\Models\Santri;

class SantriResource extends Resource
{
    protected static ?string $model = Santri::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Santri';

    protected static ?string $modelLabel = 'Santri';

    protected static ?string $pluralModelLabel = 'Santri';

    protected static string|\UnitEnum|null $navigationGroup = 'Kesantrian';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_santri', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->can('manage_santri') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Pribadi')->schema([
                TextInput::make('nis')
                    ->label('NIS')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('nama')
                    ->label('Nama Lengkap')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('tanggal_lahir')
                    ->label('Tanggal Lahir')
                    ->required(),
                TextInput::make('tempat_lahir')
                    ->label('Tempat Lahir')
                    ->maxLength(100),
                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                    ->required(),
                TextInput::make('agama')
                    ->label('Agama')
                    ->default('Islam')
                    ->maxLength(50),
                TextInput::make('asal_sekolah')
                    ->label('Asal Sekolah')
                    ->maxLength(255),
                Textarea::make('alamat')
                    ->label('Alamat')
                    ->rows(3),
                FileUpload::make('photo_url')
                    ->label('Foto')
                    ->image()
                    ->directory('santri-photos')
                    ->maxSize(2048),
            ])->columns(2),

            Section::make('Data Wali / Orang Tua')->schema([
                TextInput::make('nama_ayah')->label('Nama Ayah')->maxLength(255),
                TextInput::make('nama_ibu')->label('Nama Ibu')->maxLength(255),
                TextInput::make('pekerjaan_ayah')->label('Pekerjaan Ayah')->maxLength(255),
                TextInput::make('pekerjaan_ibu')->label('Pekerjaan Ibu')->maxLength(255),
                TextInput::make('no_hp_wali')->label('No. HP Wali')->tel()->maxLength(20),
                TextInput::make('email_wali')->label('Email Wali')->email()->maxLength(255),
            ])->columns(2),

            Section::make('Akademik')->schema([
                Select::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn () => Kelas::where('is_active', true)->pluck('nama_kelas', 'id'))
                    ->searchable()
                    ->nullable(),
                TextInput::make('tahun_angkatan')
                    ->label('Tahun Angkatan')
                    ->placeholder('2026/2027')
                    ->maxLength(9),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'aktif' => 'Aktif',
                        'cuti' => 'Cuti',
                        'lulus' => 'Lulus',
                        'keluar' => 'Keluar',
                        'dikeluarkan' => 'Dikeluarkan',
                    ])
                    ->default('aktif')
                    ->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_url')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?background=random&name=S'),
                TextColumn::make('nis')
                    ->label('NIS')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis_kelamin')
                    ->label('L/P')
                    ->sortable(),
                TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'cuti' => 'warning',
                        'lulus' => 'info',
                        'keluar', 'dikeluarkan' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('tahun_angkatan')
                    ->label('Angkatan')
                    ->sortable(),
            ])
            ->defaultSort('nama')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'aktif' => 'Aktif',
                        'cuti' => 'Cuti',
                        'lulus' => 'Lulus',
                        'keluar' => 'Keluar',
                        'dikeluarkan' => 'Dikeluarkan',
                    ]),
                SelectFilter::make('kelas_id')
                    ->label('Kelas')
                    ->options(fn () => Kelas::pluck('nama_kelas', 'id')),
                SelectFilter::make('jenis_kelamin')
                    ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('kelas');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSantri::route('/'),
            'create' => CreateSantri::route('/create'),
            'edit' => EditSantri::route('/{record}/edit'),
        ];
    }
}
