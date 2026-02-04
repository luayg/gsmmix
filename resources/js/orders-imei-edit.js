// resources/js/orders-imei-edit.js
import { initModalEditors } from './modal-editors';

function wait(ms) {
  return new Promise((r) => setTimeout(r, ms));
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

  // لازم Bootstrap يكون global من layout/admin
  const BS = window.bootstrap;
  if (!BS?.Modal) {
    console.error('Bootstrap Modal not found on window.bootstrap (check admin layout).');
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

    // ✅ انتظر حتى ينتهي ظهور المودال فعلاً
    const runInit = async () => {
      // 1) انتظر animation
      await wait(80);
      // 2) انتظر DOM يرسم
      await wait(0);
      // 3) شغّل المحررات على محتوى المودال فقط
      await initModalEditors(contentEl);
      console.log('✅ Summernote logic executed on modal content');
    };

    // إذا المودال حالياً ظاهر (غالباً نعم) شغّل مباشرة
    // وإلا اربطه على shown
    if (modalEl.classList.contains('show')) {
      await runInit();
    } else {
      const onShown = async () => {
        modalEl.removeEventListener('shown.bs.modal', onShown);
        await runInit();
      };
      modalEl.addEventListener('shown.bs.modal', onShown);
    }
  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});
