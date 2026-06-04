<?php

namespace App\Services;

use App\Models\Tenant;
use Symfony\Component\Process\Process;

/**
 * Backup / restore / export database fisik milik tenant.
 * Dipakai dari panel platform (super_admin) untuk monitoring & recovery.
 */
class TenantRecoveryService
{
    protected function conn(): array
    {
        $name = config('tenancy.database.central_connection', config('database.default'));

        return config("database.connections.{$name}");
    }

    protected function dir(): string
    {
        $dir = storage_path('app/tenant-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /**
     * Buat backup (gzipped SQL) database tenant. Mengembalikan path file.
     */
    public function backup(Tenant $tenant): string
    {
        $db = $tenant->database()->getName();
        $c = $this->conn();
        $file = $this->dir() . '/' . $db . '_' . now()->format('Ymd_His') . '.sql.gz';

        $cmd = sprintf(
            'pg_dump --clean --if-exists -h %s -p %s -U %s %s | gzip > %s',
            escapeshellarg((string) $c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']),
            escapeshellarg($db),
            escapeshellarg($file),
        );

        $this->run($cmd, $c['password']);

        return $file;
    }

    /**
     * Restore database tenant dari file gzipped SQL.
     */
    public function restore(Tenant $tenant, string $sqlGzPath): void
    {
        $db = $tenant->database()->getName();
        $c = $this->conn();

        $cmd = sprintf(
            'gunzip -c %s | psql -v ON_ERROR_STOP=1 -h %s -p %s -U %s -d %s',
            escapeshellarg($sqlGzPath),
            escapeshellarg((string) $c['host']),
            escapeshellarg((string) $c['port']),
            escapeshellarg((string) $c['username']),
            escapeshellarg($db),
        );

        $this->run($cmd, $c['password'], 600);
    }

    /**
     * Ukuran database tenant (human readable), mis. "8.5 MB".
     */
    public function size(Tenant $tenant): ?string
    {
        try {
            $db = $tenant->database()->getName();
            $c = $this->conn();
            $process = Process::fromShellCommandline(
                sprintf(
                    'psql -tAc %s -h %s -p %s -U %s -d postgres',
                    escapeshellarg("SELECT pg_size_pretty(pg_database_size('{$db}'))"),
                    escapeshellarg((string) $c['host']),
                    escapeshellarg((string) $c['port']),
                    escapeshellarg((string) $c['username']),
                ),
                base_path(),
                ['PGPASSWORD' => $c['password']],
                null,
                30,
            );
            $process->mustRun();

            return trim($process->getOutput()) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function run(string $cmd, ?string $password, int $timeout = 300): void
    {
        $process = Process::fromShellCommandline(
            $cmd,
            base_path(),
            ['PGPASSWORD' => (string) $password],
            null,
            $timeout,
        );
        $process->mustRun();
    }
}
