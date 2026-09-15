import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync('resources/views/admin/partials/service-modal.blade.php', 'utf8');
function fixture(saved = []) {
  class Element extends EventTarget {
    constructor(value = '') { super(); this.value = value; this.dataset = {}; this.textContent = ''; }
  }
  const rows = [];
  const cost = new Element('20');
  const profit = new Element('5');
  const type = new Element('1');
  const wrap = { set innerHTML(value) { rows.length = 0; }, appendChild(row) { rows.push(row); }, querySelectorAll() { return rows; } };
  const scope = { querySelector(selector) {
    return { '#groupsPricingWrap': wrap, '[name="cost"]': cost, '[name="profit"]': profit, '[name="profit_type"]': type }[selector] ?? null;
  } };
  const document = { getElementById() { return null; }, createElement() {
    const row = new Element();
    row.controls = {};
    Object.defineProperty(row, 'innerHTML', { set(html) {
      const price = html.match(/name="group_prices\[[^\]]+\]\[price\]" value="([^"]*)"/)[1];
      row.controls = { '[data-price]': new Element(price), '[data-discount]': new Element('0.0000'),
        '[data-price-mode]': new Element('1'), '[data-price-mode-label]': new Element(),
        '[data-discount-type]': new Element('1'), '[data-final]': new Element(), '.btn-reset': new Element() };
      row.controls['[data-price]'].dataset.autoPrice = '1';
    } });
    row.querySelector = selector => row.controls[selector];
    return row;
  } };
  const ctx = vm.createContext({ document, Event, clean: value => value });
  const calculations = source.slice(source.indexOf('  function calcServiceFinalPrice'), source.indexOf('  async function loadUserGroups'));
  const builder = source.slice(source.indexOf('  function buildPricingTable'), source.indexOf('  function ensureApiUI'));
  vm.runInContext(calculations + builder, ctx);
  ctx.buildPricingTable(scope, [{ id: 1, name: 'VIP' }, { id: 2, name: 'Regular' }], saved);
  const helper = ctx.initPrice(scope);
  return { rows, scope, helper, ctx, cost };
}
const control = (row, name) => row.querySelector(`[data-${name}]`);
const serialize = rows => rows.map((row, index) => ({
  group_id: index + 1,
  price: Number(control(row, 'price').value),
  auto_price: Number(control(row, 'price-mode').value),
  discount: Number(control(row, 'discount').value),
  discount_type: Number(control(row, 'discount-type').value),
}));

test('saved fractional and zero prices load immediately and survive service repricing', () => {
  const { rows, helper } = fixture([
    { group_id: 1, price: 12.3456, discount: 10, discount_type: 2 },
    { group_id: 2, price: 0, discount: 0, discount_type: 1 },
  ]);
  assert.equal(control(rows[0], 'price').value, '12.3456');
  assert.equal(control(rows[0], 'final').textContent, '11.1110');
  assert.equal(control(rows[1], 'price').value, '0.0000');
  helper.setCost(100);
  assert.equal(control(rows[0], 'price').value, '12.3456');
  assert.equal(control(rows[0], 'discount').value, '10.0000');
  assert.equal(control(rows[0], 'discount-type').value, '2');
  assert.equal(control(rows[1], 'price').value, '0.0000');
});

test('unsaved groups follow service price while final preview retains their discount', () => {
  const { rows, helper } = fixture([{ group_id: 1, price: 9, discount: 2, discount_type: 1 }]);
  assert.equal(control(rows[0], 'final').textContent, '7.0000');
  assert.equal(control(rows[1], 'price').value, '25.0000');
  control(rows[1], 'discount').value = '10';
  control(rows[1], 'discount-type').value = '2';
  control(rows[1], 'discount-type').dispatchEvent(new Event('change'));
  helper.setCost(35);
  assert.equal(control(rows[0], 'price').value, '9.0000');
  assert.equal(control(rows[1], 'price').value, '40.0000');
  assert.equal(control(rows[1], 'final').textContent, '36.0000');
});

test('manual editing disables auto pricing and Reset explicitly restores it', () => {
  const { rows, helper } = fixture();
  const row = rows[0];
  control(row, 'price').value = '7.1250';
  control(row, 'price').dispatchEvent(new Event('input'));
  helper.setCost(100);
  assert.equal(control(row, 'price').value, '7.1250');
  row.querySelector('.btn-reset').dispatchEvent(new Event('click'));
  assert.equal(control(row, 'price').value, '105.0000');
  assert.equal(control(row, 'discount').value, '0.0000');
  assert.equal(control(row, 'discount-type').value, '1');
  helper.setCost(20);
  assert.equal(control(row, 'price').value, '25.0000');
});

test('edit initialization supplies saved prices synchronously', () => {
  const start = source.indexOf('    const userGroups = await loadUserGroups();', source.indexOf('    const custom = Array.isArray(s.custom_fields)'));
  const end = source.indexOf('    const modal = window.bootstrap.Modal', start);
  const body = source.slice(start, end);
  assert.ok(body.includes('buildPricingTable(body, userGroups, s.group_prices)'));
  assert.ok(!body.includes('setTimeout'));
});

test('automatic rows and both discount types survive serialization, reopening and repricing', () => {
  for (const [discountType, expected] of [[1, '37.0000'], [2, '38.8000']]) {
    const initial = fixture();
    control(initial.rows[0], 'discount').value = '3';
    control(initial.rows[0], 'discount-type').value = String(discountType);
    const reopened = fixture(serialize(initial.rows));
    reopened.helper.setCost(35);
    assert.equal(control(reopened.rows[0], 'price').value, '40.0000');
    assert.equal(control(reopened.rows[0], 'price-mode').value, '1');
    assert.equal(control(reopened.rows[0], 'final').textContent, expected);
  }
});

test('Reset survives save/reopen and changing profit amount or type', () => {
  const initial = fixture([{ group_id: 1, price: 7, discount: 2, discount_type: 1 }]);
  initial.rows[0].querySelector('.btn-reset').dispatchEvent(new Event('click'));
  const reopened = fixture(serialize(initial.rows));
  reopened.helper.setCost(40);
  const profit = reopened.scope.querySelector('[name="profit"]');
  const type = reopened.scope.querySelector('[name="profit_type"]');
  profit.value = '10';
  profit.dispatchEvent(new Event('input'));
  assert.equal(control(reopened.rows[0], 'price').value, '50.0000');
  type.value = '2';
  type.dispatchEvent(new Event('change'));
  assert.equal(control(reopened.rows[0], 'price').value, '44.0000');
  assert.equal(control(reopened.rows[0], 'discount').value, '0.0000');
  assert.equal(control(reopened.rows[0], 'discount-type').value, '1');
});

test('manual prices equal to the default and zero remain manual after reopening', () => {
  const initial = fixture();
  for (const [i, value] of [[0, '25.0000'], [1, '0.0000']]) {
    control(initial.rows[i], 'price').value = value;
    control(initial.rows[i], 'price').dispatchEvent(new Event('input'));
  }
  const reopened = fixture(serialize(initial.rows));
  reopened.helper.setCost(100);
  assert.equal(control(reopened.rows[0], 'price').value, '25.0000');
  assert.equal(control(reopened.rows[1], 'price').value, '0.0000');
  assert.ok(serialize(reopened.rows).every(row => row.auto_price === 0));
});
