<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleEntitlement extends Model
{
    protected $connection = 'central';

    protected $fillable = ['organization_id', 'module_catalog_item_id', 'enabled_by', 'enabled_at'];

    protected function casts(): array
    {
        return ['enabled_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleCatalogItem::class, 'module_catalog_item_id');
    }
}
