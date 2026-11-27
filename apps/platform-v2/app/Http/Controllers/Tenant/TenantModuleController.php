<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\ModuleCatalogItem;
use App\Models\TenantModuleEntitlement;
use Illuminate\Http\Request;

class TenantModuleController extends Controller
{
    public function show(string $moduleKey, Request $request): never
    {
        $organization = app('current-organization');
        $module = ModuleCatalogItem::query()->where('key', $moduleKey)->first();
        abort_unless($module && $module->available && $module->implemented, 404, 'Modul ini belum tersedia.');

        $enabled = TenantModuleEntitlement::query()
            ->where('organization_id', $organization->id)
            ->where('module_catalog_item_id', $module->id)
            ->exists();
        abort_unless($enabled, 404, 'Modul ini belum diaktifkan untuk organisasi Anda.');

        // A module route is only added when its implementation and its tests ship.
        abort(404, 'Modul ini belum tersedia.');
    }
}
