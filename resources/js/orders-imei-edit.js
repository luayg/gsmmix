// resources/js/orders-imei-edit.js
import { initModalEditors } from './modal-editors';

function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function qs(sel, root = document) {
  return root.querySelector(sel);
}

function qsa(sel, root = document) {
  return Array.from(root.querySelectorAll(sel));
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

  // Use bootstrap if available, otherwise fallback
  const bs = window.bootstrap;
  let modalInstance = null;
  if (bs?.Modal) {
    modalInstance = bs.Modal.getOrCreateInstance(modalEl);
    modalInstance.show();
  } else {
    // minimal fallback
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
  }

  const res = await fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });

  if (!res.ok) {
    contentEl.innerHTML = `<div class="modal-body text-danger">Failed to load edit modal.</div>`;
    return;
  }

  const html = await res.text();
  contentEl.innerHTML = html;

  // init summernote inside this modal only
  await initModalEditors(contentEl);

  // bind ajax submit (only inside this modal)
  const form = contentEl.querySelector('form.js-ajax-form');
  if (form) {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();

      const btn = form.querySelector('[type="submit"]');
      if (btn) btn.disabled = true;

      try {
        const fd = new FormData(form);
        const method = (form.method || 'POST').toUpperCase();

        const res2 = await fetch(form.action, {
          method,
          headers: {
            'X-CSRF-TOKEN': getCsrf(),
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          body: fd
        });

        if (btn) btn.disabled = false;

        if (res2.status === 422) {
          const j = await res2.json().catch(() => ({}));
          alert(Object.values(j.errors || {}).flat().join("\n") || 'Validation error');
          return;
        }

        if (!res2.ok) {
          const t = await res2.text().catch(() => '');
          alert(t || 'Failed to save');
          return;
        }

        // close modal
        if (modalInstance) modalInstance.hide();

        // (optional) reload page to reflect status quickly
        // location.reload();
      } catch (e) {
        if (btn) btn.disabled = false;
        console.error(e);
        alert('Network error');
      }
    });
  }
}

document.addEventListener('click', (e) => {
  const a = e.target.closest('.js-open-order-edit');
  if (!a) return;

  e.preventDefault();
  const url = a.dataset.url || a.getAttribute('href');
  if (!url) return;

  openOrderEditModal(url);
});
