<?php

namespace Modules\Notifikasi\Filament\Resources\TemplateNotifikasi;

use App\Filament\Concerns\ModuleGate;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\Pages\CreateTemplateNotifikasi;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\Pages\EditTemplateNotifikasi;
use Modules\Notifikasi\Filament\Resources\TemplateNotifikasi\Pages\ListTemplateNotifikasi;
use App\Support\AlbaKidsTenant;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
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
use Illuminate\Database\Eloquent\Model;
use Modules\Notifikasi\Models\TemplateNotifikasi;

class TemplateNotifikasiResource extends Resource
{
    use ModuleGate;

    protected static ?string $model = TemplateNotifikasi::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static \UnitEnum|string|null $navigationGroup = 'Komunikasi';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible('notifikasi') && (auth()->user()?->canAny(['view_notifikasi', 'manage_notifikasi']) ?? false);
    }

    public static function canAccess(): bool
    {
        return static::moduleVisible('notifikasi') && (auth()->user()?->canAny(['view_notifikasi', 'manage_notifikasi']) ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_notifikasi') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage_notifikasi') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage_notifikasi') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Template')->schema([
                TextInput::make('kode_template')
                    ->label('Kode Template')
                    ->required()
                    ->maxLength(100),
                TextInput::make('judul')
                    ->label('Judul')
                    ->required()
                    ->maxLength(255),
                Select::make('channel')
                    ->label('Channel')
                    ->options([
                        'wa' => 'WhatsApp',
                        'email' => 'Email',
                    ])
                    ->default('wa')
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
                Textarea::make('isi')
                    ->label('Isi Pesan')
                    ->rows(8)
                    ->required()
                    ->helperText('Gunakan placeholder seperti {{nama_santri}} atau {{nama_wali}}.'),
                KeyValue::make('variable_dinamis')
                    ->label('Variabel Dinamis')
                    ->keyLabel('Nama Variabel')
                    ->valueLabel('Contoh Nilai')
                    ->reorderable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode_template')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('judul')
                    ->label('Judul')
                    ->searchable(),
                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplateNotifikasi::route('/'),
            'create' => CreateTemplateNotifikasi::route('/create'),
            'edit' => EditTemplateNotifikasi::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (AlbaKidsTenant::isActive()) {
            $query->where('kode_template', 'like', 'albakids_%');
        }

        return $query;
    }

    public static function getNavigationLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Template Pesan' : 'Template Notifikasi';
    }

    public static function getModelLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Template Pesan Alba Kids' : 'Template Notifikasi';
    }

    public static function getPluralModelLabel(): string
    {
        return AlbaKidsTenant::isActive() ? 'Template Pesan' : 'Template Notifikasi';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AlbaKidsTenant::isActive() ? 'Notifikasi Custom' : 'Komunikasi';
    }
}
