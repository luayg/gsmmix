// resources/js/orders-imei-edit.js
import { initModalEditors } from './modal-editors';

// (اختياري) لو عندك select2 في admin.js ويشتغل، نفعّله داخل مودال الأوامر فقط
function initSelect2InModal(modalEl) {
  const $ = window.jQuery;
  if (!$ || !$.fn || typeof $.fn.select2 !== 'function') return;

  const $root = $(modalEl);
  $root.find('select.select2').each(function () {
    const $el = $(this);
    try {
      if ($el.data('select2')) $el.select2('destroy');
      $el.select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $root
      });
    } catch (e) {
      console.warn('Select2 init failed', e);
    }
  });
}

async function openOrderEdit(url) {
  const modalEl = document.getElementById('orderEditModal');
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

    // ✅ فعّل المحرر داخل هذا المودال فقط
    await initModalEditors(contentEl);

    // ✅ (اختياري) select2 داخل المودال فقط
    initSelect2InModal(modalEl);

    // ✅ Ajax submit للمودال فقط
    const form = contentEl.querySelector('form.js-ajax-form');
    if (form) {
      form.addEventListener('submit', async (ev) => {
        ev.preventDefault();

        // تأكد من نقل HTML من summernote إلى textarea قبل الإرسال
        const $ = window.jQuery;
        if ($ && $.fn && typeof $.fn.summernote === 'function') {
          const $ta = $(form).find('textarea[data-summernote="1"], textarea.js-editor');
          $ta.each(function () {
            // summernote يحدّث قيمة textarea تلقائياً، لكن نخليها صريحة
            try {
              const code = $(this).summernote('code');
              this.value = code;
            } catch (_) {}
          });
        }

        const fd = new FormData(form);
        const btn = form.querySelector('[type="submit"]');
        if (btn) btn.disabled = true;

        try {
          const res2 = await fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: fd
          });

          if (btn) btn.disabled = false;

          if (res2.status === 422) {
            const j = await res2.json().catch(() => ({}));
            const msgs = Object.values(j.errors || {}).flat().join('\n');
            alert(msgs || 'Validation error');
            return;
          }

          if (!res2.ok) {
            const t = await res2.text();
            alert('Save failed:\n' + t);
            return;
          }

          // نجاح
          modal.hide();

          // لو عندك datatable، حدّثها بدون ما تلمس صفحات أخرى
          if (window.jQuery && window.jQuery.fn && window.jQuery.fn.DataTable) {
            window.jQuery('.dataTable').each(function () {
              try {
                const dt = window.jQuery(this).DataTable();
                if (dt && dt.ajax) dt.ajax.reload(null, false);
              } catch (_) {}
            });
          }
        } catch (e) {
          if (btn) btn.disabled = false;
          console.error(e);
          alert('Network error');
        }
      }, { once: true });
    }
  } catch (e) {
    console.error(e);
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load edit modal.</div>`;
  }
}

// Delegation: edit button فقط
document.addEventListener('click', (e) => {
  const a = e.target.closest('.js-open-order-edit');
  if (!a) return;
  e.preventDefault();
  const url = a.dataset.url || a.getAttribute('href');
  if (!url || url === '#') return;
  openOrderEdit(url);
});
