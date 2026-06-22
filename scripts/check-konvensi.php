<?php

/**
 * check-konvensi.php — pengukur "konvensi debt" 5-lapisan (lihat docs/KONVENSI.md).
 *
 * MURNI scan filesystem (tanpa boot Laravel / tanpa DB). Aman & cepat.
 * Jalankan:  php scripts/check-konvensi.php           (laporan, exit 0)
 *            php scripts/check-konvensi.php --strict   (exit 1 bila ada debt → cocok CI ratchet)
 *
 * Mengukur, BUKAN memperbaiki. Angkanya dipakai untuk memantau rollout turun ke 0.
 */

$root = dirname(__DIR__);
$strict = in_array('--strict', $argv, true);
const ROUTE_MAX = 150;

function rfiles(string $dir, array $exts): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            fn ($f) => ! in_array($f->getFilename(), ['node_modules', 'vendor', '.git'], true)
        )
    );
    foreach ($it as $f) {
        if ($f->isFile() && in_array(strtolower($f->getExtension()), $exts, true)) {
            $out[] = $f->getPathname();
        }
    }
    return $out;
}

function rel(string $p, string $root): string
{
    return str_replace('\\', '/', substr($p, strlen($root) + 1));
}

$findings = [];

// ── L2: file route > 150 baris ────────────────────────────────────────────
$routeOver = [];
foreach (rfiles("$root/routes", ['php']) as $f) {
    $lines = count(file($f));
    if ($lines > ROUTE_MAX) {
        $routeOver[] = ['file' => rel($f, $root), 'lines' => $lines];
    }
}
usort($routeOver, fn ($a, $b) => $b['lines'] <=> $a['lines']);

// ── L3b/L4: .vue Pages/Layouts melakukan fetch/mutasi langsung ─────────────
// (services/ & composables/ memang BOLEH — itu lapisannya)
$vueFetch = [];
foreach (['resources/js/Pages', 'resources/js/Layouts'] as $sub) {
    foreach (rfiles("$root/$sub", ['vue']) as $f) {
        $src = file_get_contents($f);
        if (preg_match_all('/\baxios\b|\bfetch\s*\(|\brouter\.(post|put|patch|delete)\s*\(/', $src, $m)) {
            $vueFetch[] = ['file' => rel($f, $root), 'hits' => count($m[0])];
        }
    }
}
usort($vueFetch, fn ($a, $b) => $b['hits'] <=> $a['hits']);

// ── L5 (reuse desain): Intl.NumberFormat manual (→ MoneyText / Shared/format) ─
$intl = [];
$allowFmt = ['resources/js/Shared/format.js', 'resources/js/Components/Ui/MoneyText.vue'];
foreach (['resources/js/Pages', 'resources/js/Layouts', 'resources/js/Shared', 'resources/js/keuangan-pwa'] as $sub) {
    foreach (rfiles("$root/$sub", ['vue', 'js']) as $f) {
        $r = rel($f, $root);
        if (in_array($r, $allowFmt, true)) {
            continue;
        }
        $src = file_get_contents($f);
        if (preg_match_all('/Intl\.NumberFormat/', $src, $m)) {
            $intl[] = ['file' => $r, 'hits' => count($m[0])];
        }
    }
}
usort($intl, fn ($a, $b) => $b['hits'] <=> $a['hits']);

// ── L3a (informatif): Filament masih di app/ (target: pindah ke modul) ──────
$filamentFlat = count(rfiles("$root/app/Filament/Resources", ['php'])) + count(rfiles("$root/app/Filament/Pages", ['php']));

// ── Laporan ────────────────────────────────────────────────────────────────
$debt = count($routeOver) + count($vueFetch) + count($intl);

function section(string $title, array $rows, string $col): void
{
    printf("\n## %s — %d\n", $title, count($rows));
    foreach (array_slice($rows, 0, 15) as $r) {
        printf("  %4s  %s\n", $r[$col], $r['file']);
    }
    if (count($rows) > 15) {
        printf("  … +%d lagi\n", count($rows) - 15);
    }
}

echo "═══ Konvensi 5-Lapisan — Baseline ═══\n";
echo "Acuan: docs/KONVENSI.md · docs/PLAYBOOK-IRISAN.md · ROLLOUT-5-LAPISAN-BACKLOG.md\n";
section('L2 · Route file > '.ROUTE_MAX.' baris (pecah per-portal)', $routeOver, 'lines');
section('L3b · .vue Pages/Layouts fetch/mutasi langsung (pindah ke services/)', $vueFetch, 'hits');
section('L5 · Intl.NumberFormat manual (pakai MoneyText/Shared format)', $intl, 'hits');
printf("\n## L3a · Filament masih flat di app/ (target: Modules/{X}/app/Filament/) — %d file\n", $filamentFlat);

echo "\n─────────────────────────────────────\n";
printf("KONVENSI DEBT (route+vue+intl): %d temuan\n", $debt);
echo $debt === 0 ? "✓ Bersih.\n" : "→ Turunkan seiring gelombang rollout (G0–G8).\n";

if ($strict && $debt > 0) {
    exit(1);
}
