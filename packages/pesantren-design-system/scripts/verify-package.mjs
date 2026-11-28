import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relative) => readFile(path.join(root, relative), 'utf8');

const manifest = JSON.parse(await read('composer.json'));
assert.equal(manifest.name, 'brontolano/pesantren-online-design-system');
assert.equal(manifest.type, 'library');
assert.equal(manifest.require.php, '^8.3');
assert.equal(manifest.require['illuminate/support'], '^13.0');
assert.ok(!Object.keys(manifest.require).some((name) => name.startsWith('filament/')));
assert.ok(manifest.extra.laravel.providers.includes('Brontolano\\PesantrenDesignSystem\\PesantrenDesignSystemServiceProvider'));

const provider = await read('src/PesantrenDesignSystemServiceProvider.php');
assert.match(provider, /loadViewsFrom\([^,]+,\s*'pesantren-ui'\)/);
assert.match(provider, /pesantren-ui-assets/);

const workflow = await read('.github/workflows/quality.yml');
for (const marker of [
  'name: Pesantren.online UI package quality',
  '  push:',
  '  pull_request:',
  'permissions:\n  contents: read',
  "php-version: '8.3'",
  'node scripts/verify-package.mjs',
  'php -l src/PesantrenDesignSystemServiceProvider.php',
]) assert.ok(workflow.includes(marker), 'package workflow must include ' + marker);
assert.doesNotMatch(workflow, /deploy|production|ssh|rsync|scp|publish/i);

const components = [
  'resources/views/components/app-shell.blade.php',
  'resources/views/components/page-header.blade.php',
  'resources/views/components/button.blade.php',
  'resources/views/components/badge.blade.php',
  'resources/views/components/card.blade.php',
  'resources/views/components/field.blade.php',
  'resources/views/components/status.blade.php',
  'resources/views/components/data-table.blade.php',
  'resources/views/components/icon.blade.php',
  'resources/views/components/metric-card.blade.php',
  'resources/views/components/empty-state.blade.php',
];
for (const file of components) assert.ok((await read(file)).length > 50, file + ' must be implemented');

const css = await read('resources/css/pesantren-ui.css');
assert.match(css, /--pu-primitive-forest-900:\s*#123c35/i);
assert.match(css, /--pu-primitive-jade-700:\s*#2f6b5b/i);
assert.match(css, /--pu-primitive-sage-100:\s*#dde9df/i);
assert.match(css, /--pu-primitive-ivory-50:\s*#f7f5ef/i);
assert.match(css, /--pu-primitive-gold-500:\s*#b99a55/i);
assert.match(css, /--pu-primitive-navy-900:\s*#1f2d46/i);
assert.match(css, /--pu-color-brand-700:\s*var\(--pu-primitive-forest-900\)/i);
assert.match(css, /:focus-visible/);
assert.match(css, /@media\s*\(max-width:\s*48rem\)/);
assert.match(css, /\.pu-data-table__desktop\s*\{\s*display:\s*none;/);
assert.match(css, /\.pu-data-table__mobile\s*\{\s*display:\s*block;/);
assert.match(css, /overflow-wrap:\s*anywhere/);
assert.match(css, /\.pu-shell__drawer-panel\s*\{/);
assert.match(css, /\.pu-mobile-menu-button\s*\{/);
assert.match(css, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
assert.match(css, /\.pu-data-table__desktop th, \.pu-data-table__desktop td\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*word-break:\s*normal;/);
assert.doesNotMatch(css, /https?:\/\/|@import\s+url|@font-face/i);

const docs = await Promise.all([
  read('README.md'),
  read('docs/design-tokens.md'),
  read('docs/components.md'),
  read('docs/accessibility-and-layout.md'),
  read('docs/testing-and-release.md'),
  read('.github/workflows/quality.yml'),
  read('CONTRIBUTING.md'),
]);
const joinedDocs = docs.join('\n').toLowerCase();
for (const phrase of ['laravel 13', 'livewire 4', 'wcag', 'composer require', 'kartu', 'kontribusi', 'rilis']) {
  assert.ok(joinedDocs.includes(phrase), 'package documentation must cover ' + phrase);
}

console.log('UI package verification passed');
