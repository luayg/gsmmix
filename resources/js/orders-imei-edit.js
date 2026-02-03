import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
import { initModalEditors } from './modal-editors';

document.addEventListener('DOMContentLoaded', () => {
  const token =
    document.querySelector('meta[name="csrf-token"]')?.content ||
    window.CSRF_TOKEN ||
    '';

  let REQ_ID = 0;

  // فتح Edit فقط عبر زر خاص
  $(document).on('click', '.js-open-order-edit', async function (e) {
    e.preventDefault();

    const url = $(this).data('url') || $(this).attr('href');
    if (!url || url === '#') return;

    const myReq = ++REQ_ID;

    const $modal = $('#orderEditModal');
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
      if (!res.ok) throw new Error('Failed to load edit modal');
      const html = await res.text();
      if (myReq !== REQ_ID) return;

      $content.html(html);

      // ✅ تفعيل Summernote داخل مودال edit فقط
      await initModalEditors($content[0]);

      // Submit Ajax داخل edit modal (لو الفورم يحمل js-ajax-form)
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
            const msgs = Object.values(j.errors || {}).flat().join('\n');
            alert(msgs || 'Validation error');
            return;
          }

          if (!res2.ok) {
            const txt = await res2.text();
            alert(txt || 'Failed to submit');
            return;
          }

          inst.hide();
          // لو عندك reload للجدول (اختياري)
          // location.reload();

        } catch (err) {
          console.error(err);
          alert('Network error');
        }
      });

    } catch (err) {
      console.error(err);
      if (myReq !== REQ_ID) return;
      $content.html(`<div class="modal-body text-danger">Failed to load modal content.</div>`);
    }
  });
});
