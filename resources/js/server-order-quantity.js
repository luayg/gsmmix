function serverOrderFormFrom(node) {
  const form = node?.closest?.('#createOrderForm');
  if (!form) return null;

  try {
    const path = new URL(form.action, window.location.origin).pathname;
    return /\/admin\/orders\/server(?:\/|$)/.test(path) ? form : null;
  } catch (_) {
    return null;
  }
}

function number(value) {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function priceForUser(form) {
  const user = form.querySelector('.js-step-user');
  const service = form.querySelector('.js-service');
  const option = service?.selectedOptions?.[0];
  if (!user?.value || !option?.value) return 0;

  const userOption = user.selectedOptions?.[0];
  const groupId = number(userOption?.getAttribute('data-group'));

  let groupPrices = {};
  try {
    groupPrices = JSON.parse(option.getAttribute('data-group-prices') || '{}');
  } catch (_) {
    groupPrices = {};
  }

  const groupPrice = number(groupPrices[String(groupId)]);
  if (groupPrice > 0) return groupPrice;

  return Math.max(0, number(option.getAttribute('data-base-price')));
}

function state(form) {
  const user = form.querySelector('.js-step-user');
  const service = form.querySelector('.js-service');
  const userOption = user?.selectedOptions?.[0];
  const quantityInput = form.querySelector('input[name="quantity"]');
  const quantity = Math.max(1, Math.trunc(number(quantityInput?.value) || 1));
  const unitPrice = priceForUser(form);
  const total = Math.round(unitPrice * quantity * 100) / 100;
  const balance = number(userOption?.getAttribute('data-balance'));

  return { user, service, quantity, unitPrice, total, balance };
}

function updateServerOrderSummary(form) {
  const current = state(form);
  const price = form.querySelector('#selectedPrice');
  const after = form.querySelector('#balanceAfter');
  const error = form.querySelector('#balanceError');
  const button = form.querySelector('#btnCreateOrder');

  if (price) price.textContent = '$' + current.total.toFixed(2);
  if (after) after.textContent = '$' + (current.balance - current.total).toFixed(2);

  const selected = Boolean(current.user?.value && current.service?.value && current.unitPrice > 0);
  const enough = current.balance >= current.total;

  if (error) {
    error.classList.toggle('d-none', !selected || enough);
  }

  // The inline modal script owns the other validation rules. We only make its
  // balance decision stricter for Server quantity and never re-enable Creating...
  if (button && button.textContent !== 'Creating...' && selected && !enough) {
    button.disabled = true;
  }
}

function scheduleUpdate(node) {
  const form = serverOrderFormFrom(node);
  if (!form) return;
  window.setTimeout(() => updateServerOrderSummary(form), 0);
}

document.addEventListener('input', (event) => scheduleUpdate(event.target));
document.addEventListener('change', (event) => scheduleUpdate(event.target));
document.addEventListener('shown.bs.modal', (event) => {
  const form = event.target?.querySelector?.('#createOrderForm');
  if (form && serverOrderFormFrom(form)) updateServerOrderSummary(form);
});

document.addEventListener('submit', (event) => {
  const form = serverOrderFormFrom(event.target);
  if (!form) return;

  const current = state(form);
  if (current.user?.value && current.service?.value && current.unitPrice > 0 && current.balance < current.total) {
    event.preventDefault();
    event.stopImmediatePropagation();
    updateServerOrderSummary(form);
  }
}, true);
