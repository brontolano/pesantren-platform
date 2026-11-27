<?php

namespace App\Services\Provisioning;

use App\Mail\TenantAdminInvitationMail;
use App\Models\Organization;
use App\Models\PlatformInvitation;
use App\Support\PlatformAudit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PlatformInvitationService
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function issueForSetup(Organization $organization): PlatformInvitation
    {
        $email = mb_strtolower(trim($organization->first_admin_email));
        $existing = PlatformInvitation::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($existing) {
            // A mail transport may have failed after the token was stored. Expire the
            // old hash before re-issuing so retry never leaves two usable links.
            $existing->forceFill(['expires_at' => now()])->save();
        }

        $token = Str::random(64);
        $minutes = max(5, min(120, (int) config('tenancy.invitation_ttl_minutes', 30)));
        $invitation = PlatformInvitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $email,
            'token_hash' => Hash::make($token),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $url = $scheme.'://'.$organization->canonical_domain.'/pondok/invitation/'.$token;
        try {
            Mail::to($email)->send(new TenantAdminInvitationMail($organization, $url, $invitation->expires_at));
        } catch (\Throwable $exception) {
            $invitation->forceFill(['expires_at' => now()])->save();
            throw $exception;
        }
        $this->audit->record('organization.admin_invitation_sent', $organization->id, ['email' => $email], PlatformInvitation::class, $invitation->id);

        return $invitation;
    }
}
