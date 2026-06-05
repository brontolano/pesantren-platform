<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Proyek pengembangan (CENTRAL only). Pengelompokan task untuk
 * memantau progres pembangunan platform & modul.
 */
class DevProject extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'status', 'color', 'icon', 'sort'];

    protected $casts = [
        'sort' => 'integer',
    ];

    /** Auto-isi slug unik dari name bila kosong. */
    protected static function booted(): void
    {
        static::saving(function (DevProject $project): void {
            if (blank($project->slug)) {
                $base = Str::slug($project->name) ?: 'project';
                $slug = $base;
                $i = 1;
                while (static::query()->where('slug', $slug)->where('id', '!=', $project->id ?? 0)->exists()) {
                    $slug = $base.'-'.(++$i);
                }
                $project->slug = $slug;
            }
        });
    }

    /** Paksa koneksi central agar konsisten meski dipanggil dari konteks tenant. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', parent::getConnectionName());
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(DevTask::class)->orderBy('sort');
    }

    /** Rata-rata progres seluruh task (0-100). */
    public function progressPercent(): int
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();

        if ($tasks->isEmpty()) {
            return 0;
        }

        return (int) round($tasks->avg('progress'));
    }

    /** Jumlah task per status: ['todo'=>x,'in_progress'=>y,'blocked'=>z,'done'=>w]. */
    public function statusCounts(): array
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();

        return [
            'todo' => $tasks->where('status', 'todo')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
            'done' => $tasks->where('status', 'done')->count(),
            'total' => $tasks->count(),
        ];
    }
}
