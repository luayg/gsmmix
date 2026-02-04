// resources/js/orders-imei-edit.js
import { initModalEditors } from './modal-editors';

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-open-order-edit');
  if (!btn) return;

  e.preventDefault();

  const url = btn.dataset.url || btn.getAttribute('href');
  if (!url || url === '#') return;

  const modalEl = document.getElementById('orderEditModal');
  if (!modalEl) return;

  const contentEl = modalEl.querySelector('.modal-content');
  if (!contentEl) return;

  contentEl.innerHTML = `
    <div class="modal-body py-5 text-center text-muted">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="mt-3">Loading…</div>
    </div>
  `;

  const modal = window.bootstrap?.Modal.getOrCreateInstance(modalEl) || new window.bootstrap.Modal(modalEl);
  modal.show();

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load edit modal: ' + res.status);

    const html = await res.text();
    contentEl.innerHTML = html;

    // ✅ init after modal is fully visible
    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      try {
        await initModalEditors(contentEl);
      } catch (err) {
        console.error(err);
      }
    };

    modalEl.addEventListener('shown.bs.modal', onShown);
  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});
