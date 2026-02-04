// C:\xampp\htdocs\gsmmix\resources\js\orders-imei-edit.js
import { initModalEditors } from './modal-editors';

function nextFrame() {
  return new Promise((r) => requestAnimationFrame(() => r()));
}

async function runInitSafely(modalEl, contentEl) {
  // انتظر كم فريم لضمان DOM + animation
  await nextFrame();
  await nextFrame();

  try {
    await initModalEditors(contentEl);
    console.log('✅ initModalEditors done');
  } catch (e) {
    console.error('❌ initModalEditors failed', e);
  }
}

function cleanupQuillIn(modalEl) {
  // ✅ تنظيف بسيط حتى ما تتكرر instances وتسبب أعطال
  const textareas = modalEl.querySelectorAll('textarea[data-editor="quill"]');
  textareas.forEach((ta) => {
    try {
      if (ta.__quillWrap && ta.__quillWrap.isConnected) ta.__quillWrap.remove();
    } catch (_) {}
    try {
      // رجّع textarea طبيعي
      ta.style.display = '';
      ta.dataset.quillReady = '0';
      ta.removeAttribute('data-quill-ready');
    } catch (_) {}
    try {
      ta.__quillInstance = null;
      ta.__quillWrap = null;
    } catch (_) {}
  });
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

  // bootstrap من layout/admin.js
  const BS = window.bootstrap;
  if (!BS?.Modal) {
    console.error('❌ bootstrap.Modal not found on window.bootstrap');
    return;
  }

  // ✅ hook cleanup مرة واحدة
  if (!modalEl.__quillCleanupHooked) {
    modalEl.__quillCleanupHooked = true;
    modalEl.addEventListener('hidden.bs.modal', () => {
      cleanupQuillIn(modalEl);
    });
  }

  contentEl.innerHTML = `
    <div class="modal-body py-5 text-center text-muted">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="mt-3">Loading…</div>
    </div>
  `;

  const modal = BS.Modal.getOrCreateInstance(modalEl);

  // ✅ اربط shown قبل show حتى لا يفوتك
  let shownResolve;
  const shownPromise = new Promise((r) => (shownResolve = r));
  const onShownOnce = async () => {
    modalEl.removeEventListener('shown.bs.modal', onShownOnce);
    shownResolve(true);
  };
  modalEl.addEventListener('shown.bs.modal', onShownOnce);

  modal.show();

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load edit modal: ' + res.status);

    const html = await res.text();
    contentEl.innerHTML = html;

    // انتظر shown لو لم يكن ظاهر فعليًا بعد
    if (!modalEl.classList.contains('show')) {
      await shownPromise;
    } else {
      await nextFrame();
      await nextFrame();
    }

    await runInitSafely(modalEl, contentEl);
  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});
