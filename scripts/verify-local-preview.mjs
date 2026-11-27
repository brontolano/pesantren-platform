import { readFile } from 'node:fs/promises';

const script = await readFile(new URL('./start-local.ps1', import.meta.url), 'utf8');
const database = script.match(/\$databaseName\s*=\s*'([^']+)'/)?.[1];
const requiredBindings = [
  /DB_DATABASE\s*=\s*\$databaseName/,
  /TEST_DB_DATABASE\s*=\s*\$databaseName/,
  /DB_HOST\s*=\s*'127\.0\.0\.1'/,
  /APP_URL\s*=\s*"http:\/\/127\.0\.0\.1:\$Port"/,
  /SESSION_DRIVER\s*=\s*'file'/,
  /artisan serve --host=127\.0\.0\.1 --port=\$Port/,
];

if (database !== 'pesantren_test_asrama_solo') {
  throw new Error('Preview must use the dedicated test database.');
}

if (requiredBindings.some((pattern) => !pattern.test(script))) {
  throw new Error('Preview server binding or database isolation is incomplete.');
}

const productionTarget = /\$databaseName\s*=\s*['"](?:pesantren_platform|pesantren_production)['"]/i;
if (!productionTarget.test("$databaseName = 'pesantren_platform'")) {
  throw new Error('Production database guard did not catch its positive control.');
}

if (productionTarget.test(script)) {
  throw new Error('Preview script contains a production database target.');
}

console.log('local preview configuration passed with persistent local sessions');
