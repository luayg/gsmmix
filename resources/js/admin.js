import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Select2
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';
import 'select2/dist/js/select2.full.min.js';

// Summernote CSS (JS سنحمّله ديناميكيًا بعد تثبيت window.jQuery)
import 'summernote/dist/summernote-lite.css';

// DataTables (كما كان عندك)
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/dataTables.buttons';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-buttons/js/buttons.html5';

// =======================================================
// Toast helper
// =======================================================
window.showToast = window.showToast || function (variant = 'success', message = 'Done', opts = {}) {
  const bg = {
    success: 'bg-success text-white',
    danger: 'bg-danger text-white',
    warning: 'bg-warning',
    info: 'bg-primary text-white'
  }[variant] || 'bg-dark text-white';

  const id = 't' + Date.now() + Math.random().toString(16).slice(2);
  const html = `
    <div id="${id}" class="toast border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true"
         data-bs-delay="${opts.delay ?? 4000}">
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

// =======================================================
// Summernote loader (LOCAL)
// =======================================================
let __summernoteLoaded = false;

async function ensureSummernote() {
  if (__summernoteLoaded) return true;
  try {
    // مهم: تحميل JS بعد تثبيت window.jQuery
    await import('summernote/dist/summernote-lite.js');
    __summernoteLoaded = true;
    return true;
  } catch (e) {
    console.error('Summernote load failed', e);
    showToast('danger', 'Summernote failed to load (check npm build).', { title: 'Error', delay: 7000 });
    return false;
  }
}

function initSelect2(scopeEl, dropdownParentEl) {
  if (!$.fn || typeof $.fn.select2 !== 'function') return;
  const $root = $(scopeEl);
  $root.find('select.select2').each(function () {
    const $el = $(this);
    try {
      if ($el.data('select2')) $el.select2('destroy');
      $el.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: dropdownParentEl ? $(dropdownParentEl) : $root
      });
    } catch (e) {
      console.warn('Select2 init failed', e);
    }
  });
}

// ✅ تفعيل Summernote داخل نطاق معيّن (المودال)
async function initEditors(scopeEl) {
  const ok = await ensureSummernote();
  if (!ok) return;

  if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.summernote !== 'function') {
    console.warn('Summernote not available on jQuery');
    showToast('danger', 'Summernote not attached to jQuery.', { title: 'Error', delay: 7000 });
    return;
  }

  const root = scopeEl instanceof Element ? scopeEl : document;
  const textareas = root.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  window.jQuery(textareas).each(function () {
    const $t = window.jQuery(this);
    if ($t.next('.note-editor').length) return; // already initialized

    const h = Number($t.attr('data-summernote-height') || 360);

    $t.summernote({
      height: h,
      focus: false,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['insert', ['link', 'picture', 'table', 'hr']],
        ['view', ['codeview']]
      ],
      fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32', '36', '48'],
      callbacks: {
        // ✅ إدراج صورة مباشرة Base64 (بدون سيرفر)
        onImageUpload: function (files) {
          for (const f of files) {
            const reader = new FileReader();
            reader.onload = (e) => {
              $t.summernote('insertImage', e.target.result);
            };
            reader.readAsDataURL(f);
          }
        }
      }
    });
  });
}

// =======================================================
// Ajax modal loader
// =======================================================
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

    const inst = new bootstrap.Modal($modal[0]);
    inst.show();

    try {
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) throw new Error('Failed to load modal');
      const html = await res.text();

      if (myReq !== MODAL_REQ_ID) return;

      $content.html(html);

      // ✅ مهم: بعد حقن HTML فعّل select2 + summernote
      initSelect2($content[0], $modal[0]);
      await initEditors($content[0]);

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
            body: fd
          });

          if (res2.status === 422) {
            const j = await res2.json().catch(() => ({}));
            const msgs = Object.values(j.errors || {}).flat().join('<br>');
            showToast('danger', msgs || 'Validation error', { title: 'Error', delay: 7000 });
            return;
          }

          if (!res2.ok) {
            const txt = await res2.text();
            showToast('danger', txt || 'Failed to submit', { title: 'Error', delay: 7000 });
            return;
          }

          const j = await res2.json().catch(async () => ({ ok: true, msg: await res2.text() }));

          bootstrap.Modal.getInstance($modal[0])?.hide();
          $('.dataTable').each(function () {
            try { $(this).DataTable()?.ajax?.reload(null, false); } catch (_) {}
          });

          showToast('success', j.msg || 'Saved successfully');
        } catch (err) {
          console.error(err);
          showToast('danger', 'Network error', { title: 'Error', delay: 7000 });
        }
      });

    } catch (err) {
      console.error(err);
      if (myReq !== MODAL_REQ_ID) return;
      $content.html(`<div class="modal-body text-danger">Failed to load modal content.</div>`);
      showToast('danger', 'Failed to load modal content', { title: 'Error', delay: 7000 });
    }
  });
});

// cleanup backdrops (منع تكدس backdrop)
document.addEventListener('hidden.bs.modal', function () {
  document.body.classList.remove('modal-open');
  document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
});
