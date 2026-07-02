<?php

namespace Modules\Kesantrian\Filament\Resources\Kelas;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Akademik\Models\Lembaga;
use Modules\Kesantrian\Filament\Resources\Kelas\Pages\CreateKelas;
use Modules\Kesantrian\Filament\Resources\Kelas\Pages\EditKelas;
use Modules\Kesantrian\Filament\Resources\Kelas\Pages\ListKelas;
use Modules\Kesantrian\Models\Kelas;

class KelasResource extends Resource
{
    protected static ?string $model = Kelas::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Kelas';

    protected static ?string $modelLabel = 'Kelas';

    protected static ?string $pluralModelLabel = 'Kelas';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Akademik';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canAny(['view_akademik', 'manage_akademik', 'manage_santri']) ?? false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canAny(['view_akademik', 'manage_akademik', 'manage_santri']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canAny(['manage_akademik', 'manage_santri']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canAny(['manage_akademik', 'manage_santri']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama_kelas')
                ->label('Nama Kelas')
                ->required()
                ->maxLength(50)
                ->placeholder('e.g. VII-A, X IPA-1'),
            TextInput::make('tingkat')
                ->label('Tingkat')
                ->required()
                ->maxLength(20)
                ->placeholder('e.g. 7, 10'),
            Select::make('jenjang_kesetaraan')
                ->label('Jenjang Kesetaraan (PKPPS/SPM)')
                ->helperText('Pemetaan kelas internal ke jenjang formal untuk EMIS & ijazah. Kosong = ikut jenjang Lembaga saat dibuat.')
                ->options(Kelas::opsiJenjangKesetaraan())
                ->native(false)
                ->nullable(),
            TextInput::make('tingkat_kesetaraan')
                ->label('Tingkat Kesetaraan')
                ->helperText('Ula 1-6, Wustha 1-3, Ulya 1-3.')
                ->numeric()
                ->minValue(1)
                ->maxValue(6)
                ->nullable(),
            TextInput::make('jurusan')
                ->label('Jurusan')
                ->maxLength(50)
                ->placeholder('e.g. IPA, IPS (opsional)'),
            TextInput::make('capacity')
                ->label('Kapasitas')
                ->numeric()
                ->default(40)
                ->minValue(1)
                ->required(),
            Select::make('wali_kelas_id')
                ->label('Wali Kelas')
                ->options(fn () => User::whereHas('roles', fn ($q) => $q->whereIn('name', ['guru', 'tu', 'super_admin']))->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
            Select::make('lembaga_id')
                ->label('Lembaga (opsional)')
                ->helperText('Kosong = lembaga default tenant.')
                ->options(fn () => Lembaga::query()->where('aktif', true)->pluck('nama', 'id'))
                ->searchable()
                ->nullable(),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('Belum ada kelas')
            ->emptyStateDescription('Tambahkan kelas terlebih dahulu untuk mulai menempatkan santri.')
            ->emptyStateIcon('heroicon-o-rectangle-stack')
            ->emptyStateActions([
                Action::make('tambah')
                    ->label('Tambah Kelas')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => static::canCreate())
                    ->url(fn (): string => static::getUrl('create')),
            ])
            ->columns([
                TextColumn::make('nama_kelas')
                    ->label('Nama Kelas')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tingkat')
                    ->label('Tingkat')
                    ->sortable(),
                TextColumn::make('label_kesetaraan')
                    ->label('Kesetaraan')
                    ->badge()
                    ->color('success')
                    ->placeholder('-'),
                TextColumn::make('jurusan')
                    ->label('Jurusan')
                    ->placeholder('-'),
                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->sortable(),
                TextColumn::make('santri_count')
                    ->label('Jumlah Santri')
                    ->counts('santri'),
                TextColumn::make('waliKelas.name')
                    ->label('Wali Kelas')
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('tingkat')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('waliKelas')
            ->withCount('santri');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKelas::route('/'),
            'create' => CreateKelas::route('/create'),
            'edit' => EditKelas::route('/{record}/edit'),
        ];
    }
}
