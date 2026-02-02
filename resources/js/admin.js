/* =================================================================
 * Imports (نسخة واحدة فقط)
 * ================================================================= */

import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';

// Buttons + ColVis + HTML5 export + Bootstrap 5 styling
import 'datatables.net-buttons/js/dataTables.buttons';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-buttons/js/buttons.html5';

import 'select2/dist/js/select2.full.min.js';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';

// ✅ CSS فقط (آمن في الأعلى)
import 'summernote/dist/summernote-lite.css';

/* =================================================================
 * Helpers
 * ================================================================= */

const hasSelect2 = () => !!$.fn && typeof $.fn.select2 === 'function';
const hasSummernote = () => !!$.fn && typeof $.fn.summernote === 'function';

// ✅ تحميل Summernote JS ديناميكياً (بعد ما صار window.jQuery جاهز)
let __summernoteLoaded = false;
async function ensureSummernoteLoaded() {
  if (__summernoteLoaded) return;

  // مهم: هذا الاستيراد الآن سيتم بعد ضبط window.jQuery
  await import('summernote/dist/summernote-lite.js');

  __summernoteLoaded = true;
}

function initSelect2Safe($root, $dropdownParent = null) {
  if (!hasSelect2()) return;
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

window.showToast = function (variant = 'success', message = 'Done', opts = {}) {
  const bg = {
    success: 'bg-success text-white',
    danger: 'bg-danger text-white',
    warning: 'bg-warning',
    info: 'bg-primary text-white'
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

/* ================================
 * Summernote upload + init
 * ================================ */

async function uploadSummernoteImage(file, token) {
  const fd = new FormData();
  fd.append('image', file);

  const res = await fetch('/admin/uploads/summernote-image', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': token,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    },
    body: fd
  });

  if (!res.ok) throw new Error(await res.text());
  const j = await res.json();
  if (!j?.url) throw new Error('Invalid upload response');
  return j.url;
}

async function initSummernoteSafe($root, token) {
  // ✅ حمّل Summernote JS الآن
  await ensureSummernoteLoaded();

  if (!hasSummernote()) {
    console.warn('Summernote loaded but plugin not attached to jQuery. Check window.jQuery binding.');
    return;
  }

  const $areas = $root.find('textarea[data-summernote="1"]');
  if (!$areas.length) return;

  // إعطاء فرصة للرسم داخل المودال قبل التحويل (يقلل مشاكل عدم ظهور toolbar)
  await new Promise((r) => requestAnimationFrame(() => r()));

  $areas.each(function () {
    const $t = $(this);

    try {
      if ($t.next('.note-editor').length) $t.summernote('destroy');
    } catch (_) {}

    const h = Number($t.attr('data-summernote-height') || 420);

    $t.summernote({
      height: h,
      toolbar: [
        ['style', ['style']],
        ['font', ['fontname', 'fontsize', 'bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture']],
        ['view', ['codeview']]
      ],
      fontNames: ['Arial', 'Lato', 'Tahoma', 'Times New Roman', 'Courier New', 'Verdana'],
      fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32'],
      callbacks: {
        onImageUpload: async function (files) {
          for (const f of files) {
            try {
              const url = await uploadSummernoteImage(f, token);
              $t.summernote('insertImage', url);
            } catch (e) {
              console.error(e);
              window.showToast?.('danger', 'Image upload failed', { title: 'Error', delay: 5000 });
            }
          }
        }
      }
    });
  });
}

/* =================================================================
 * Ajax Modal Loader (مرة واحدة للموقع)
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

    const inst = new window.bootstrap.Modal($modal[0]);
    inst.show();

    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('Failed to load modal');
      const html = await res.text();

      if (myReq !== MODAL_REQ_ID) return;

      $content.html(html);

      initSelect2Safe($content, $modal);

      // ✅ فعل Summernote بعد تحميل المودال (وبعد تحميله ديناميكياً)
      await initSummernoteSafe($content, token);

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
  if (document.getElementById('permsPage')) {
    await import('./pages/permissions.js');
    return;
  }
  if (document.querySelector('.service-create-form')) {
    import('./pages/service-custom-fields.js');
  }
});

/* =================================================================
 * سلوك السايدبار على الموبايل
 * ================================================================= */
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('.admin-sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const btn = document.getElementById('btnToggleSidebar');

  const openSidebar = () => {
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
 * زر إظهار/إخفاء كلمة المرور
 * ================================================================= */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.js-toggle-pass');
  if (!btn) return;
  const input = btn.closest('.input-group')?.querySelector('input[type="password"], input[type="text"]');
  if (!input) return;
  input.type = input.type === 'password' ? 'text' : password;
});

/* =================================================================
 * موضع التوست داخل content-wrapper
 * ================================================================= */
function positionToastStack() {
  const stack = document.getElementById('toastStack');
  const wrap = document.querySelector('.content-wrapper');
  if (!stack || !wrap) return;

  const gap = 16;
  const rect = wrap.getBoundingClientRect();
  const extra = Math.max(window.innerWidth - rect.right + gap, gap);

  stack.style.right = extra + 'px';
  stack.style.top = '16px';
}

document.addEventListener('DOMContentLoaded', positionToastStack);
window.addEventListener('resize', positionToastStack);

const btnRepos = document.getElementById('btnToggleSidebar');
if (btnRepos) {
  btnRepos.addEventListener('click', () => {
    setTimeout(positionToastStack, 250);
  });
}

window.addEventListener('show-toast-reposition', positionToastStack);
