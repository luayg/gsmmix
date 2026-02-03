import $ from 'jquery';

// Summernote (خاص بهذا الملف فقط)
import 'summernote/dist/summernote-lite.css';
import 'summernote/dist/summernote-lite.js';

(function () {
  // ===== Helpers =====
  const getCsrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  function initSummernote(scopeEl) {
    const root = scopeEl instanceof Element ? scopeEl : document;
    const areas = root.querySelectorAll('textarea[data-summernote="1"]');
    if (!areas.length) return;

    // مهم: لازم summernote تكون على نفس نسخة jquery في هذا الملف
    if (!$.fn || typeof $.fn.summernote !== 'function') {
      console.error('Summernote is not attached to this jQuery instance (orders-imei-edit.js).');
      return;
    }

    $(areas).each(function () {
      const $t = $(this);
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
        fontSizes: ['8','9','10','11','12','14','16','18','20','24','28','32','36','48'],
      });
    });
  }

  function showError(msg) {
    // بدون الاعتماد على showToast العام
    alert(msg);
    console.error(msg);
  }

  // ===== Open Order Edit Modal (isolated) =====
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-open-order-edit');
    if (!btn) return;

    e.preventDefault();

    const url = btn.dataset.url || btn.getAttribute('href') || '';
    if (!url) return;

    const modalEl = document.getElementById('orderEditModal');
    if (!modalEl) {
      showError('orderEditModal not found in page. Add the modal container to the orders index page.');
      return;
    }

    const contentEl = modalEl.querySelector('.modal-content');
    if (!contentEl) return;

    contentEl.innerHTML = `
      <div class="p-5 text-center text-muted">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div class="mt-3">Loading…</div>
      </div>
    `;

    // Bootstrap Modal (بدون لمس أي مودال ثاني)
    const bs = window.bootstrap;
    if (!bs?.Modal) {
      showError('Bootstrap Modal not found (window.bootstrap.Modal).');
      return;
    }

    const inst = bs.Modal.getOrCreateInstance(modalEl);
    inst.show();

    try {
      const res = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) {
        const t = await res.text().catch(() => '');
        throw new Error(`Failed to load edit modal (${res.status})\n${t}`);
      }

      const html = await res.text();
      contentEl.innerHTML = html;

      // ✅ فعّل summernote داخل هذا المودال فقط
      initSummernote(contentEl);

      // ✅ Ajax submit داخل المودال فقط
      const form = contentEl.querySelector('form.js-order-edit-form');
      if (form) {
        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();

          const fd = new FormData(form);
          const method = (form.getAttribute('method') || 'POST').toUpperCase();

          try {
            const res2 = await fetch(form.action, {
              method,
              headers: {
                'X-CSRF-TOKEN': getCsrf(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: fd
            });

            if (res2.status === 422) {
              const j = await res2.json().catch(() => ({}));
              const msgs = Object.values(j.errors || {}).flat().join('\n');
              showError(msgs || 'Validation error');
              return;
            }

            if (!res2.ok) {
              const t = await res2.text().catch(() => '');
              showError(t || 'Failed to save');
              return;
            }

            inst.hide();

            // لو عندك datatable في الصفحة، أعد تحميلها بدون لمس صفحات أخرى
            if (window.$ && window.$.fn?.DataTable) {
              try {
                window.$('.dataTable').each(function () {
                  const dt = window.$(this).DataTable();
                  if (dt?.ajax) dt.ajax.reload(null, false);
                });
              } catch (_) {}
            }
          } catch (err) {
            console.error(err);
            showError('Network error while saving');
          }
        });
      }
    } catch (err) {
      console.error(err);
      showError('Failed to load modal content (order edit). Check Laravel error log.');
      inst.hide();
    }
  });

  // تنظيف backdrop فقط لمودال الأوردر (بدون تعميم)
  document.addEventListener('hidden.bs.modal', function (e) {
    if (e.target?.id !== 'orderEditModal') return;
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
  });
})();
