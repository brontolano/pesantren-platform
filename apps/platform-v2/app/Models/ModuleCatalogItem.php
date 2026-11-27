<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleCatalogItem extends Model
{
    protected $connection = 'central';

    protected $fillable = ['key', 'name', 'description', 'available', 'implemented'];

    protected function casts(): array
    {
        return ['available' => 'boolean', 'implemented' => 'boolean'];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(TenantModuleEntitlement::class, 'module_catalog_item_id');
    }
}
