<?php

namespace App\Filament\Pages;

use App\Models\TenantApiKey;
use App\Support\UiMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Panel tenant: kelola Kunci API (TenantApiKey) untuk akses API publik headless
 * (website tenant memanggil /api/v1/* dgn header X-Api-Key). Auto-discovered via
 * TenantPanelProvider->discoverPages().
 *
 * Keamanan: plaintext kunci HANYA ditampilkan sekali (saat dibuat) di kartu atas,
 * lalu tak pernah lagi — hanya hash yang tersimpan. "Cabut" mengeset revoked_at
 * sehingga kunci langsung ditolak middleware api.key.
 */
class IntegrasiApi extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Integrasi & API';

    protected static string|\UnitEnum|null $navigationGroup = 'Cadangan & Integrasi';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Integrasi & API';

    protected string $view = 'filament.pages.integrasi-api';

    /** Plaintext kunci yang baru dibuat — ditampilkan sekali lalu dibuang. */
    public ?string $generatedKey = null;

    public static function canAccess(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'tenant'
            && (Auth::user()?->can('manage_tenant_settings') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return UiMode::advanced() && static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Kunci API memberi website/aplikasi luar akses baca ke data tenant ini '
            .'melalui header X-Api-Key. Jaga kerahasiaannya — perlakukan seperti kata sandi.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buat')
                ->label('Buat Kunci API')
                ->icon('heroicon-o-plus')
                ->modalHeading('Buat Kunci API Baru')
                ->modalDescription('Beri label agar mudah dikenali (mis. "Website utama"). '
                    .'Kunci akan ditampilkan sekali setelah dibuat.')
                ->modalSubmitActionLabel('Buat')
                ->form([
                    TextInput::make('label')
                        ->label('Label')
                        ->placeholder('Website utama')
                        ->maxLength(60),
                ])
                ->action(function (array $data): void {
                    $tenantId = tenant()?->getTenantKey();
                    if ($tenantId === null) {
                        Notification::make()
                            ->title('Gagal: konteks tenant tidak ditemukan.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $built = TenantApiKey::buildFor(
                        (string) $tenantId,
                        filled($data['label'] ?? null) ? $data['label'] : null,
                    );
                    TenantApiKey::create($built['attributes']);

                    $this->generatedKey = $built['plain'];

                    Notification::make()
                        ->title('Kunci API berhasil dibuat')
                        ->body('Salin kunci di kartu atas sekarang — tidak akan ditampilkan lagi.')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }

    public function dismissGeneratedKey(): void
    {
        $this->generatedKey = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => TenantApiKey::query()
                ->where('tenant_id', tenant()?->getTenantKey()))
            ->columns([
                TextColumn::make('label')
                    ->label('Label')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('prefix')
                    ->label('Kunci (prefix)')
                    ->formatStateUsing(fn (?string $state): string => ($state ?? '').'…')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Prefix disalin'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (TenantApiKey $record): string => $record->revoked_at === null ? 'Aktif' : 'Dicabut')
                    ->color(fn (string $state): string => $state === 'Aktif' ? 'success' : 'danger'),
                TextColumn::make('last_used_at')
                    ->label('Terakhir dipakai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Belum pernah')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('revoked_at')
                    ->label('Dicabut')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('cabut')
                    ->label('Cabut')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cabut kunci API ini?')
                    ->modalDescription('Aplikasi/website yang memakai kunci ini akan langsung kehilangan akses. '
                        .'Tindakan ini tidak bisa dibatalkan.')
                    ->visible(fn (TenantApiKey $record): bool => $record->revoked_at === null)
                    ->action(function (TenantApiKey $record): void {
                        $record->forceFill(['revoked_at' => now()])->save();

                        Notification::make()
                            ->title('Kunci API dicabut')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada kunci API')
            ->emptyStateDescription('Buat kunci pertama untuk menghubungkan website ke API tenant ini.')
            ->emptyStateIcon('heroicon-o-key');
    }
}
