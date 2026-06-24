<?php

namespace Modules\Kegiatan\Filament\Resources\Template;

use App\Filament\Concerns\ModuleGate;
use Modules\Kegiatan\Filament\Resources\Template\Pages\CreateKegiatanTemplate;
use Modules\Kegiatan\Filament\Resources\Template\Pages\EditKegiatanTemplate;
use Modules\Kegiatan\Filament\Resources\Template\Pages\ListKegiatanTemplate;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Kegiatan\Models\KegiatanTemplate;
use Modules\Kegiatan\Services\KegiatanLanjutService;

class KegiatanTemplateResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = KegiatanTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Template Kegiatan';

    protected static ?string $modelLabel = 'Template Kegiatan';

    protected static ?string $pluralModelLabel = 'Template Kegiatan';

    protected static string|\UnitEnum|null $navigationGroup = 'Santri & Pengasuhan';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::moduleVisible('kegiatan') && (auth()->user()?->can('view_santri') ?? false);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template Kegiatan')->schema([
                TextInput::make('nama')->label('Nama')->required()->maxLength(255),
                TextInput::make('jenis')->label('Jenis')->default('umum')->maxLength(50),
                Textarea::make('deskripsi')->label('Deskripsi')->rows(2)->columnSpanFull(),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                Repeater::make('default_phases')->label('Phase Default (Gantt)')
                    ->schema([
                        TextInput::make('nama')->label('Nama Phase')->required(),
                        TextInput::make('offset_hari')->label('Mulai (hari ke-, dari tgl kegiatan)')->numeric()->default(0)->minValue(0),
                        TextInput::make('durasi_hari')->label('Durasi (hari)')->numeric()->default(1)->minValue(1),
                    ])->columns(3)->columnSpanFull()->default([])->reorderable()->addActionLabel('Tambah Phase'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Template')->searchable(),
                TextColumn::make('jenis')->label('Jenis')->badge(),
                TextColumn::make('default_phases')->label('Jml Phase')
                    ->formatStateUsing(fn ($state): int => is_array($state) ? count($state) : 0)->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('nama')
            ->actions([
                Action::make('clone')
                    ->label('Buat Kegiatan')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->schema([
                        DatePicker::make('mulai')->label('Tanggal Kegiatan')->required()->default(now()),
                        TextInput::make('nama')->label('Nama (opsional override)')->maxLength(255),
                    ])
                    ->action(function (array $data, KegiatanTemplate $record): void {
                        app(KegiatanLanjutService::class)->cloneTemplate($record, [
                            'mulai' => $data['mulai'],
                            'nama' => $data['nama'] ?: null,
                        ]);
                        Notification::make()->title('Kegiatan dibuat dari template (+phase)')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKegiatanTemplate::route('/'),
            'create' => CreateKegiatanTemplate::route('/create'),
            'edit' => EditKegiatanTemplate::route('/{record}/edit'),
        ];
    }
}
