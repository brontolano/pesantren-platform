import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(new URL(`../${path}`, import.meta.url), 'utf8');
const [tokens, appCss, controller, dashboard, organizations, shell, layout, js] = await Promise.all([
  read('packages/pesantren-design-system/resources/css/pesantren-ui.css'),
  read('apps/platform-v2/resources/css/app.css'),
  read('apps/platform-v2/app/Http/Controllers/Platform/PlatformConsoleController.php'),
  read('apps/platform-v2/resources/views/platform/dashboard.blade.php'),
  read('apps/platform-v2/resources/views/platform/organizations.blade.php'),
  read('packages/pesantren-design-system/resources/views/components/app-shell.blade.php'),
  read('apps/platform-v2/resources/views/layouts/app.blade.php'),
  read('apps/platform-v2/resources/js/app.js'),
]);

for (const token of ['#123c35', '#2f6b5b', '#dde9df', '#f7f5ef', '#b99a55', '#1f2d46']) {
  assert.ok(tokens.toLowerCase().includes(token), `approved palette token ${token} is missing`);
}
assert.match(appCss, /@import "\.\.\/\.\.\/\.\.\/\.\.\/packages\/pesantren-design-system\/resources\/css\/pesantren-ui\.css"/);
assert.match(appCss, /\.dashboard-grid\s*\{/);
assert.match(appCss, /@media\s*\(max-width:\s*760px\)/);
assert.doesNotMatch(appCss, /#[0-9a-f]{3,8}\s*;\s*\/\*\s*legacy/i);

assert.match(controller, /Organization::query\(\)[\s\S]*?selectRaw\('COUNT\(\*\) as total'\)/);
assert.match(controller, /AuditEvent::query\(\)[\s\S]*?groupByRaw\("DATE\(created_at AT TIME ZONE 'Asia\/Jakarta'\)"\)/);
assert.match(controller, /ModuleCatalogItem::query\(\)->where\('available', true\)/);
assert.match(controller, /whereHas\('moduleEntitlements'/);
assert.match(controller, /'service_state' => \['nullable', 'in:inactive,active,suspended'\]/);
assert.doesNotMatch(controller, /DB::connection\(['"]tenant/);

for (const label of ['Total organisasi', 'Perlu perhatian', 'Modul tersedia', 'Aktivitas 7 hari terakhir', 'Aktivitas terbaru']) {
  assert.ok(dashboard.includes(label), `dashboard must show ${label}`);
}
assert.match(dashboard, /\$trendMax/);
assert.match(dashboard, /\$recent->isEmpty\(\)/);
assert.match(organizations, /name="service_state"/);
assert.match(organizations, /name="module_id"/);
assert.match(organizations, /pu-data-table__mobile-list/);
assert.match(shell, /data-drawer-toggle/);
assert.match(shell, /role="dialog" aria-modal="true"/);
assert.match(layout, /context=.*Superadmin/);
for (const behavior of ["event.key === 'Escape'", 'drawerToggle?.focus', 'focusables[0]', 'last.focus()']) {
  assert.ok(js.includes(behavior), `mobile drawer must include ${behavior}`);
}
assert.doesNotMatch(tokens, /https?:\/\/|@import\s+url|@font-face/i, 'package CSS must not fetch remote assets');

console.log('platform design verification passed');
