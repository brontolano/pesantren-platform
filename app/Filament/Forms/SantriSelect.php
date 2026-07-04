<?php

namespace App\Filament\Forms;

use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Modules\Kesantrian\Models\Santri;

/**
 * Dropdown santri terpusat — satu sumber label "Nama (Kelas) — NIS"
 * (Santri::label_pilih) dipakai lintas form, bukan `pluck('nama', 'id')`
 * mentah yang tak membedakan santri bernama sama.
 *
 * Query di-eager-load 'kelas' + 'penempatanAktif.kelas' agar akses label
 * tak memicu N+1 saat merender daftar opsi.
 *
 * Pemakaian:
 *   SantriSelect::make('santri_id')->required()
 *   SantriSelect::make('santri_id', fn (Builder $q) => $q->where('status', 'aktif'))->required()
 */
class SantriSelect
{
    public static function make(string $name, ?Closure $queryModifier = null): Select
    {
        return Select::make($name)
            ->label('Santri')
            ->searchable()
            ->preload()
            ->options(function () use ($queryModifier): array {
                $query = Santri::query()->with(['kelas', 'penempatanAktif.kelas']);

                if ($queryModifier !== null) {
                    $queryModifier($query);
                }

                return $query->orderBy('nama')
                    ->get()
                    ->mapWithKeys(fn (Santri $santri): array => [$santri->id => $santri->label_pilih])
                    ->all();
            });
    }
}
