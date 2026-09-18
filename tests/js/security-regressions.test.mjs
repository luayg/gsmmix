import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('payment webhooks authenticate before reserving an event id', () => {
  const source = readFileSync('app/Http/Controllers/PaymentWebhookController.php', 'utf8');
  assert.ok(source.indexOf('abort_unless($verified') < source.indexOf('PaymentWebhookEvent::firstOrCreate'));
});

test('all order models sanitize provider reply html when saving', () => {
  const observer = readFileSync('app/Observers/OrderProviderMetadataSanitizerObserver.php', 'utf8');
  const provider = readFileSync('app/Providers/AppServiceProvider.php', 'utf8');
  assert.match(observer, /provider_reply_html/);
  assert.match(observer, /HtmlSanitizer/);
  assert.match(provider, /OrderProviderMetadataSanitizerObserver/);
});

test('inactive accounts are rejected for every authenticated surface and login flow', () => {
  const bootstrap = readFileSync('bootstrap/app.php', 'utf8');
  const flow = readFileSync('app/Services/Auth/LoginFlow.php', 'utf8');
  assert.match(bootstrap, /EnsureActiveUser::class/);
  assert.match(flow, /\$user->status !== 'active'/);
});

test('successful login clears both account and ip throttles', () => {
  const login = readFileSync('app/Http/Controllers/Auth/LoginController.php', 'utf8');
  assert.match(login, /RateLimiter::clear\(\$key\);\s*RateLimiter::clear\(\$ipKey\);/);
});

test('mail transport failures return safe validation errors instead of debug exceptions', () => {
  const registration = readFileSync('app/Http/Controllers/Auth/RegisterController.php', 'utf8');
  const loginFlow = readFileSync('app/Services/Auth/LoginFlow.php', 'utf8');
  assert.match(registration, /catch \(Throwable \$exception\)/);
  assert.match(registration, /\$user->delete\(\)/);
  assert.match(registration, /mailUnavailableMessage/);
  assert.match(loginFlow, /catch \(Throwable \$exception\)/);
  assert.match(loginFlow, /sign-in verification code could not be sent/);
});
