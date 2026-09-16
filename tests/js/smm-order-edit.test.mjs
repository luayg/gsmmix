import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('SMM order list loads the shared order edit modal controller', () => {
  const source = readFileSync('resources/views/admin/orders/smm/index.blade.php', 'utf8');
  assert.match(source, /resources\/js\/orders-imei-edit\.js/);
});
