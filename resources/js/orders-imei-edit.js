// resources/js/orders-imei-edit.js
import { initModalEditors } from './modal-editors';

console.log('orders-imei-edit loaded ✅');

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-open-order-edit');
  if (!btn) return;

  // ✅ prevent any global handlers (like js-open-modal / ajaxModal)
  e.preventDefault();
  e.stopPropagation();
  e.stopImmediatePropagation();

  const url = btn.dataset.url || btn.getAttribute('href');
  if (!url || url === '#') return;

  const modalEl = document.getElementById('orderEditModal');
  if (!modalEl) {
    console.error('❌ orderEditModal not found. Make sure it exists in DOM.');
    return;
  }

  const contentEl = modalEl.querySelector('.modal-content');
  if (!contentEl) return;

  // loader
  contentEl.innerHTML = `
    <div class="modal-body py-5 text-center text-muted">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="mt-3">Loading…</div>
    </div>
  `;

  // show modal first (OK)
  const modal =
    window.bootstrap?.Modal.getOrCreateInstance(modalEl) ||
    new window.bootstrap.Modal(modalEl);

  modal.show();

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load edit modal: ' + res.status);

    const html = await res.text();
    contentEl.innerHTML = html;

    // ✅ IMPORTANT FIX:
    // modal is already shown, so shown.bs.modal will NOT fire now.
    // initialize editors immediately.
    try {
      await initModalEditors(contentEl);
      console.log('✅ initModalEditors done');
    } catch (err) {
      console.error('❌ initModalEditors error:', err);
    }

  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});
