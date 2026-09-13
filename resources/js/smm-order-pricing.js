function smmFormFrom(node) {
  const form = node?.closest?.('#createOrderForm');
  if (!form) return null;

  try {
    const path = new URL(form.action, window.location.origin).pathname;
    return /\/admin\/orders\/smm(?:\/|$)/.test(path) ? form : null;
  } catch (_) {
    return null;
  }
}

function num(value) {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function selectedOption(form) {
  const select = form.querySelector('.js-service');
  return select?.selectedOptions?.[0] || null;
}

function customFieldsForOption(option) {
  if (!option) return [];
  try {
    const parsed = JSON.parse(option.getAttribute('data-custom-fields') || '[]');
    return Array.isArray(parsed) ? parsed.filter(field => String(field?.active ?? 1) === '1') : [];
  } catch (_) {
    return [];
  }
}

function fieldNames(option) {
  return customFieldsForOption(option).map(field => String(field?.input || '').toLowerCase().trim()).filter(Boolean);
}

function inferMode(option) {
  const names = fieldNames(option);
  if (names.includes('min') && names.includes('max') && (names.includes('posts') || names.includes('old_posts') || names.includes('expiry'))) {
    return 'subscription';
  }
  if (names.includes('quantity') && (names.includes('runs') || names.includes('interval'))) {
    return 'drip';
  }
  if (!names.includes('quantity') && names.includes('comments')) {
    return 'comments';
  }
  if (!names.includes('quantity') && names.includes('usernames')) {
    return 'usernames';
  }
  if (names.length > 0 && !names.includes('quantity')) {
    return 'package';
  }
  return 'quantity';
}

function userBalance(form) {
  const select = form.querySelector('.js-step-user');
  return num(select?.selectedOptions?.[0]?.getAttribute('data-balance'));
}

function userGroupId(form) {
  const select = form.querySelector('.js-step-user');
  return num(select?.selectedOptions?.[0]?.getAttribute('data-group'));
}

function serviceRate(form, option) {
  if (!option) return 0;
  let groupPrices = {};
  try {
    groupPrices = JSON.parse(option.getAttribute('data-group-prices') || '{}');
  } catch (_) {
    groupPrices = {};
  }

  const groupRate = num(groupPrices[String(userGroupId(form))]);
  if (groupRate > 0) return groupRate;
  return Math.max(0, num(option.getAttribute('data-base-price')));
}

function namedControl(form, name) {
  return form.querySelector(`[name="required[${name}]"]`);
}

function positiveInt(value) {
  const parsed = Math.trunc(num(value));
  return parsed > 0 ? parsed : 0;
}

function lineCount(value) {
  return String(value || '').split(/\r\n|\n|\r/).map(line => line.trim()).filter(Boolean).length;
}

function quote(form) {
  const option = selectedOption(form);
  const rate = serviceRate(form, option);
  const mode = inferMode(option);
  const topQuantity = form.querySelector('input[name="quantity"]');
  const quantityControl = namedControl(form, 'quantity');
  const minLimit = positiveInt(option?.getAttribute('data-smm-min'));
  const maxLimit = positiveInt(option?.getAttribute('data-smm-max'));

  if (quantityControl && !String(quantityControl.value || '').trim() && minLimit > 0 && (mode === 'quantity' || mode === 'drip')) {
    quantityControl.value = String(minLimit);
  }

  const quantity = positiveInt(quantityControl?.value) || positiveInt(topQuantity?.value) || minLimit || 1;
  let units = quantity;
  let valid = true;
  let message = '';

  if (mode === 'package') {
    units = 1000;
  } else if (mode === 'drip') {
    const runs = positiveInt(namedControl(form, 'runs')?.value) || 1;
    units = quantity * runs;
  } else if (mode === 'comments') {
    units = lineCount(namedControl(form, 'comments')?.value);
    if (units < 1) {
      valid = false;
      message = 'Enter at least one comment to calculate the SMM price.';
    }
  } else if (mode === 'usernames') {
    units = lineCount(namedControl(form, 'usernames')?.value);
    if (units < 1) {
      valid = false;
      message = 'Enter at least one username to calculate the SMM price.';
    }
  } else if (mode === 'subscription') {
    const min = positiveInt(namedControl(form, 'min')?.value);
    const max = positiveInt(namedControl(form, 'max')?.value);
    const posts = Math.max(0, Math.trunc(num(namedControl(form, 'posts')?.value)));
    const oldPosts = Math.max(0, Math.trunc(num(namedControl(form, 'old_posts')?.value)));

    if (!min || !max) {
      valid = false;
      message = 'Subscription Min and Max are required for safe billing.';
    } else if (min !== max) {
      valid = false;
      message = 'For safe pre-charge, subscription Min and Max must be the same.';
    } else if ((posts + oldPosts) < 1) {
      valid = false;
      message = 'Set a finite Posts or Old Posts count. Unlimited subscriptions cannot be pre-charged safely.';
    } else {
      units = min * (posts + oldPosts);
    }
  }

  if ((mode === 'quantity' || mode === 'drip') && minLimit > 0 && quantity < minLimit) {
    valid = false;
    message = `Quantity must be at least ${minLimit}.`;
  }
  if ((mode === 'quantity' || mode === 'drip') && maxLimit > 0 && quantity > maxLimit) {
    valid = false;
    message = `Quantity must be at most ${maxLimit}.`;
  }

  const total = mode === 'package' ? rate : (rate * units / 1000);
  return { mode, rate, units, total: Math.round(total * 10000) / 10000, valid, message };
}

function ensurePricingError(form) {
  let element = form.querySelector('#smmPricingError');
  if (element) return element;

  element = document.createElement('div');
  element.id = 'smmPricingError';
  element.className = 'mt-2 text-danger d-none';

  const balanceError = form.querySelector('#balanceError');
  if (balanceError?.parentNode) balanceError.parentNode.insertBefore(element, balanceError);
  else form.querySelector('.modal-body')?.appendChild(element);

  return element;
}

function updateQuantityUi(form, current) {
  const quantityWrap = form.querySelector('#quantityWrap');
  const topQuantity = form.querySelector('input[name="quantity"]');
  const quantityHint = form.querySelector('#quantityHint');
  const customQuantity = namedControl(form, 'quantity');

  if (quantityWrap) {
    const useTopQuantity = current.mode === 'quantity' && !customQuantity;
    quantityWrap.classList.toggle('d-none', !useTopQuantity);
  }

  if (topQuantity && current.mode !== 'quantity') {
    topQuantity.value = '1';
  }

  if (quantityHint) {
    quantityHint.textContent = current.mode === 'package'
      ? 'Package service: the displayed rate is the full order price.'
      : current.mode === 'drip'
        ? 'Drip-feed total = quantity per run × runs. Rate is per 1,000 delivered units.'
        : current.mode === 'comments'
          ? 'Price is based on the number of non-empty comment lines.'
          : current.mode === 'usernames'
            ? 'Price is based on the number of non-empty username lines.'
            : current.mode === 'subscription'
              ? 'Subscription pre-charge is available only for fixed Min=Max and a finite post count.'
              : 'SMM rate is per 1,000 units.';
  }
}

function relabelServices(form) {
  const service = form.querySelector('.js-service');
  if (!service) return;
  const gid = userGroupId(form);

  Array.from(service.options).forEach(option => {
    if (!option.value) return;

    let groupPrices = {};
    try { groupPrices = JSON.parse(option.getAttribute('data-group-prices') || '{}'); } catch (_) { groupPrices = {}; }
    let rate = num(groupPrices[String(gid)]);
    if (!(rate > 0)) rate = Math.max(0, num(option.getAttribute('data-base-price')));

    const name = option.getAttribute('data-name') || option.textContent || '';
    const unit = inferMode(option) === 'package' ? ' / order' : ' / 1K';
    option.textContent = `${name} — $${rate.toFixed(4)}${unit}`;
  });
}

function update(form) {
  const option = selectedOption(form);
  if (!option?.value) return;

  relabelServices(form);
  const current = quote(form);
  updateQuantityUi(form, current);

  const balance = userBalance(form);
  const price = form.querySelector('#selectedPrice');
  const after = form.querySelector('#balanceAfter');
  const balanceError = form.querySelector('#balanceError');
  const pricingError = ensurePricingError(form);
  const button = form.querySelector('#btnCreateOrder');

  if (price) price.textContent = '$' + current.total.toFixed(4);
  if (after) after.textContent = '$' + (balance - current.total).toFixed(4);

  pricingError.textContent = current.message;
  pricingError.classList.toggle('d-none', current.valid);

  const enough = balance + 0.0000001 >= current.total;
  if (balanceError) {
    balanceError.textContent = 'Insufficient balance.';
    balanceError.classList.toggle('d-none', !current.valid || enough);
  }

  if (button && button.textContent !== 'Creating...' && (!current.valid || !enough || !(current.total > 0))) {
    button.disabled = true;
  }
}

function schedule(node) {
  const form = smmFormFrom(node);
  if (!form) return;
  window.setTimeout(() => update(form), 0);
}

document.addEventListener('input', event => schedule(event.target));
document.addEventListener('change', event => schedule(event.target));
document.addEventListener('shown.bs.modal', event => {
  const form = event.target?.querySelector?.('#createOrderForm');
  if (form && smmFormFrom(form)) window.setTimeout(() => update(form), 0);
});

document.addEventListener('submit', event => {
  const form = smmFormFrom(event.target);
  if (!form) return;

  const current = quote(form);
  const enough = userBalance(form) + 0.0000001 >= current.total;
  if (!current.valid || !enough || !(current.total > 0)) {
    event.preventDefault();
    event.stopImmediatePropagation();
    update(form);
  }
}, true);
