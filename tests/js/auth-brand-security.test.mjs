import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('authenticated pages disable caching and logout returns home', () => {
  const middleware = readFileSync('app/Http/Middleware/PreventAuthenticatedResponseCaching.php', 'utf8');
  const bootstrap = readFileSync('bootstrap/app.php', 'utf8');
  const login = readFileSync('app/Http/Controllers/Auth/LoginController.php', 'utf8');
  const customer = readFileSync('resources/views/layouts/customer.blade.php', 'utf8');
  assert.match(middleware, /no-store, no-cache, must-revalidate, private/);
  assert.match(bootstrap, /PreventAuthenticatedResponseCaching::class/);
  assert.match(login, /redirect\(\)->route\('home'\)/);
  assert.match(customer, /event\.persisted/);
});

test('authentication screens and customer sidebar use the configured brand', () => {
  for (const path of ['resources/views/auth/login.blade.php','resources/views/auth/register.blade.php','resources/views/layouts/customer.blade.php']) {
    assert.match(readFileSync(path, 'utf8'), /shared\.brand/);
  }
  const css = readFileSync('resources/css/app.css', 'utf8');
  assert.match(css, /\.auth-page\{/);
  assert.match(css, /place-items:center/);
});

test('registration email verification uses a six digit expiring code', () => {
  const controller = readFileSync('app/Http/Controllers/Auth/RegisterController.php', 'utf8');
  const routes = readFileSync('routes/web.php', 'utf8');
  const settings = readFileSync('resources/views/admin/settings/general.blade.php', 'utf8');
  assert.match(controller, /random_int\(100000, 999999\)/);
  assert.match(controller, /addMinutes\(15\)/);
  assert.match(controller, /Hash::check/);
  assert.match(routes, /register\.verify\.submit/);
  assert.match(settings, /email_verification_enabled/);
});
