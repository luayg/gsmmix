{{-- resources/views/admin/services/server/_modal_create.blade.php --}}

<form id="serviceCreateForm"
      class="service-create-form js-ajax-form"
      action="{{ route('admin.services.server.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  {{-- ✅ Required by backend validation --}}
  <input type="hidden" name="name_en" id="nameEnHidden" value="">
  <input type="hidden" name="main_type" id="mainTypeHidden" value="">

  {{-- ✅ Custom fields JSON (هذا هو الذي سيحمل standfield/custom fields) --}}
  <input type="hidden" name="custom_fields_json" id="customFieldsJson" value="[]">

  {{-- Injected by service-modal --}}
  <input type="hidden" name="supplier_id" value="">
  <input type="hidden" name="remote_id" value="">
  <input type="hidden" name="group_name" value="">

  <div class="service-tabs-content">

    {{-- ===================== ✅ GENERAL TAB ===================== --}}
    <div class="tab-pane active" data-tab="general">
      <div class="row g-3">

        <div class="col-xl-7">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label mb-1">Name</label>
              <input name="name" id="nameInput" type="text" class="form-control" required>
              <small class="text-muted">سيتم تعبئة name_en تلقائياً بنفس الاسم.</small>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Alias</label>
              <input name="alias" type="text" class="form-control" placeholder="unique-alias-like-this">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Delivery time</label>
              <input name="time" type="text" class="form-control" placeholder="e.g. 1-24h">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Group</label>
              <select name="group_id" class="form-select">
                <option value="">Group</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Main field type</label>
              <select name="main_field_type" class="form-select" id="mainFieldType">
                <option value="serial" selected>Serial</option>
                <option value="imei">IMEI</option>
                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="text">Text</option>
                <option value="custom">Custom</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Type</label>
              <select name="type" class="form-select">
                <option value="server" selected>Server</option>
                <option value="imei">IMEI</option>
                <option value="file">File</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Main field label</label>
              <input name="main_field_label" id="mainFieldLabel" type="text" class="form-control" value="Serial">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Allowed characters</label>
              <select name="allowed_chars" class="form-select">
                <option value="1" selected>Any</option>
                <option value="2">Numbers only</option>
                <option value="3">Letters only</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <div class="input-group">
                <input name="minimum" type="number" class="form-control" value="1">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <div class="input-group">
                <input name="maximum" type="number" class="form-control" value="50">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input id="pricePreview" type="text" class="form-control" readonly value="0.0000">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Cost</label>
              <div class="input-group">
                <input name="cost" type="number" step="0.0001" class="form-control" value="0.0000">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label mb-1">Profit</label>
              <div class="input-group">
                <input name="profit" type="number" step="0.0001" class="form-control" value="0.0000">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-2">
              <label class="form-label mb-1">&nbsp;</label>
              <select name="profit_type" class="form-select">
                <option value="1" selected>Credits</option>
                <option value="2">Percent</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Source</label>
              <select name="source" class="form-select">
                <option value="1">Manual</option>
                <option value="2" selected>API</option>
                <option value="3">Supplier</option>
                <option value="4">Local source</option>
              </select>
            </div>

          </div>
        </div>

        <div class="col-xl-5">
          <label class="form-label mb-1">Info</label>
          <textarea id="infoEditor" class="form-control d-none"></textarea>
          <input type="hidden" name="info" id="infoHidden">
          <small class="text-muted">Description, notes, terms…</small>
        </div>

      </div>
    </div>

    {{-- ===================== ✅ ADDITIONAL TAB ===================== --}}
    <div class="tab-pane" data-tab="additional">
      <div class="row g-3">

        <div class="col-lg-7">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold">Custom fields</div>
            <a href="javascript:void(0)" class="text-primary small" id="btnAddField">Add field</a>
          </div>

          <div id="fieldsWrap" class="border rounded bg-white p-2" style="min-height:280px">
            <div class="text-muted small px-2 py-2">
              هذه الحقول سيتم حفظها وربطها بالخدمة لاستخدامها لاحقًا عند إرسال الطلبات (خصوصًا server).
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="fw-bold mb-2">Groups</div>
          <div id="groupsPricingWrap" class="border rounded p-2 bg-white"></div>
          <div class="text-muted small mt-2">هنا يتم عرض User Groups ويتم حفظ الجدول تلقائيًا.</div>
        </div>

      </div>

      <template id="fieldTpl">
        <div class="border rounded mb-2 p-2 bg-light field-card" data-field>
          <div class="d-flex justify-content-between align-items-start">
            <div class="form-check form-switch">
              <input class="form-check-input js-field-active" type="checkbox" checked>
              <label class="form-check-label">Active</label>
            </div>
            <button type="button" class="btn btn-sm btn-danger js-remove-field" title="Remove">Remove</button>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-md-6">
              <label class="form-label mb-1">Name</label>
              <input type="text" class="form-control form-control-sm js-field-name" placeholder="Name">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Field type</label>
              <select class="form-select form-select-sm js-field-type">
                <option value="text" selected>Text</option>
                <option value="password">Password</option>
                <option value="textarea">Textarea</option>
                <option value="dropdown">Dropdown</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Input name</label>
              <input type="text" class="form-control form-control-sm js-field-input" placeholder="service_fields_1">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Description</label>
              <input type="text" class="form-control form-control-sm js-field-desc" placeholder="Description">
            </div>

            <div class="col-md-3">
              <label class="form-label mb-1">Min</label>
              <input type="number" class="form-control form-control-sm js-field-min" value="0">
            </div>

            <div class="col-md-3">
              <label class="form-label mb-1">Max</label>
              <input type="number" class="form-control form-control-sm js-field-max" value="0">
            </div>

            <div class="col-md-3">
              <label class="form-label mb-1">Validation</label>
              <select class="form-select form-select-sm js-field-validation">
                <option value="" selected>None</option>
                <option value="imei">IMEI</option>
                <option value="serial">Serial</option>
                <option value="email">Email</option>
                <option value="numeric">Numeric</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label mb-1">Required</label>
              <select class="form-select form-select-sm js-field-required">
                <option value="0" selected>No</option>
                <option value="1">Yes</option>
              </select>
            </div>

            <div class="col-12 js-options-wrap d-none">
              <label class="form-label mb-1">Options</label>
              <textarea class="form-control form-control-sm js-field-options" rows="2" placeholder="a,b,c"></textarea>
              <div class="form-text">Used only when Field type = Dropdown</div>
            </div>
          </div>
        </div>
      </template>

    </div>

    {{-- ===================== ✅ META TAB (اختياري) ===================== --}}
    <div class="tab-pane" data-tab="meta">
      <div class="alert alert-light mb-0">Meta tab…</div>
    </div>

  </div>

  <div class="mt-3 d-flex justify-content-end gap-2">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>

<script>
(function(){
  const form = document.getElementById('serviceCreateForm');
  if(!form) return;

  const btnAdd = form.querySelector('#btnAddField');
  const localHidden = form.querySelector('#customFieldsJson');

  function serializeFieldsInScope(scope){
    const localWrap = scope.querySelector('#fieldsWrap');
    if (!localWrap || !localHidden) return;

    const rows = [];
    localWrap.querySelectorAll('[data-field]').forEach(card => {
      const obj = {
        active: card.querySelector('.js-field-active')?.checked ? 1 : 0,
        name: (card.querySelector('.js-field-name')?.value || '').trim(),
        input: (card.querySelector('.js-field-input')?.value || '').trim(),
        description: (card.querySelector('.js-field-desc')?.value || '').trim(),
        minimum: parseInt(card.querySelector('.js-field-min')?.value || '0', 10),
        maximum: parseInt(card.querySelector('.js-field-max')?.value || '0', 10),
        validation: card.querySelector('.js-field-validation')?.value || '',
        required: parseInt(card.querySelector('.js-field-required')?.value || '0', 10),
        type: card.querySelector('.js-field-type')?.value || 'text',
        options: (card.querySelector('.js-field-options')?.value || '').trim(),
      };
      if (obj.name || obj.input) rows.push(obj);
    });

    localHidden.value = JSON.stringify(rows);
  }

  function bindCard(card, scope){
    const typeSel  = card.querySelector('.js-field-type');
    const optsWrap = card.querySelector('.js-options-wrap');

    const refreshOptions = () => {
      const t = typeSel?.value || 'text';
      optsWrap?.classList.toggle('d-none', t !== 'dropdown');
    };

    typeSel?.addEventListener('change', () => { refreshOptions(); serializeFieldsInScope(scope); });
    card.addEventListener('input', () => serializeFieldsInScope(scope));

    card.querySelector('.js-remove-field')?.addEventListener('click', () => {
      card.remove();
      serializeFieldsInScope(scope);
    });

    refreshOptions();
  }

  function mapRemoteType(t){
    const s = String(t || '').toLowerCase();
    if (s.includes('pass')) return 'password';
    if (s.includes('area')) return 'textarea';
    if (s.includes('select') || s.includes('drop')) return 'dropdown';
    return 'text';
  }

  function mapRemoteValidationByName(label){
    const s = String(label||'').toLowerCase();
    if (s.includes('imei')) return 'imei';
    if (s.includes('serial')) return 'serial';
    if (s.includes('email')) return 'email';
    return '';
  }

  function addField(scope, defaults = null){
    const localWrap = scope.querySelector('#fieldsWrap');
    const localTpl  = scope.querySelector('#fieldTpl');
    if (!localWrap || !localTpl) return;

    const node = localTpl.content.cloneNode(true);
    localWrap.appendChild(node);

    const cards = Array.from(localWrap.querySelectorAll('[data-field]'));
    const cardEl = cards[cards.length - 1];
    if (!cardEl) return;

    if (defaults){
      cardEl.querySelector('.js-field-active').checked = !!defaults.active;
      cardEl.querySelector('.js-field-name').value = defaults.name || '';
      cardEl.querySelector('.js-field-type').value = defaults.type || 'text';
      cardEl.querySelector('.js-field-input').value = defaults.input || '';
      cardEl.querySelector('.js-field-desc').value = defaults.description || '';
      cardEl.querySelector('.js-field-min').value = defaults.minimum ?? 0;
      cardEl.querySelector('.js-field-max').value = defaults.maximum ?? 0;
      cardEl.querySelector('.js-field-validation').value = defaults.validation || '';
      cardEl.querySelector('.js-field-required').value = String(defaults.required ?? 0);
      if (defaults.options) cardEl.querySelector('.js-field-options').value = defaults.options;
    }

    bindCard(cardEl, scope);
    serializeFieldsInScope(scope);
  }

  btnAdd?.addEventListener('click', (e) => {
    e.preventDefault();
    addField(form);
  });

  // =============== ✅ HOOKS used by service-modal.blade.php ===============
  window.__serverServiceApplyRemoteFields__ = function(scope, additionalFields){
    try{
      if (!scope || !Array.isArray(additionalFields)) return;

      const localWrap = scope.querySelector('#fieldsWrap');
      if (!localWrap) return;

      Array.from(localWrap.querySelectorAll('[data-field]')).forEach(x => x.remove());

      additionalFields.forEach((f, idx) => {
        const label = String(f.fieldname || f.name || '').trim();
        const input = 'service_fields_' + (idx + 1);
        const req = (String(f.required || '').toLowerCase() === 'on') ? 1 : 0;

        addField(scope, {
          active: 1,
          name: label || input,
          type: mapRemoteType(f.fieldtype || f.type || 'text'),
          input,
          description: String(f.description || '').trim(),
          minimum: 0,
          maximum: 0,
          validation: mapRemoteValidationByName(label),
          required: req,
          options: Array.isArray(f.fieldoptions) ? f.fieldoptions.join(',') : String(f.fieldoptions || '').trim(),
        });
      });

      serializeFieldsInScope(scope);
    }catch(e){
      console.warn('applyRemoteFields failed', e);
    }
  };

  window.__serverServiceSetMainField__ = function(scope, type, label){
    try{
      if (!scope) return;

      const t = String(type || '').toLowerCase().trim();
      const l = String(label || '').trim();

      const typeSel = scope.querySelector('#mainFieldType');
      const labInp  = scope.querySelector('#mainFieldLabel');

      if (typeSel && Array.from(typeSel.options).some(o => o.value === t)) {
        typeSel.value = t;
        typeSel.dispatchEvent(new Event('change'));
      }
      if (labInp && l) labInp.value = l;

      // main_type hidden (backend)
      const mainTypeHidden = scope.querySelector('#mainTypeHidden');
      if (mainTypeHidden) mainTypeHidden.value = t;

    }catch(e){
      console.warn('setMainField failed', e);
    }
  };

  // initial serialize
  serializeFieldsInScope(form);
})();
</script>
