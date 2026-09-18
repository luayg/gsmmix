import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('customer order requests only queue provider work', () => {
  const base = readFileSync('app/Http/Controllers/Admin/Orders/BaseOrdersController.php', 'utf8');
  const smm = readFileSync('app/Http/Controllers/Admin/Orders/SmmOrdersController.php', 'utf8');
  const products = readFileSync('app/Services/Orders/ProductOrderService.php', 'utf8');

  assert.doesNotMatch(base, /OrderDispatcher::class/);
  assert.doesNotMatch(smm, /OrderDispatcher/);
  assert.doesNotMatch(products, /OrderDispatcher::class/);
  assert.match(base, /\$order->status\s*=\s*'waiting'/);
  assert.match(smm, /\$order->status\s*=\s*'waiting'/);
  assert.match(products, /'status'\s*=>\s*'waiting'/);
});

test('local development runs the automatic order scheduler', () => {
  const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
  assert.equal(pkg.scripts['dev:scheduler'], 'php artisan schedule:work');
  assert.match(pkg.scripts['dev:all'], /npm:dev:scheduler/);
});
