<?php

namespace Modules\Akademik\Filament\Resources\PaketSoalAkademik;

use App\Filament\Concerns\ModuleGate;
use Modules\Akademik\Filament\Resources\PaketSoalAkademik\Pages\CreatePaketSoalAkademik;
use Modules\Akademik\Filament\Resources\PaketSoalAkademik\Pages\EditPaketSoalAkademik;
use Modules\Akademik\Filament\Resources\PaketSoalAkademik\Pages\ListPaketSoalAkademik;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Modules\Akademik\Models\Pelajaran;
use Modules\Akademik\Services\UjianAkademikService;
use Modules\PPDB\Models\PaketSoal;

/**
 * F4 — Bank Soal Akademik: paket soal ber-pelajaran (engine ujian PPDB,
 * konteks akademik). Hanya guru pengampu pelajaran ybs / manage_akademik.
 * Bank pribadi (created_by) vs bank lembaga (is_bank_lembaga, terlihat &
 * boleh diduplikat guru lain, read-only).
 */
class PaketSoalAkademikResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = PaketSoal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?string $navigationLabel = 'Bank Soal Akademik';

    protected static string|\UnitEnum|null $navigationGroup = 'Akademik & Tahfidz';

    protected static ?string $modelLabel = 'Paket Soal Akademik';

    protected static ?string $slug = 'akademik/paket-soal-akademik';

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('akademik') && static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return static::moduleVisible('akademik')
            && ($user->can('manage_akademik')
                || Pelajaran::query()->where('guru_pengampu_id', $user->getKey())->exists());
    }

    /** Hanya paket akademik. Non-manage: milikku / pelajaran ampuanku / bank lembaga. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->whereNotNull('pelajaran_id');
        $user = Auth::user();

        if ($user !== null && ! $user->can('manage_akademik')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->getKey())
                    ->orWhere('is_bank_lembaga', true)
                    ->orWhereHas('pelajaran', fn (Builder $p) => $p->where('guru_pengampu_id', $user->getKey()));
            });
        }

        return $query;
    }

    public static function canEdit(Model $record): bool
    {
        try {
            app(UjianAkademikService::class)
                ->pastikanBolehKelolaPaket(Auth::user(), (int) $record->pelajaran_id, $record);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Paket')->columns(3)->schema([
                TextInput::make('nama')->label('Nama paket')->required()->maxLength(150),
                Select::make('pelajaran_id')->label('Pelajaran')
                    ->options(function () {
                        $user = Auth::user();
                        $q = Pelajaran::query()->orderBy('nama');
                        if ($user !== null && ! $user->can('manage_akademik')) {
                            $q->where('guru_pengampu_id', $user->getKey());
                        }

                        return $q->pluck('nama', 'id');
                    })
                    ->required()
                    ->helperText('Hanya pelajaran yang Anda ampu (kecuali manage_akademik).'),
                TextInput::make('durasi_menit')->label('Durasi')->numeric()->minValue(5)->maxValue(360)->default(60)->suffix('menit')->required(),
                TextInput::make('jumlah_tampil')->label('Jumlah soal tampil')->numeric()->minValue(1)
                    ->helperText('Kosongkan = semua soal. Isi utk subset acak per peserta.'),
                Toggle::make('is_bank_lembaga')->label('Bank lembaga')
                    ->helperText('Terlihat & dapat diduplikat guru lain (read-only).'),
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
                TextColumn::make('pelajaran.nama')->label('Pelajaran')->badge(),
                TextColumn::make('durasi_menit')->label('Durasi')->suffix(' mnt'),
                TextColumn::make('soal_count')->counts('soal')->label('Jml Soal'),
                IconColumn::make('is_bank_lembaga')->label('Bank Lembaga')->boolean(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->actions([
                EditAction::make()->visible(fn (Model $record): bool => static::canEdit($record)),
                DeleteAction::make()->visible(fn (Model $record): bool => static::canEdit($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaketSoalAkademik::route('/'),
            'create' => CreatePaketSoalAkademik::route('/create'),
            'edit' => EditPaketSoalAkademik::route('/{record}/edit'),
        ];
    }
}
