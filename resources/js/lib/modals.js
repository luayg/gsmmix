// Bootstrap 5 Modal helper (بدون jQuery)
export function showModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const modal = bootstrap.Modal.getOrCreateInstance(el);
  modal.show();
}
export function hideModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const modal = bootstrap.Modal.getOrCreateInstance(el);
  modal.hide();
}

// تعبئة مودال العرض بجدول مفاتيح/قيم
export function fillViewModal({ id = 'modalView', title, rows = [] }) {
  if (title) {
    document.querySelector(`#${id} .modal-title`).textContent = title;
  }
  const body = document.getElementById(`${id}-body`);
  if (!body) return;

  if (!rows.length) {
    body.innerHTML = `<div class="text-muted">No data</div>`;
  } else {
    const html = `
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <tbody>
            ${rows.map(r => `
              <tr>
                <th style="width:30%">${r.label ?? ''}</th>
                <td>${r.value ?? ''}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
    body.innerHTML = html;
  }
  showModal(id);
}

// ضبط مودال التعديل/الإنشاء
export function setupEditModal({ id='modalEdit', title, action, method='POST', submitText, values={} }) {
  const form = document.getElementById(`${id}-form`);
  if (!form) return;

  if (title) form.closest('.modal-content').querySelector('.modal-title').textContent = title;
  if (action) form.setAttribute('action', action);

  // حقن @method عند الحاجة
  const methodInput = form.querySelector('input[name="_method"]');
  if (methodInput) methodInput.value = method.toUpperCase() === 'PUT' ? 'PUT' : 'POST';

  if (submitText) form.querySelector('button[type="submit"]').textContent = submitText;

  // تعبئة الحقول بالـname
  Object.entries(values).forEach(([name, val]) => {
    const el = form.querySelector(`[name="${name}"]`);
    if (!el) return;
    if (el.tagName === 'SELECT') {
      el.value = val;
    } else if (el.type === 'checkbox') {
      el.checked = !!val;
    } else {
      el.value = val ?? '';
    }
  });

  // تنظيف أخطاء
  const errBox = document.getElementById(`${id}-errors`);
  if (errBox) { errBox.style.display = 'none'; errBox.textContent = ''; }

  showModal(id);
}

// إظهار مودال الحذف وتعيين action والرسالة
export function setupDeleteModal({ id='modalDelete', action, message }) {
  const form = document.getElementById(`${id}-form`);
  const msg = document.getElementById(`${id}-msg`);
  if (form && action) form.setAttribute('action', action);
  if (msg && message) msg.textContent = message;
  showModal(id);
}
