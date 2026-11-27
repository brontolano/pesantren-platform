<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvitation;
use App\Models\TenantAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TenantInvitationController extends Controller
{
    public function create(string $token): View
    {
        $invitation = $this->invitation($token);
        return view('auth.tenant-invitation', ['organization' => app('current-organization'), 'invitation' => $invitation, 'token' => $token]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'password.min' => 'Gunakan kata sandi minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
        ]);

        $organization = app('current-organization');
        $invitation = $this->invitation($token);
        DB::connection('central')->transaction(function () use ($invitation, $organization, $data): void {
            $locked = PlatformInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless($locked->organization_id === $organization->id && $locked->isUsable(), 410, 'Tautan undangan sudah dipakai atau kedaluwarsa.');

            TenantAdmin::query()->firstOrCreate(
                ['email' => mb_strtolower($locked->email)],
                ['name' => trim($data['name']), 'password' => Hash::make($data['password']), 'is_active' => true],
            );
            $locked->forceFill(['used_at' => now()])->save();
        });

        $admin = TenantAdmin::query()->where('email', mb_strtolower($invitation->email))->firstOrFail();
        abort_unless($admin->is_active, 403, 'Akun admin organisasi tidak aktif. Hubungi operator platform.');
        Auth::guard('tenant')->login($admin);
        $request->session()->regenerate();
        $request->session()->put('tenant_context_id', $organization->id);

        return redirect()->route('tenant.dashboard')->with('success', 'Akun admin aktif. Selamat datang di ruang kerja organisasi.');
    }

    private function invitation(string $token): PlatformInvitation
    {
        abort_unless(strlen($token) >= 32 && strlen($token) <= 128, 404);
        $organization = app('current-organization');
        $invitation = PlatformInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', mb_strtolower($organization->first_admin_email))
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        abort_unless($invitation && $invitation->isUsable() && Hash::check($token, $invitation->token_hash), 410, 'Tautan undangan sudah dipakai atau kedaluwarsa.');

        return $invitation;
    }
}
