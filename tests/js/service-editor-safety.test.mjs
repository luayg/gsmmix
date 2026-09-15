import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync('resources/views/admin/partials/service-modal.blade.php', 'utf8');

test('opening an inactive service preserves its status and other saved flags', () => {
  const code = source.slice(source.indexOf('  function applyRemoteServiceSettings('), source.indexOf('  function resolveHooks('));
  for (const active of [0, 1]) {
    const controls = Object.fromEntries(['active', 'allow_bulk', 'allow_cancel'].map(k => [k, { checked: true }]));
    const scope = { querySelector: selector => controls[selector.match(/name="([^"]+)"/)[1]] ?? null };
    const ctx = vm.createContext({});
    vm.runInContext(code, ctx);
    ctx.applyRemoteServiceSettings(scope, { dataset: { active: String(active), allowBulk: '0', allowCancel: '1' } });
    assert.equal(controls.active.checked, Boolean(active));
    assert.equal(controls.allow_bulk.checked, false);
    assert.equal(controls.allow_cancel.checked, true);
  }
});

test('edit restores service subtype rather than retaining the create template default', () => {
  const line = source.split('\n').find(line => line.includes("value = s.type || ''"));
  assert.ok(line);
  const control = { value: 'imei' };
  vm.runInNewContext(line, { form: { querySelector: () => control }, s: { type: 'server' } });
  assert.equal(control.value, 'server');
});

test('changing provider clears the old remote ID and selecting a service updates submitted routing', async () => {
  const code = source.slice(source.indexOf('    const editProviderSelect ='), source.indexOf('        // Keep saved local service options'));
  const events = {};
  const provider = { value: '2', addEventListener: (type, fn) => { events.provider = fn; } };
  const service = { value: '88', addEventListener: (type, fn) => { events.service = fn; } };
  const controls = { supplier_id: { value: '1' }, remote_id: { value: '42' } };
  const loaded = [];
  vm.runInNewContext(code, {
    body: { querySelector: key => key === '#apiProviderSelect' ? provider : service },
    form: { querySelector: key => controls[key.match(/name="([^"]+)"/)[1]] },
    serviceType: 'server', loadProviderServices: async (_, id, kind) => { loaded.push([id, kind]); },
  });
  await events.provider();
  assert.equal(controls.supplier_id.value, '2');
  assert.equal(controls.remote_id.value, '');
  assert.deepEqual(loaded, [['2', 'server']]);
  events.service();
  assert.equal(controls.remote_id.value, '88');
});
