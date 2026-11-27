<?php

namespace App\Services\Provisioning;

use App\Models\Organization;
use App\Models\TenantDomain;
use App\Support\DomainCanonicalizer;
use App\Support\PlatformAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenantDraft
{
    public function __construct(private readonly DomainCanonicalizer $domains, private readonly PlatformAudit $audit) {}

    public function handle(array $input): Organization
    {
        $domain = $this->domains->normalize($input['canonical_domain']);
        if (TenantDomain::query()->where('domain', $domain)->exists()) {
            throw ValidationException::withMessages(['canonical_domain' => 'Domain ini sudah dipakai organisasi lain.']);
        }

        return DB::connection('central')->transaction(function () use ($input, $domain): Organization {
            $slug = Str::slug($input['name']);
            if (Organization::query()->where('slug', $slug)->exists()) {
                $slug = $slug.'-'.Str::lower(Str::random(5));
            }

            $organization = Organization::query()->create([
                'name' => trim($input['name']),
                'slug' => $slug,
                'organization_type' => $input['organization_type'],
                'canonical_domain' => $domain,
                'first_admin_email' => mb_strtolower(trim($input['first_admin_email'])),
                'setup_state' => 'draft',
                'service_state' => 'inactive',
                'setup_steps' => ['identity' => true, 'domain' => true, 'first_admin' => true, 'reviewed' => false],
            ]);

            TenantDomain::query()->create([
                'organization_id' => $organization->id,
                'domain' => $domain,
                'is_canonical' => true,
            ]);

            $this->audit->record('organization.draft_created', $organization->id, [
                'organization_type' => $organization->organization_type,
                'domain' => $organization->canonical_domain,
            ], Organization::class, $organization->id);

            return $organization;
        });
    }
}
