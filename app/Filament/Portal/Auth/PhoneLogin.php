<?php

namespace App\Filament\Portal\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Modules\PPDB\Support\PpdbPhone;

/**
 * Login portal PESERTA via No. HP (bukan email).
 *
 * Tetap memakai guard `web` + provider `users` (Hukum #2 Blueprint — satu auth).
 * Pembatasan ke role `peserta` ditangani User::canAccessPanel('portal').
 */
class PhoneLogin extends Login
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getPhoneFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getPhoneFormComponent(): TextInput
    {
        return TextInput::make('phone')
            ->label('Nomor WhatsApp')
            ->tel()
            ->required()
            ->autofocus()
            ->autocomplete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            // Normalisasi WAJIB identik dgn issuance (62…) agar nomor terbit bisa login.
            'phone' => PpdbPhone::normalize($data['phone'] ?? ''),
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
