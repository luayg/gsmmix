document.addEventListener('DOMContentLoaded', () => {

  const wrap = document.getElementById('customFieldsWrap');
  const btn  = document.getElementById('btnAddCustomField');
  const tpl  = document.getElementById('customFieldTpl');
  const out  = document.getElementById('customFieldsInput');

  if (!wrap || !btn || !tpl || !out) return;

  function sync() {
    const data = [];
    wrap.querySelectorAll('.custom-field-item').forEach(el => {
      data.push({
        active: el.querySelector('.cf-active').checked ? 1 : 0,
        name: el.querySelector('.cf-name').value,
        key: el.querySelector('.cf-key').value,
        type: el.querySelector('.cf-type').value,
        description: el.querySelector('.cf-desc').value,
        min: el.querySelector('.cf-min').value,
        max: el.querySelector('.cf-max').value,
        validation: el.querySelector('.cf-validation').value,
        required: el.querySelector('.cf-required').value,
      });
    });
    out.value = JSON.stringify(data);
  }

  btn.addEventListener('click', () => {
    const node = tpl.content.cloneNode(true);
    wrap.appendChild(node);
    sync();
  });

  wrap.addEventListener('click', e => {
    if (e.target.classList.contains('btn-remove-field')) {
      e.target.closest('.custom-field-item').remove();
      sync();
    }
  });

  wrap.addEventListener('input', sync);
  wrap.addEventListener('change', sync);
});
window.initModalEditors = initModalEditors;
