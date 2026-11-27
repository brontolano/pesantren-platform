import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const required = [
  ['README-V2.md', ['apps/platform-v2', 'Laravel 13', 'basis data tenant', 'packages/pesantren-design-system']],
  ['docs/v2/product/scope.md', ['Ringkasan', 'Draf', 'nonaktif']],
  ['docs/v2/product/permissions-and-tenancy.md', ['PlatformOperator', 'Admin tenant', 'server']],
  ['docs/v2/product/roadmap.md', ['MFA operator', 'Portal Orang Tua']],
  ['docs/v2/design-system/guide.md', ['WCAG 2.2 AA', '24×24', 'Ponsel', 'Fokus', 'Blade/Livewire', 'kartu/baris bertumpuk', 'packages/pesantren-design-system']],
  ['docs/v2/operations/installation.md', ['Windows PowerShell', 'Linux/macOS shell', '.env.example', 'example.invalid', 'composer.lock', 'laravel/boost', 'IDE', 'laravel/ai']],
  ['docs/v2/operations/staging.md', ['MFA', 'LCP/INP/CLS', 'belum diketahui']],
  ['docs/v2/operations/backup-restore-rollback.md', ['Backup', 'Restore', 'Rollback']],
  ['docs/v2/testing/verification.md', ['tenant B', 'p95', 'postgres']],
];
for (const [relative, needles] of required) {
  const content = await readFile(path.join(root, relative), 'utf8');
  for (const needle of needles) assert.ok(content.toLowerCase().includes(needle.toLowerCase()), `${relative} must mention ${needle}`);
}

const workflow = await readFile(path.join(root, '.github/workflows/platform-v2.yml'), 'utf8');
for (const marker of ['name: Platform v2 quality', '  push:', '  pull_request:', "      - 'packages/pesantren-design-system/**'", 'permissions:\n  contents: read', 'postgres:16', 'DB_CONNECTION: central', "php-version: '8.3'", 'composer validate --no-check-publish', 'boost:install --no-interaction', 'vendor/bin/pint --test', 'php artisan test', 'npm install --no-audit --no-fund', 'npm run build', 'actions/upload-artifact@v4']) {
  assert.ok(workflow.includes(marker), `workflow must include ${marker}`);
}
assert.ok(!/^\s*workflow_dispatch:/m.test(workflow), 'workflow must run from branch/PR changes only');
assert.ok(!/deploy|production|ssh|rsync|scp|hosting_deploy|migrate --force.*https/i.test(workflow), 'quality workflow must not contain release/deployment behavior');

console.log('v2 documentation verification passed');
