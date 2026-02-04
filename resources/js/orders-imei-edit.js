// resources/js/orders-imei-edit.js
import $ from 'jquery';
import * as bootstrap from 'bootstrap';
import { initModalEditors } from './modal-editors';

if (!window.jQuery) window.jQuery = $;
if (!window.$) window.$ = $;
if (!window.bootstrap) window.bootstrap = bootstrap;

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

  // إظهار مؤشر التحميل
  contentEl.innerHTML = `
    <div class="modal-body py-5 text-center text-muted">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="mt-3">Loading…</div>
    </div>
  `;

  const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load edit modal: ' + res.status);

    const html = await res.text();
    contentEl.innerHTML = html;

    // ✅ التعديل: استدعاء المحرر بعد حقن المحرر مباشرة 
    // نستخدم تأخير 300ms لضمان أن المودال انتهى من التحريك (Fade animation)
    setTimeout(async () => {
      await initModalEditors(contentEl);
      console.log('✅ Summernote logic executed on modal content');
    }, 300);

  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});