<?php

namespace App\Filament\Resources\PPDB\PaketSoal;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\PPDB\PaketSoal\Pages\CreatePaketSoal;
use App\Filament\Resources\PPDB\PaketSoal\Pages\EditPaketSoal;
use App\Filament\Resources\PPDB\PaketSoal\Pages\ListPaketSoal;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\PPDB\Models\PaketSoal;

/**
 * Bank Soal PPDB — authoring paket + butir soal (PG berkunci / essay) dalam
 * satu layar (Repeater relationship). Dipakai Ujian Online (attempt terpisah).
 */
class PaketSoalResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PaketSoal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Bank Soal';

    protected static string|\UnitEnum|null $navigationGroup = 'PPDB';

    protected static ?string $modelLabel = 'Paket Soal';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('ppdb') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('ppdb') && (Auth::user()?->can('manage_santri') ?? false);
    }

    /**
     * F4: paket akademik (pelajaran_id terisi) dikelola di resource
     * Akademik — resource PPDB ini tetap HANYA melihat paket PPDB.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('pelajaran_id');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Paket')->columns(3)->schema([
                TextInput::make('nama')->label('Nama paket')->required()->maxLength(150),
                Select::make('kategori_ujian')->label('Kategori')
                    ->options(['Tulis' => 'Tulis', 'Lisan' => 'Lisan', 'Tahfidz' => 'Tahfidz', 'Wawancara' => 'Wawancara'])
                    ->default('Tulis')->required(),
                TextInput::make('durasi_menit')->label('Durasi')->numeric()->minValue(5)->maxValue(360)->default(60)->suffix('menit')->required(),
                TextInput::make('jumlah_tampil')->label('Jumlah soal tampil')->numeric()->minValue(1)
                    ->helperText('Kosongkan = semua soal. Isi utk subset acak per peserta.'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),

            Section::make('Butir Soal')->schema([
                Repeater::make('soal')
                    ->relationship()
                    ->label('')
                    ->orderColumn('sort')
                    ->reorderable()
                    ->collapsible()
                    ->itemLabel(fn (array $state): string => mb_strimwidth((string) ($state['pertanyaan'] ?? 'Soal baru'), 0, 60, '…'))
                    ->schema([
                        Select::make('tipe')->label('Tipe')
                            ->options(['pg' => 'Pilihan Ganda (auto-grade)', 'essay' => 'Essay (nilai manual)'])
                            ->default('pg')->live()->required(),
                        Textarea::make('pertanyaan')->label('Pertanyaan')->rows(2)->required(),
                        KeyValue::make('pilihan')->label('Pilihan jawaban')
                            ->keyLabel('Huruf (A-E)')->valueLabel('Teks pilihan')
                            ->visible(fn ($get): bool => $get('tipe') === 'pg')
                            ->default(['A' => '', 'B' => '', 'C' => '', 'D' => '']),
                        TextInput::make('kunci')->label('Kunci jawaban')
                            ->maxLength(5)
                            ->visible(fn ($get): bool => $get('tipe') === 'pg')
                            ->requiredIf('tipe', 'pg')
                            ->helperText('Huruf pilihan yang benar, mis. A'),
                        TextInput::make('bobot')->label('Bobot')->numeric()->minValue(1)->default(1),
                    ])
                    ->columns(2)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah soal'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Paket')->searchable(),
                TextColumn::make('kategori_ujian')->label('Kategori')->badge(),
                TextColumn::make('durasi_menit')->label('Durasi')->suffix(' mnt'),
                TextColumn::make('soal_count')->counts('soal')->label('Jml Soal'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaketSoal::route('/'),
            'create' => CreatePaketSoal::route('/create'),
            'edit' => EditPaketSoal::route('/{record}/edit'),
        ];
    }
}
