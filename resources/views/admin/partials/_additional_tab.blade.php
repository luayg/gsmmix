{{-- resources/views/admin/partials/_additional_tab.blade.php --}}

@php
  // row->meta comes from BaseServiceController::viewData() (params decoded)
  $meta = $row->meta ?? [];
  $savedCustomFields = $meta['custom_fields'] ?? [];
@endphp

<div class="row g-3">
  {{-- Left: Custom fields --}}
  <div class="col-lg-6">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0">Custom fields</h6>
      <button type="button" class="btn btn-link p-0" id="btnAddCustomField">Add field</button>
    </div>

    <input type="hidden" name="custom_fields_json" id="customFieldsHidden"
           value='@json($savedCustomFields)'>

    <div id="customFieldsWrap"></div>

    {{-- Template --}}
    <template id="customFieldTpl">
      <div class="card mb-2 custom-field-card" data-field>
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <div class="d-flex gap-2 align-items-center">
            <div class="form-check form-switch m-0">
              <input class="form-check-input" type="checkbox" data-active checked>
              <label class="form-check-label small">Active</label>
            </div>
          </div>

          <button type="button" class="btn btn-sm btn-danger" data-remove>&times;</button>
        </div>

        <div class="card-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label mb-1">Name</label>
              <input type="text" class="form-control form-control-sm" data-name placeholder="Name">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Field type</label>
              <select class="form-select form-select-sm" data-type>
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="password">Password</option>
                <option value="textarea">Textarea</option>
                <option value="select">Select</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Input name</label>
              <input type="text" class="form-control form-control-sm" data-input placeholder="machine_name">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Description</label>
              <input type="text" class="form-control form-control-sm" data-desc placeholder="Description">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <input type="number" class="form-control form-control-sm" data-min value="0">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <input type="number" class="form-control form-control-sm" data-max placeholder="Unlimited">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Validation</label>
              <select class="form-select form-select-sm" data-validation>
                <option value="">None</option>
                <option value="imei">IMEI</option>
                <option value="serial">Serial</option>
                <option value="email">Email</option>
                <option value="numeric">Numeric</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Required</label>
              <select class="form-select form-select-sm" data-required>
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
            </div>

            <div class="col-12" data-options-wrap style="display:none;">
              <label class="form-label mb-1">Options (one per line)</label>
              <textarea class="form-control form-control-sm" rows="3" data-options></textarea>
              <div class="form-text">Used only when Field type = Select</div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>

  {{-- Right: Group pricing --}}
  <div class="col-lg-6">
    <h6 class="mb-2">Groups</h6>

    {{-- هذا سيتم بناؤه بالـ JS (buildPricingTable في admin.js الذي أرسلته أنت) --}}
    <div id="groupsPricingWrap"></div>

    <input type="hidden" name="group_prices_json" id="pricingTableHidden" value="[]">

    <div class="form-text">
      Set special prices/discounts per user group for this service.
    </div>
  </div>
</div>

<script>
(function(){
  // ===== Custom fields dynamic UI =====
  const wrap   = document.getElementById('customFieldsWrap');
  const tpl    = document.getElementById('customFieldTpl');
  const hidden = document.getElementById('customFieldsHidden');

  if (!wrap || !tpl || !hidden) return;

  const slugify = (s) => String(s || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g,'_')
    .replace(/^_+|_+$/g,'');

  function readAll(){
    const out = [];
    wrap.querySelectorAll('[data-field]').forEach(card => {
      const type = card.querySelector('[data-type]')?.value || 'text';
      const optionsTxt = card.querySelector('[data-options]')?.value || '';
      const optionsArr = optionsTxt.split('\n').map(x => x.trim()).filter(Boolean);

      out.push({
        active: card.querySelector('[data-active]')?.checked ? 1 : 0,
        name: card.querySelector('[data-name]')?.value || '',
        type,
        input: card.querySelector('[data-input]')?.value || '',
        description: card.querySelector('[data-desc]')?.value || '',
        minimum: Number(card.querySelector('[data-min]')?.value || 0),
        maximum: (card.querySelector('[data-max]')?.value === '' ? null : Number(card.querySelector('[data-max]')?.value || 0)),
        validation: card.querySelector('[data-validation]')?.value || '',
        required: Number(card.querySelector('[data-required]')?.value || 0),
        options: (type === 'select' ? optionsArr : []),
      });
    });

    hidden.value = JSON.stringify(out);
  }

  function toggleOptions(card){
    const type = card.querySelector('[data-type]')?.value || 'text';
    const box  = card.querySelector('[data-options-wrap]');
    if (!box) return;
    box.style.display = (type === 'select') ? '' : 'none';
  }

  function bindCard(card){
    card.querySelector('[data-remove]')?.addEventListener('click', () => {
      card.remove();
      readAll();
    });

    card.querySelector('[data-name]')?.addEventListener('input', (e) => {
      const inp = card.querySelector('[data-input]');
      if (inp && !inp.value) inp.value = slugify(e.target.value);
      readAll();
    });

    card.querySelectorAll('input,select,textarea').forEach(el => {
      el.addEventListener('input', readAll);
      el.addEventListener('change', () => {
        toggleOptions(card);
        readAll();
      });
    });

    toggleOptions(card);
  }

  function addField(prefill = null){
    const node = tpl.content.cloneNode(true);
    const card = node.querySelector('[data-field]');

    if (prefill) {
      card.querySelector('[data-active]').checked = !!prefill.active;
      card.querySelector('[data-name]').value = prefill.name || '';
      card.querySelector('[data-type]').value = prefill.type || 'text';
      card.querySelector('[data-input]').value = prefill.input || '';
      card.querySelector('[data-desc]').value = prefill.description || '';
      card.querySelector('[data-min]').value = (prefill.minimum ?? 0);
      card.querySelector('[data-max]').value = (prefill.maximum ?? '');
      card.querySelector('[data-validation]').value = prefill.validation || '';
      card.querySelector('[data-required]').value = String(prefill.required ?? 0);

      if (Array.isArray(prefill.options)) {
        card.querySelector('[data-options]').value = prefill.options.join('\n');
      }
    }

    wrap.appendChild(node);
    bindCard(wrap.querySelectorAll('[data-field]')[wrap.querySelectorAll('[data-field]').length - 1]);
    readAll();
  }

  // Load saved
  try {
    const saved = JSON.parse(hidden.value || '[]');
    if (Array.isArray(saved) && saved.length) saved.forEach(x => addField(x));
    else addField();
  } catch(e) {
    addField();
  }

  document.getElementById('btnAddCustomField')?.addEventListener('click', () => addField());

  // Expose a hook so service-modal.js can force refresh hidden after cloning
  window.__syncCustomFieldsHidden__ = readAll;
})();
</script>
