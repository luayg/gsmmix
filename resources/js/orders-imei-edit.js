import $ from 'jquery';
window.$ = window.jQuery = $;

// Bootstrap (إذا صفحتك أصلاً تحمل bootstrap من bundle.css فقط، هذا يكفي)
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import { initModalEditors, destroyModalEditors } from './modal-editors';

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-open-order-edit');
  if (!btn) return;

  e.preventDefault();

  const url = btn.getAttribute('data-url') || btn.getAttribute('href');
  if (!url || url === '#') return;

  const modalEl = document.getElementById('orderEditModal');
  const contentEl = modalEl.querySelector('.modal-content');

  // loading UI
  contentEl.innerHTML = `
    <div class="modal-body py-5 text-center text-muted">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div class="mt-3">Loading…</div>
    </div>
  `;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();

  try {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error('Failed to load edit modal');

    const html = await res.text();
    contentEl.innerHTML = html;

    // ✅ هنا نفعّل Summernote داخل هذا المودال فقط
    await initModalEditors(contentEl);

  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
});

// تنظيف عند إغلاق المودال
document.addEventListener('hidden.bs.modal', (e) => {
  if (e.target?.id !== 'orderEditModal') return;
  destroyModalEditors(e.target);
  // إزالة backdrop الزائد (احتياط)
  document.body.classList.remove('modal-open');
  document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
});
