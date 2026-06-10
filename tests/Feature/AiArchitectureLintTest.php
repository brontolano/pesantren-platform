<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Lint Aturan Emas #4 (blueprint modul AI §3.1): DILARANG memanggil/menyebut
 * Ollama di luar kelas provider gateway. Satu-satunya pintu LLM = AiGateway.
 *
 * Scan seluruh source (Modules + app): string '11434' atau 'ollama' hanya
 * boleh muncul di Modules/AI/Services/Gateway/* dan direktori config modul.
 */
class AiArchitectureLintTest extends TestCase
{
    private const PATTERN = '/11434|ollama/i';

    /** Fragmen path (dinormalisasi ke "/") yang DIIZINKAN memuat string Ollama. */
    private const ALLOWED_FRAGMENTS = [
        'Modules/AI/app/Services/Gateway/',
        'Modules/AI/config/',
    ];

    private const SCANNED_EXTENSIONS = ['php', 'json', 'js', 'ts', 'vue', 'yml', 'yaml', 'blade.php'];

    public function test_tidak_ada_referensi_ollama_di_luar_gateway_dan_config(): void
    {
        $pelanggaran = [];

        foreach ([base_path('Modules'), base_path('app')] as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! $this->shouldScan($file)) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());

                if ($this->isAllowed($path)) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                if (preg_match(self::PATTERN, $contents)) {
                    $pelanggaran[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $path);
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "Pelanggaran Aturan Emas #4 — referensi Ollama/11434 di luar gateway:\n - "
            .implode("\n - ", $pelanggaran)
            ."\nSemua panggilan LLM wajib lewat Modules\\AI\\Services\\Gateway\\AiGateway.",
        );
    }

    private function shouldScan(SplFileInfo $file): bool
    {
        $path = str_replace('\\', '/', $file->getPathname());

        if (str_contains($path, '/vendor/') || str_contains($path, '/node_modules/')) {
            return false;
        }

        foreach (self::SCANNED_EXTENSIONS as $ext) {
            if (str_ends_with($path, '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowed(string $normalizedPath): bool
    {
        foreach (self::ALLOWED_FRAGMENTS as $fragment) {
            if (str_contains($normalizedPath, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
