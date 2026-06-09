<?php

namespace App\Filament\Resources\Approval;

use App\Filament\Resources\Approval\ApprovalRule\Pages\CreateApprovalRule;
use App\Filament\Resources\Approval\ApprovalRule\Pages\EditApprovalRule;
use App\Filament\Resources\Approval\ApprovalRule\Pages\ListApprovalRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\ApprovalRule;
use Spatie\Permission\Models\Role;

/**
 * Konfigurasi aturan persetujuan (F1). Gated: panel tenant + permission kelola_approval.
 */
class ApprovalRuleResource extends Resource
{
    protected static ?string $model = ApprovalRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Aturan Persetujuan';

    protected static ?string $modelLabel = 'Aturan Persetujuan';

    protected static ?string $pluralModelLabel = 'Aturan Persetujuan';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('kelola_approval') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Aturan Persetujuan')
                ->description('Tetapkan kapan sebuah transaksi butuh persetujuan & siapa penyetujunya. Mode "wajib" menahan transaksi sampai disetujui.')
                ->schema([
                    Select::make('approvable_type')->label('Jenis Transaksi')
                        ->options(ApprovalRule::APPROVABLE)->required()->native(false),
                    TextInput::make('nama')->label('Nama Aturan')->required()->maxLength(255),
                    TextInput::make('field')->label('Field Syarat')->maxLength(60)
                        ->placeholder('mis. total / nominal')
                        ->helperText('Kosongkan = semua transaksi jenis ini butuh approval.'),
                    Select::make('operator')->label('Operator')->default('>=')
                        ->options(['>' => '>', '>=' => '≥', '=' => '=', '<=' => '≤', '<' => '<']),
                    TextInput::make('nilai')->label('Ambang (Rp)')->numeric()->default(0)->prefix('Rp'),
                    Select::make('approver_role')->label('Disetujui oleh (peran)')
                        ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                        ->required()->searchable(),
                    Select::make('mode')->label('Mode')->default('wajib')
                        ->options(['wajib' => 'Wajib (tahan sampai disetujui)', 'peringatan' => 'Peringatan (lolos, ditandai)']),
                    TextInput::make('urutan')->label('Urutan Step')->numeric()->default(1)->minValue(1),
                    Toggle::make('aktif')->label('Aktif')->default(true)->inline(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('approvable_type')->label('Jenis')
                    ->formatStateUsing(fn (?string $state): string => ApprovalRule::APPROVABLE[$state] ?? class_basename((string) $state)),
                TextColumn::make('field')->label('Syarat')
                    ->formatStateUsing(fn (?string $state, ApprovalRule $r): string => $state ? "{$state} {$r->operator} ".number_format((float) $r->nilai, 0, ',', '.') : 'selalu'),
                TextColumn::make('approver_role')->label('Penyetuju')->badge(),
                TextColumn::make('mode')->label('Mode')->badge()
                    ->color(fn (?string $state): string => $state === 'wajib' ? 'danger' : 'warning'),
                TextColumn::make('aktif')->label('Status')->badge()
                    ->formatStateUsing(fn ($state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('approvable_type')
            ->actions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRule::route('/'),
            'create' => CreateApprovalRule::route('/create'),
            'edit' => EditApprovalRule::route('/{record}/edit'),
        ];
    }
}
