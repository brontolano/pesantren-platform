<?php

namespace App\Filament\Portal\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Ganti password portal peserta. Wajib saat login pertama (must_reset_password),
 * dipaksa oleh middleware EnsurePesertaPasswordReset. Bisa juga dipakai sukarela.
 */
class GantiPasswordPertama extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $title = 'Ganti Password';

    protected static ?string $slug = 'ganti-password';

    protected string $view = 'filament.portal.pages.ganti-password';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('password')
                    ->label('Password Baru')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed(),
                TextInput::make('password_confirmation')
                    ->label('Ulangi Password Baru')
                    ->password()
                    ->revealable()
                    ->required(),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();
        $user->password = $data['password']; // cast 'hashed' meng-hash saat di-set
        $user->must_reset_password = false;
        $user->save();

        Notification::make()->title('Password berhasil diperbarui')->success()->send();

        $this->redirect('/portal');
    }
}
