import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const hook = readFileSync('resources/views/admin/services/partials/saved-fields-hook.blade.php', 'utf8');
const shell = readFileSync('resources/views/admin/partials/service-modal.blade.php', 'utf8');
for (const kind of ['imei', 'server', 'file', 'smm']) {
  test(`${kind}: saved field properties survive restore and form serialization`, () => {
    const template = readFileSync(`resources/views/admin/services/${kind}/_modal_create.blade.php`, 'utf8');
    assert.ok(template.includes(`@include('admin.services.partials.saved-fields-hook', ['kind' => '${kind}'])`));
    const start = template.indexOf('  function serializeFieldsInScope(scope)');
    const end = template.indexOf('  function bindCard(card)', start);
    assert.ok(start >= 0 && end > start);
    const cards = [];
    let removed = 0;
    const out = { value: '' };
    const scope = { querySelector: selector => selector === '#customFieldsJson' ? out : {
      querySelectorAll: () => cards,
    } };
    // Model the controls consumed by the production serializer. The mapping
    // and serialization functions themselves are executed from shipped code.
    const addField = (_scope, field) => {
      const controls = Object.fromEntries(Object.entries({
        type: field.type, options: field.options, name: field.name, input: field.input,
        desc: field.description, min: field.minimum, max: field.maximum,
        validation: field.validation, required: field.required,
      }).map(([key, value]) => [`[data-${key}]`, { value: String(value) }]));
      controls['[data-active]'] = { checked: field.active };
      const card = { querySelector: selector => controls[selector], remove() {
        removed++;
        cards.splice(cards.indexOf(card), 1);
      } };
      cards.push(card);
    };
    const ctx = vm.createContext({ window: {}, addField, syncParamsHidden() {} });
    vm.runInContext(template.slice(start, end), ctx);
    vm.runInContext(hook.replaceAll('{{ $kind }}', kind), ctx);
    const restore = ctx.window[`__${kind}ServiceRestoreSavedFields__`];
    const fields = Array.from({ length: 35 }, (_, i) => ({
      // Duplicate labels and more than 30 fields are valid saved data.
      name: 'Same label', input_name: `exact_api_key_${i}`, active: '0',
      field_type: 'select', min: 3, max: 48, validation: 'alphanumeric',
      required: 1, description: 'Keep description', options: ['A', 'B'],
    }));
    restore(scope, fields);
    const result = JSON.parse(out.value);
    assert.equal(result.length, 35);
    for (let i = 0; i < result.length; i++) {
      assert.deepEqual(result[i], {
        active: 0, name: 'Same label', type: 'select', input: `exact_api_key_${i}`,
        description: 'Keep description', minimum: 3, maximum: 48,
        validation: 'alphanumeric', required: 1, options: 'A,B',
      });
    }
    restore(scope, [{ name: { en: 'Database label' }, input: 'db_input', type: 'text',
      active: 1, minimum: 0, maximum: 0, validation: 'none', required: 0,
      description: { fallback: 'Database description' }, options: { en: 'X,Y' } }]);
    assert.equal(removed, 35);
    assert.deepEqual(JSON.parse(out.value), [{ active: 1, name: 'Database label', type: 'text',
      input: 'db_input', description: 'Database description', minimum: 0, maximum: 0,
      validation: 'none', required: 0, options: 'X,Y' }]);
    restore(scope, []);
    assert.deepEqual(JSON.parse(out.value), []);
  });
}
test('edit hydration uses the saved-field hook, including empty lists', () => {
  const start = shell.indexOf('    const custom = Array.isArray(s.custom_fields)');
  const end = shell.indexOf('    const userGroups = await loadUserGroups();', start);
  const calls = [];
  const ctx = vm.createContext({ s: { custom_fields: [] }, params: {}, body: {}, serviceType: 'server',
    resolveHooks: () => ({ restore: (_body, fields) => calls.push(fields), apply: () => assert.fail('Used provider import') }),
    openGeneralTab() {},
  });
  assert.ok(start >= 0 && end > start);
  vm.runInContext(shell.slice(start, end), ctx);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].length, 0);
});
