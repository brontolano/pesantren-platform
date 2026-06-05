<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MediaRegistrar;
use App\Services\StorageQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Scan disk tenant & daftarkan file yang belum tercatat di media_files,
 * sehingga muncul rapi di panel File Media. Idempotent — aman dijalankan ulang.
 *
 *   php artisan media:reindex                 # semua tenant
 *   php artisan media:reindex --tenant=asy-syifaa
 */
class MediaReindex extends Command
{
    protected $signature = 'media:reindex {--tenant= : ID tenant tertentu (default: semua)}';

    protected $description = 'Index ulang file disk tenant ke pustaka Media File';

    public function handle(MediaRegistrar $registrar): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Tidak ada tenant cocok.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $registrar): void {
                $disk = Storage::disk(StorageQuotaService::diskName());
                $created = 0;
                $seen = 0;

                foreach ($disk->allFiles() as $path) {
                    if (str_ends_with($path, '.gitignore')) {
                        continue;
                    }
                    $seen++;
                    $folder = dirname($path);
                    $folder = ($folder === '.' || $folder === '') ? null : $folder;
                    $media = $registrar->register($path, $folder);
                    if ($media && $media->wasRecentlyCreated) {
                        $created++;
                    }
                }

                $this->info(sprintf('[%s] %d file discan, %d baru ter-index.', $tenant->getKey(), $seen, $created));
            });
        }

        return self::SUCCESS;
    }
}
