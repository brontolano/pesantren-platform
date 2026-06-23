<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'key', 'value'])]
class Setting extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function getTenantValue(string $key, mixed $default = null): mixed
    {
        $tenant = tenant();

        if (! $tenant) {
            return $default;
        }

        $setting = static::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        return $setting->value;
    }

    public static function getTenantStringValue(string $key, ?string $default = null): ?string
    {
        $value = static::getTenantValue($key, null);

        if (is_array($value)) {
            foreach (['text', 'logo_path', 'favicon_path', 'path', 'url', 'value', 'color'] as $candidate) {
                if (isset($value[$candidate]) && is_string($value[$candidate]) && trim($value[$candidate]) !== '') {
                    return $value[$candidate];
                }
            }
        }

        return is_string($value) ? $value : $default;
    }
}
