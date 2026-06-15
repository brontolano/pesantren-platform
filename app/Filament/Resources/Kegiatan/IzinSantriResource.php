<?php

namespace App\Filament\Resources\Kegiatan;

use App\Filament\Concerns\ModuleGate;
use App\Filament\Resources\Kegiatan\IzinSantri\Pages\CreateIzinSantri;
use App\Filament\Resources\Kegiatan\IzinSantri\Pages\EditIzinSantri;
use App\Filament\Resources\Kegiatan\IzinSantri\Pages\ListIzinSantri;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Kegiatan\Models\IzinSantri;
use UnitEnum;

class IzinSantriResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = IzinSantri::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Izin Santri';

    protected static UnitEnum|string|null $navigationGroup = 'Kepengasuhan & Kedisiplinan';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('santri_id')
                ->label('Santri')
                ->relationship('santri', 'nama')
                ->searchable()->preload()->required(),
            Select::make('jenis_izin')
                ->label('Jenis Izin')
                ->options(['pulang' => 'Pulang', 'sakit' => 'Sakit', 'keluar_kota' => 'Keluar Kota', 'kunjungan' => 'Kunjungan'])
                ->required(),
            DatePicker::make('tanggal_mulai')->label('Tanggal Mulai')->required(),
            DatePicker::make('tanggal_selesai')->label('Tanggal Selesai')->required(),
            Textarea::make('alasan')->label('Alasan')->required()->rows(3),
            Select::make('status')
                ->label('Status')
                ->options(['pending' => 'Pending', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'])
                ->required()->default('pending'),
            Textarea::make('catatan_pengurus')->label('Catatan Pengurus')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('santri.nama')->label('Santri')->searchable()->sortable(),
                TextColumn::make('jenis_izin')->label('Jenis')->badge(),
                TextColumn::make('tanggal_mulai')->label('Mulai')->date('d M Y'),
                TextColumn::make('tanggal_selesai')->label('Selesai')->date('d M Y'),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'disetujui' => 'success',
                        'ditolak' => 'danger',
                        default => 'warning',
                    }),
                IconColumn::make('sudah_kembali')->label('Kembali')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak']),
                SelectFilter::make('jenis_izin')->options(['pulang' => 'Pulang', 'sakit' => 'Sakit', 'keluar_kota' => 'Keluar Kota']),
            ])
            ->actions([EditAction::make()])
            ->defaultSort('tanggal_mulai', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIzinSantri::route('/'),
            'create' => CreateIzinSantri::route('/create'),
            'edit' => EditIzinSantri::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('kegiatan') && (auth()->user()?->can('manage_santri') ?? false);
    }
}
