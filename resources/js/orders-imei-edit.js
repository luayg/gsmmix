// resources/js/orders-imei-edit.js
import { initModalEditors } from './modal-editors';

function nextFrame() {
  return new Promise((r) => requestAnimationFrame(() => r()));
}

async function runInitSafely(modalEl, contentEl) {
  await nextFrame();
  await nextFrame();

  try {
    await initModalEditors(contentEl);
    console.log('✅ initModalEditors done');
  } catch (e) {
    console.error('❌ initModalEditors failed', e);
  }
}

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

  const BS = window.bootstrap;
  if (!BS?.Modal) {
    console.error('❌ bootstrap.Modal not found on window.bootstrap');
    return;
  }

  contentEl.innerHTML = `
    <div class="modal-body py-5 text-center text-muted">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="mt-3">Loading…</div>
    </div>
  `;

  const modal = BS.Modal.getOrCreateInstance(modalEl);
  modal.show();

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load edit modal: ' + res.status);

    const html = await res.text();
    contentEl.innerHTML = html;

    if (modalEl.classList.contains('show')) {
      await runInitSafely(modalEl, contentEl);
      return;
    }

    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      await runInitSafely(modalEl, contentEl);
    };
    modalEl.addEventListener('shown.bs.modal', onShown);

  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});
