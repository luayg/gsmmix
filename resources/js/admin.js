/* =================================================================
 * Imports (نسخة واحدة فقط)
 * ================================================================= */

import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Summernote (lite) global
import 'summernote/dist/summernote-lite.min.js';
import 'summernote/dist/summernote-lite.min.css';

import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';

// Buttons + ColVis + HTML5 export + Bootstrap 5 styling
import 'datatables.net-buttons/js/dataTables.buttons';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-buttons/js/buttons.html5';

// Select2 (CSS + Theme)
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';

// جرّب الاستيراد المعتاد أولاً (side-effect)
import 'select2/dist/js/select2.full.min.js';

// ✅ Fallback: لو ما تركّب select2 على $.fn لأي سبب، ركّبه يدويًا
(async () => {
  try {
    if (!$.fn || typeof $.fn.select2 !== 'function') {
      const mod = await import('select2');
      const attach = mod?.default || mod;
      // بعض نسخ select2 تُصدّر دالة factory تحتاج تمرير jQuery
      if (typeof attach === 'function') {
        attach(window.jQuery);
      }
    }
  } catch (e) {
    console.warn('Select2 fallback attach failed:', e);
  }
})();

import { initModalEditors, destroySummernoteIn } from './modal-editors';
window.initModalEditors = initModalEditors;
window.destroySummernoteIn = destroySummernoteIn;


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

/** Toast helper (يظهر أعلى يمين الشاشة) */
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
 * Ajax Modal Loader (مرة واحدة للموقع) - FIX double-binding
 * ================================================================= */
document.addEventListener('DOMContentLoaded', () => {
  // امنع إعادة الربط لو سبق الربط
  if (window.__bindOpenModalOnce__) return;
  window.__bindOpenModalOnce__ = true;

  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let MODAL_REQ_ID = 0; // يزيد مع كل نقره

  $(document).on('click', '.js-open-modal', async function (e) {
    // امنع أي مستمعين/تصرّفات أخرى
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const url = $(this).data('url') || $(this).attr('href');
    if (!url || url === '#') return;

    // عرّف رقم الطلب الحالي
    const myReq = ++MODAL_REQ_ID;

    const $modal   = $('#ajaxModal');
    const $content = $modal.find('.modal-content');

    // شاشة انتظار
    $content.html(`
      <div class="modal-body py-5 text-center text-muted">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div class="mt-3">Loading…</div>
      </div>
    `);

    const inst = new window.bootstrap.Modal($modal[0]);
    inst.show();

    try {
      const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('Failed to load modal');
      const html = await res.text();

      // لو جاء ردّ قديم، تجاهله
      if (myReq !== MODAL_REQ_ID) return;

      $content.html(html);

      // init select2 داخل المودال لو موجود
      if (typeof initSelect2Safe === 'function') initSelect2Safe($content, $modal);

      // إرسال Ajax للنماذج داخل المودال
      $content.find('form.js-ajax-form').off('submit').on('submit', async function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation();

        const form   = ev.currentTarget;
        const fd     = new FormData(form);
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
            showToast?.('danger', msgs || 'Validation error', { title: 'Error', delay: 5000 });
            return;
          }
          if (!res2.ok) {
            const txt = await res2.text();
            showToast?.('danger', txt || 'Failed to submit the form.', { title: 'Error', delay: 5000 });
            return;
          }

          const j = await res2.json().catch(async () => ({ ok: true, msg: await res2.text() }));

          window.bootstrap.Modal.getInstance($modal[0])?.hide();
          $('.dataTable').each(function () {
            try { $(this).DataTable()?.ajax?.reload(null, false); } catch (_) {}
          });

          showToast?.('success', j.msg || 'Saved successfully');
        } catch (err) {
          console.error(err);
          showToast?.('danger', 'Network error', { title: 'Error' });
        }
      });

    } catch (err) {
      console.error(err);
      // لو ردّ قديم تجاهله
      if (myReq !== MODAL_REQ_ID) return;
      $content.html(`<div class="modal-body text-danger">Failed to load modal content.</div>`);
      showToast?.('danger', 'Failed to load modal content', { title: 'Error' });
    }
  });
});


/* =================================================================
 * Lazy-load لسكربتات الصفحات
 * ================================================================= */
document.addEventListener('DOMContentLoaded', async () => {
  // Users
  if (document.getElementById('usersPage')) {
    await import('./pages/users-index.js');
    return;
  }
  if (document.getElementById('groupsPage')) {
    await import('./pages/groups-index.js');
    return;
  }
  if (document.getElementById('rolesPage')) {
    await import('./pages/roles.js');
    return;
  }
  if (document.getElementById('permsPage')) { // permissions
    await import('./pages/permissions.js');
    return;
  }
  // Service create / edit
  if (document.querySelector('.service-create-form')) {
    import('./pages/service-custom-fields.js');
  }
});


/* =================================================================
 * سلوك السايدبار على الموبايل (زر الفتح/الإغلاق + الخلفية)
 * ================================================================= */
document.addEventListener('DOMContentLoaded', () => {
  const sidebar  = document.querySelector('.admin-sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const btn      = document.getElementById('btnToggleSidebar');

  const openSidebar  = () => {
    if (!sidebar) return;
    sidebar.classList.add('is-open');
    document.body.classList.add('sidebar-open');
    backdrop?.classList.add('show');
  };
  const closeSidebar = () => {
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    document.body.classList.remove('sidebar-open');
    backdrop?.classList.remove('show');
  };

  btn?.addEventListener('click', () => {
    sidebar?.classList.contains('is-open') ? closeSidebar() : openSidebar();
  });
  backdrop?.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closeSidebar(); });
});


/* =================================================================
 * زر إظهار/إخفاء كلمة المرور داخل input-group
 * ================================================================= */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-toggle-pass');
  if (!btn) return;
  const input = btn.closest('.input-group')?.querySelector('input[type="password"], input[type="text"]');
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : 'password';
});


/* =================================================================
 * موضع التوست داخل content-wrapper
 * ================================================================= */
function positionToastStack() {
  const stack = document.getElementById('toastStack');
  const wrap  = document.querySelector('.content-wrapper');
  if (!stack || !wrap) return;

  const gap = 16; // px
  const rect  = wrap.getBoundingClientRect();
  const extra = Math.max(window.innerWidth - rect.right + gap, gap);

  stack.style.right = extra + 'px';
  stack.style.top   = '16px';
}

document.addEventListener('DOMContentLoaded', positionToastStack);
window.addEventListener('resize', positionToastStack);

// لو عندك زر إظهار/إخفاء السايدبار، أعد التموضع بعد الأنيميشن
const btnRepos = document.getElementById('btnToggleSidebar');
if (btnRepos) {
  btnRepos.addEventListener('click', () => {
    setTimeout(positionToastStack, 250); // زمن انتقال الـsidebar في CSS
  });
}

// في حال تشغيل التوستر من الدالة العامة، أعد التموضع قبل إظهاره
window.addEventListener('show-toast-reposition', positionToastStack);