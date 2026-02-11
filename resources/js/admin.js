/* =================================================================
 * resources/js/admin.js
 * ================================================================= */

import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// DataTables
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/dataTables.buttons';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-buttons/js/buttons.html5';

// Select2
import 'select2/dist/js/select2.full.min.js';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';

// Editors (Summernote/Quill init)
import { initModalEditors } from './modal-editors';
window.initModalEditors = initModalEditors;

/* =================================================================
 * Helpers
 * ================================================================= */

/** هل Select2 محمَّل على jQuery الحالي؟ */
const hasSelect2 = () => !!$.fn && typeof $.fn.select2 === 'function';

/** تهيئة Select2 بأمان داخل جذر معيّن (مثل المودال) */
function initSelect2Safe($root, $dropdownParent = null) {
  if (!hasSelect2()) {
    console.warn('Select2 is not loaded on current jQuery instance.');
    return;
  }
  $root.find('select.select2, select.js-roles, select.js-groups').each(function () {
    const $el = $(this);
    try {
      if ($el.data('select2')) $el.select2('destroy');
      $el.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $dropdownParent || $root
      });
    } catch (e) {
      console.warn('Select2 init failed for', this, e);
    }
  });
}

/** Toast helper */
window.showToast = function (variant = 'success', message = 'Done', opts = {}) {
  const bg = {
    success: 'bg-success text-white',
    danger : 'bg-danger text-white',
    warning: 'bg-warning',
    info   : 'bg-primary text-white'
  }[variant] || 'bg-dark text-white';

  const id = 't' + Date.now() + Math.random().toString(16).slice(2);
  const html = `
    <div id="${id}" class="toast border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-delay="${opts.delay ?? 3000}">
      <div class="toast-header ${bg}">
        <strong class="me-auto">${opts.title ?? 'Notification'}</strong>
        <small class="text-white-50">now</small>
        <button type="button" class="btn-close ${bg.includes('text-white') ? 'btn-close-white' : ''}"
                data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">${message}</div>
    </div>`;
  const stack = document.getElementById('toastStack') || document.body;
  stack.insertAdjacentHTML('beforeend', html);

  const el = document.getElementById(id);
  const t = new bootstrap.Toast(el);
  t.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
};

/* =================================================================
 * Ajax Modal Loader (مرة واحدة للموقع) - منع double-binding
 * ================================================================= */
document.addEventListener('DOMContentLoaded', () => {
  if (window.__bindOpenModalOnce__) return;
  window.__bindOpenModalOnce__ = true;

  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let MODAL_REQ_ID = 0;

  $(document).on('click', '.js-open-modal', async function (e) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const url = $(this).data('url') || $(this).attr('href');
    if (!url || url === '#') return;

    const myReq = ++MODAL_REQ_ID;

    const $modal = $('#ajaxModal');
    const $content = $modal.find('.modal-content');

    $content.html(`
      <div class="modal-body py-5 text-center text-muted">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div class="mt-3">Loading…</div>
      </div>
    `);

    const inst = bootstrap.Modal.getOrCreateInstance($modal[0]);
    inst.show();

    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('Failed to load modal');
      const html = await res.text();

      if (myReq !== MODAL_REQ_ID) return;

      $content.html(html);

      // select2 داخل المودال
      initSelect2Safe($content, $modal);

      // editors داخل المودال (لو موجودة)
      try {
        await window.initModalEditors?.($content[0]);
      } catch (_) {}

      // Ajax submit
      $content.find('form.js-ajax-form').off('submit').on('submit', async function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation();

        const form = ev.currentTarget;
        const fd = new FormData(form);
        const method = (form.method || 'POST').toUpperCase();

        try {
          const res2 = await fetch(form.action, {
            method,
            headers: {
              'X-CSRF-TOKEN': token,
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest'
            },
            body: fd,
            redirect: 'follow'
          });

          if (res2.status === 422) {
            const j = await res2.json().catch(() => ({}));
            const msgs = Object.values(j.errors || {}).flat().join('<br>');
            window.showToast?.('danger', msgs || 'Validation error', { title: 'Error', delay: 5000 });
            return;
          }
          if (!res2.ok) {
            const txt = await res2.text();
            window.showToast?.('danger', txt || 'Failed to submit the form.', { title: 'Error', delay: 5000 });
            return;
          }

          const j = await res2.json().catch(async () => ({ ok: true, msg: await res2.text() }));

          bootstrap.Modal.getInstance($modal[0])?.hide();

          $('.dataTable').each(function () {
            try { $(this).DataTable()?.ajax?.reload(null, false); } catch (_) {}
          });

          window.showToast?.('success', j.msg || 'Saved successfully');
        } catch (err) {
          console.error(err);
          window.showToast?.('danger', 'Network error', { title: 'Error' });
        }
      });

    } catch (err) {
      console.error(err);
      if (myReq !== MODAL_REQ_ID) return;
      $content.html(`<div class="modal-body text-danger">Failed to load modal content.</div>`);
      window.showToast?.('danger', 'Failed to load modal content', { title: 'Error' });
    }
  });

  // تنظيف الباك دروب
  document.addEventListener('hidden.bs.modal', function () {
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  });
});

/* =================================================================
 * Lazy-load لسكربتات الصفحات
 * ================================================================= */
document.addEventListener('DOMContentLoaded', async () => {
  if (document.getElementById('usersPage')) {
    await import('./pages/users-index.js'); return;
  }
  if (document.getElementById('groupsPage')) {
    await import('./pages/groups-index.js'); return;
  }
  if (document.getElementById('rolesPage')) {
    await import('./pages/roles.js'); return;
  }
  if (document.getElementById('permsPage')) {
    await import('./pages/permissions.js'); return;
  }

  if (document.querySelector('.service-create-form')) {
    import('./pages/service-custom-fields.js');
  }
});