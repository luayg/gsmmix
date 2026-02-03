import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// Select2
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';
import 'select2/dist/js/select2.full.min.js';

// ✅ Summernote (CSS + JS محلي من npm)
import 'summernote/dist/summernote-lite.css';
import 'summernote/dist/summernote-lite.js';

// DataTables
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/dataTables.buttons';
import 'datatables.net-buttons/js/buttons.colVis';
import 'datatables.net-buttons/js/buttons.html5';

// Toast helper
window.showToast = window.showToast || function (variant = 'success', message = 'Done', opts = {}) {
  const bg = {
    success: 'bg-success text-white',
    danger : 'bg-danger text-white',
    warning: 'bg-warning',
    info   : 'bg-primary text-white'
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

// Select2 init
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

const loadCssOnce = (id, href) => {
  if (document.getElementById(id)) return;
  const l = document.createElement('link');
  l.id = id;
  l.rel = 'stylesheet';
  l.href = href;
  document.head.appendChild(l);
};

const loadScriptOnce = (id, src) => new Promise((resolve, reject) => {
  if (document.getElementById(id)) return resolve();
  const s = document.createElement('script');
  s.id = id;
  s.src = src;
  s.async = false;
  s.onload = resolve;
  s.onerror = reject;
  document.body.appendChild(s);
});

// ✅ نفس فكرة service-modal: ضمن وجود summernote حتى داخل ajax modal
async function ensureSummernoteCDN() {
  loadCssOnce('sn-css', 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');

  // إذا summernote موجود خلاص
  if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote) return true;

  // تأكد jQuery
  if (!window.jQuery) {
    await loadScriptOnce('jq-cdn', 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
    window.$ = window.jQuery;
  }

  // حمّل summernote
  await loadScriptOnce('sn-cdn', 'https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');

  return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote);
}





// ✅ Summernote init
async function initEditors(scopeEl) {
  // ✅ ضمن وجود summernote (حتى لو build ما حمله)
  const ok = await ensureSummernoteCDN();
  if (!ok) {
    console.error('Summernote failed to load');
    return;
  }

  const root = scopeEl instanceof Element ? scopeEl : document;
  const textareas = root.querySelectorAll('textarea[data-summernote="1"]');
  if (!textareas.length) return;

  window.jQuery(textareas).each(function () {
    const $t = window.jQuery(this);

    // لو متفعّل مسبقًا لا تعيده
    if ($t.next('.note-editor').length) return;

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
      callbacks: {
        onImageUpload: function (files) {
          for (const f of files) {
            const reader = new FileReader();
            reader.onload = (e) => $t.summernote('insertImage', e.target.result);
            reader.readAsDataURL(f);
          }
        }
      }
    });
  });
}


// Ajax modal loader + ajax submit
document.addEventListener('DOMContentLoaded', () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content || window.CSRF_TOKEN || '';
  let MODAL_REQ_ID = 0;

  $(document).on('click', '.js-open-modal', async function (e) {
    e.preventDefault();

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

      initSelect2($content[0], $modal[0]);
      await initEditors($content[0]);

      $content.find('form.js-ajax-form').off('submit').on('submit', async function (ev) {
        ev.preventDefault();

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

document.addEventListener('hidden.bs.modal', function () {
  document.body.classList.remove('modal-open');
  document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
});
