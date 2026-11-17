// Cross-platform test entry. No running development server or local account is required.
import { existsSync, readdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const mode = process.argv[2] ?? 'frontend';
if (!['syntax', 'frontend', 'backend', 'mysql', 'all'].includes(mode)) {
  console.error('Use syntax, frontend, backend, mysql or all.');
  process.exit(2);
}
function run(command, args, cwd = root, env = process.env) {
  const result = spawnSync(command, args, { cwd, env, stdio: 'inherit', shell: false });
  if (result.error) {
    console.error(`Could not start ${command}: ${result.error.message}`);
    process.exit(1);
  }
  if (result.status !== 0) process.exit(result.status ?? 1);
}
if (mode === 'syntax' || mode === 'all') {
  const files = readdirSync(resolve(root, 'frontend/src')).filter(file => file.endsWith('.js')).sort();
  for (const file of files) run(process.execPath, ['--check', resolve(root, 'frontend/src', file)]);
  run(process.execPath, ['--check', fileURLToPath(import.meta.url)]);
  console.log('JavaScript syntax checks passed.');
}
if (mode === 'frontend' || mode === 'all') {
  const files = readdirSync(resolve(root, 'frontend/tests')).filter(file => file.endsWith('.test.mjs')).sort();
  if (!files.length) throw new Error('No frontend tests found.');
  run(process.execPath, ['--test', ...files.map(file => resolve(root, 'frontend/tests', file))]);
}
if (mode === 'backend' || mode === 'mysql' || mode === 'all') {
  const backend = resolve(root, 'backend');
  if (!existsSync(resolve(backend, 'vendor/autoload.php'))) {
    console.error('Install backend/composer.lock dependencies first; see README.md.');
    process.exit(1);
  }
  const portable = resolve(root, '.tools/php84/php.exe');
  const php = process.env.PHP_BINARY || (process.platform === 'win32' && existsSync(portable) ? portable : 'php');
  // Explicit test settings take precedence over shell/.env database values.
  const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array' };
  if (mode === 'mysql') {
    if (process.env.REPOSITORY_MYSQL_TEST !== 'isolated') throw new Error('Set REPOSITORY_MYSQL_TEST=isolated only for a disposable blood_repository_ci database.');
    Object.assign(env, { DB_CONNECTION: 'mysql', DB_DATABASE: 'blood_repository_ci' });
  }
  run(php, ['artisan', 'config:clear', '--no-interaction'], backend, env);
  run(php, mode === 'mysql' ? ['vendor/bin/phpunit', '--configuration=phpunit.mysql.xml'] : ['artisan', 'test', '--compact'], backend, env);
}
