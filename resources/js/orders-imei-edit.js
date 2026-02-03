// resources/js/orders-imei-edit.js
import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = window.bootstrap || bootstrap;

import { initModalEditors } from './modal-editors';

function initSelect2Inside(scopeEl, dropdownParentEl) {
  if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') return;

  const $root = window.jQuery(scopeEl);
  $root.find('select.select2').each(function () {
    const $el = window.jQuery(this);
    try {
      if ($el.data('select2')) $el.select2('destroy');
      $el.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: dropdownParentEl ? window.jQuery(dropdownParentEl) : $root
      });
    } catch (e) {
      console.warn('Select2 init failed in Order Edit modal', e);
    }
  });
}

async function openOrderEditModal(url) {
  const modalEl = document.getElementById('orderEditModal');
  if (!modalEl) return;

  const contentEl = modalEl.querySelector('.modal-content');
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

    // ✅ Summernote (المهم)
    await initModalEditors(contentEl);

    // ✅ Select2 (اختياري — لو موجود في المشروع)
    initSelect2Inside(contentEl, modalEl);

    // ✅ Ajax submit داخل المودال فقط
    const form = contentEl.querySelector('form.js-ajax-form');
    if (form) {
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();

        const token =
          document.querySelector('meta[name="csrf-token"]')?.content ||
          window.CSRF_TOKEN ||
          '';

        const fd = new FormData(form);
        const method = (form.method || 'POST').toUpperCase();

        // أثناء الإرسال: عطّل زر الحفظ
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        try {
          const res2 = await fetch(form.action, {
            method,
            headers: {
              'X-CSRF-TOKEN': token,
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            body: fd
          });

          if (btn) btn.disabled = false;

          if (res2.status === 422) {
            const j = await res2.json().catch(() => ({}));
            const msg = Object.values(j.errors || {}).flat().join('\n') || 'Validation error';
            alert(msg);
            return;
          }

          if (!res2.ok) {
            const t = await res2.text();
            alert('Save failed:\n\n' + t);
            return;
          }

          // نجاح
          modal.hide();

          // لو عندك Toast عالمي استخدمه
          if (window.showToast) window.showToast('success', 'Saved successfully');

          // تحديث الصفحة أو الجدول (أبسط شيء reload)
          window.location.reload();
        } catch (e) {
          if (btn) btn.disabled = false;
          console.error(e);
          alert('Network error');
        }
      });
    }
  } catch (err) {
    console.error(err);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load modal content.</div>`;
  }
}

// Click handler معزول: فقط .js-open-order-edit
document.addEventListener('click', (e) => {
  const a = e.target.closest('.js-open-order-edit');
  if (!a) return;

  e.preventDefault();
  const url = a.dataset.url || a.getAttribute('href');
  if (!url || url === '#') return;

  openOrderEditModal(url);
});

// تنظيف بقايا backdrop لهذا المودال فقط
document.addEventListener('hidden.bs.modal', function (ev) {
  if (ev.target && ev.target.id === 'orderEditModal') {
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach((b) => b.remove());
  }
});
