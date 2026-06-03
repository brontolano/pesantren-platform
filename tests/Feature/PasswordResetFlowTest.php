<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->tenant = Tenant::create([
            'id' => 'tenant-reset',
            'name' => 'Tenant Reset',
            'is_active' => true,
        ]);

        $this->tenant->domains()->create([
            'domain' => 'tenant-reset',
        ]);

        $this->user = User::withoutGlobalScopes()->create([
            'name' => 'Reset User',
            'email' => 'reset@tenant-reset.localhost',
            'password' => 'password',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_user_can_request_password_reset_link_from_filament_panel(): void
    {
        tenancy()->initialize($this->tenant);
        Notification::fake();

        Livewire::test(RequestPasswordReset::class)
            ->set('data.email', $this->user->email)
            ->call('request');

        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        tenancy()->initialize($this->tenant);

        $token = Password::broker()->createToken($this->user);

        Livewire::withQueryParams([
            'email' => $this->user->email,
            'token' => $token,
        ])
            ->test(ResetPassword::class)
            ->set('password', 'new-password-123')
            ->set('passwordConfirmation', 'new-password-123')
            ->call('resetPassword');

        $this->user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $this->user->password));
    }
}
