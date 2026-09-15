import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import test from 'node:test';
import assert from 'node:assert/strict';

function fixture(kind, prices) {
  const listeners = {};
  const option = { value: '10', getAttribute: key => ({
    'data-base-price': '25', 'data-group-prices': JSON.stringify(prices),
    'data-custom-fields': '[]', 'data-name': 'Test service',
  })[key] ?? null };
  const userOption = { getAttribute: key => key === 'data-group' ? '7' : '0' };
  const user = { value: '20', selectedOptions: [userOption] };
  const service = { value: '10', selectedOptions: [option], options: [option] };
  const form = { action: `https://example.test/admin/orders/${kind}`,
    closest: () => form,
    querySelector: key => ({ '.js-step-user': user, '.js-service': service,
      'input[name="quantity"]': { value: '1000' } })[key] ?? null,
  };
  const context = vm.createContext({ URL,
    window: { location: { origin: 'https://example.test' } },
    document: { addEventListener: (type, handler) => { listeners[type] = handler; } },
  });
  return { context, form, option, listeners };
}

for (const kind of ['server', 'smm']) {
  test(`${kind}: order preview uses zero overrides and falls back only for missing/invalid prices`, () => {
    const source = readFileSync(`resources/js/${kind === 'server' ? 'server-order-quantity' : 'smm-order-pricing'}.js`, 'utf8');
    for (const [prices, expected] of [[{7: 0}, 0], [{7: '0.0000'}, 0], [{7: 12.3456}, 12.3456], [{}, 25], [{7: -1}, 25], [{7: null}, 25]]) {
      const f = fixture(kind, prices);
      vm.runInContext(source, f.context);
      const actual = kind === 'server' ? f.context.priceForUser(f.form) : f.context.serviceRate(f.form, f.option);
      assert.equal(actual, expected);
      if (expected === 0) {
        // Zero-balance users can submit a valid free order.
        let blocked = false;
        f.listeners.submit({ target: f.form, preventDefault: () => { blocked = true; }, stopImmediatePropagation() {} });
        assert.equal(blocked, false);
      }
    }
  });
}

test('shared order modal shows a free price and enables submission for a zero balance', () => {
  const source = readFileSync('resources/views/admin/orders/modals/create.blade.php', 'utf8');
  const priceCode = source.slice(source.indexOf('  function servicePriceForUser()'), source.indexOf('  function getMainMeta()'));
  const summaryCode = source.slice(source.indexOf('  function updateSummary()'), source.indexOf('  function lockVisualOnly()'));
  const f = fixture('imei', {7: 0});
  const button = { disabled: true };
  Object.assign(f.context, {
    selectedServiceOption: () => f.option, userGroupId: () => 7, userBalance: () => 0,
    validateMainDeviceInputs: () => true, selectedPriceEl: {}, selectedBalanceEl: {}, balanceAfterEl: {},
    userSel: { value: '20' }, serviceSel: { value: '10' }, balanceErrorEl: {},
    btnCreate: button, money: value => value.toFixed(2), show() {}, hide() {},
  });
  vm.runInContext(priceCode + summaryCode, f.context);
  f.context.updateSummary();
  assert.equal(f.context.selectedPriceEl.textContent, '0.00');
  assert.equal(button.disabled, false);
});
