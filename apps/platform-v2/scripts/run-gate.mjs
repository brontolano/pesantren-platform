import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { basename, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const appDirectory = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const gates = {
    foundation: {
        marker: 'foundation verification passed',
        tests: ['tests/Feature/PlatformAccessTest.php', 'tests/Feature/PlatformOperationsTest.php'],
        source: ['routes/web.php', 'app/Http/Middleware/EnsurePlatformOperator.php', 'resources/views/platform/dashboard.blade.php', 'resources/views/platform/activity.blade.php'],
        filter: 'PlatformAccessTest|PlatformOperationsTest',
    },
    onboarding: {
        marker: 'onboarding verification passed',
        tests: ['tests/Feature/TenantOnboardingTest.php'],
        source: ['app/Support/DomainCanonicalizer.php', 'app/Services/Provisioning/TenantProvisioningService.php', 'app/Services/Provisioning/PlatformInvitationService.php'],
        filter: 'TenantOnboardingTest',
    },
    'tenant-boundary': {
        marker: 'tenant boundary verification passed',
        tests: ['tests/Feature/TenantBoundaryTest.php'],
        source: ['app/Http/Middleware/ResolveTenantContext.php', 'app/Http/Middleware/EnsureTenantSessionMatches.php', 'app/Services/Tenancy/TenantConnection.php'],
        filter: 'TenantBoundaryTest',
    },
    operations: {
        marker: 'operations verification passed',
        tests: ['tests/Feature/PlatformOperationsTest.php'],
        source: ['app/Services/Platform/TechnicalMonitor.php', 'app/Models/ModuleCatalogItem.php', 'database/migrations/2026_11_28_000001_create_platform_core_tables.php'],
        filter: 'PlatformOperationsTest',
    },
};

const invokedAs = basename(process.argv[1] ?? '');
const key = process.argv[2] ?? ({
    'verify-foundation.mjs': 'foundation',
    'verify-onboarding.mjs': 'onboarding',
    'verify-tenant-boundary.mjs': 'tenant-boundary',
    'verify-operations.mjs': 'operations',
} [invokedAs]);
const gate = gates[key];
assert.ok(gate, `Unknown verification gate: ${key ?? '(missing)'}`);

for (const relativePath of [...gate.tests, ...gate.source]) {
    const absolutePath = resolve(appDirectory, relativePath);
    assert.ok(existsSync(absolutePath), `Required gate file is missing: ${relativePath}`);
    if (gate.tests.includes(relativePath)) {
        const content = readFileSync(absolutePath, 'utf8');
        assert.match(content, /function test_/, `${relativePath} must contain decisive feature assertions`);
    }
}

const result = spawnSync('php', ['artisan', 'test', '--filter', gate.filter], {
    cwd: appDirectory,
    encoding: 'utf8',
    windowsHide: true,
});

if (result.error) {
    console.error(`PHP feature tests could not start: ${result.error.message}`);
    process.exit(1);
}
if (result.stdout) process.stdout.write(result.stdout);
if (result.stderr) process.stderr.write(result.stderr);
if (result.status !== 0) {
    console.error(`Feature assertions failed for gate: ${key}`);
    process.exit(result.status ?? 1);
}

console.log(gate.marker);
