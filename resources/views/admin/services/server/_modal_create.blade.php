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

  {{-- ✅ Custom fields JSON --}}
  <input type="hidden" name="custom_fields_json" id="customFieldsJson" value="[]">

  {{-- Injected by service-modal.js --}}
  <input type="hidden" name="supplier_id" value="">
  <input type="hidden" name="remote_id" value="">
  <input type="hidden" name="group_name" value="">

  <div class="service-tabs-content">

    {{-- ===================== ✅ GENERAL TAB ===================== --}}
    <div class="tab-pane active" data-tab="general">
      <div class="row g-3">

        {{-- LEFT SIDE --}}
        <div class="col-xl-7">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label mb-1">Name</label>
              <input name="name" id="nameInput" type="text" class="form-control" required>
              <small class="text-muted">سيتم تعبئة name_en تلقائياً بنفس الاسم.</small>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Alias (Unique name containing only latin lowercase characters and dashes)</label>
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

            {{-- ✅ MAIN FIELD --}}
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
              <select name="allowed_characters" id="allowedChars" class="form-select">
                <option value="any" selected>Any</option>
                <option value="numbers">Numbers</option>
                <option value="alphanumeric">Alphanumeric</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <div class="input-group">
                <input name="minimum" id="minChars" type="number" class="form-control" value="1">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <div class="input-group">
                <input name="maximum" id="maxChars" type="number" class="form-control" value="50">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input id="pricePreview" type="text" class="form-control" value="0.0000" readonly>
                <span class="input-group-text">Credits</span>
              </div>
              <div class="d-none">
                <input id="convertedPricePreview" type="text" value="0.0000" readonly>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Cost</label>
              <div class="input-group">
                <input name="cost" type="number" step="0.0001" class="form-control" value="0.0000">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Profit</label>
              <div class="input-group">
                <input name="profit" type="number" step="0.0001" class="form-control" value="0.0000">
                <span class="input-group-text">Credits</span>
                <select name="profit_type" class="form-select" style="max-width:120px">
                  <option value="1" selected>Credits</option>
                  <option value="2">Percent</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Source</label>
              <select name="source" class="form-select">
                <option value="1" selected>Manual</option>
                <option value="2">API</option>
              </select>
            </div>

          </div>
        </div>

        {{-- RIGHT SIDE --}}
        <div class="col-xl-5">
          <label class="form-label mb-1">Info</label>

          {{-- Summernote target --}}
          <textarea id="infoEditor" class="form-control d-none" rows="10"></textarea>
          <input type="hidden" name="info" id="infoHidden" value="">

          <small class="text-muted">Description, notes, terms…</small>
        </div>

      </div>
    </div>

    {{-- ===================== ✅ ADDITIONAL TAB ===================== --}}
    <div class="tab-pane" data-tab="additional">
      <div class="row g-3">

        <div class="col-lg-6">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0">Custom fields</h6>
            <button type="button" class="btn btn-link p-0" id="btnAddField">Add field</button>
          </div>

          <div id="fieldsWrap"></div>

          <template id="fieldTpl">
            <div class="card mb-2" data-field>
              <div class="card-header d-flex align-items-center justify-content-between py-2">
                <div class="form-check form-switch m-0">
                  <input class="form-check-input" type="checkbox" data-active checked>
                  <label class="form-check-label small">Active</label>
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
                    <label class="form-label mb-1">Options</label>
                    <textarea class="form-control form-control-sm" rows="3" data-options></textarea>
                    <div class="form-text">Used only when Field type = Select</div>
                  </div>
                </div>
              </div>
            </div>
          </template>

        </div>

        <div class="col-lg-6">
          <h6 class="mb-2">Groups</h6>
          <div id="groupsPricingWrap"></div>

          <input type="hidden" name="group_prices_json" id="pricingTableHidden" value="[]">

          <div class="form-text">
            Set special prices/discounts per user group for this service.
          </div>
        </div>

      </div>
    </div>

    {{-- ===================== ✅ META TAB ===================== --}}
    <div class="tab-pane" data-tab="meta">
      <div class="alert alert-light mb-0">
        Meta tab placeholder (if you need extra meta fields, add them here).
      </div>
    </div>

  </div>

  <div class="d-flex justify-content-end gap-2 mt-3">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>

</form>

<script>
(function(){
  const form = document.getElementById('serviceCreateForm');
  if (!form) return;

  const fieldsWrap = form.querySelector('#fieldsWrap');
  const fieldTpl   = form.querySelector('#fieldTpl');
  const btnAdd     = form.querySelector('#btnAddField');
  const outJson    = form.querySelector('#customFieldsJson');

  const nameInput  = form.querySelector('#nameInput');
  const nameEn     = form.querySelector('#nameEnHidden');
  const mainTypeHidden = form.querySelector('#mainTypeHidden');
  const mainFieldType  = form.querySelector('#mainFieldType');

  const slugify = (s) => String(s||'').toLowerCase().trim().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');

  function toggleOptions(card){
    const type = card.querySelector('[data-type]')?.value || 'text';
    const box  = card.querySelector('[data-options-wrap]');
    if (!box) return;
    box.style.display = (type === 'select') ? '' : 'none';
  }

  function serializeFieldsInScope(scope){
    const wrap = scope.querySelector('#fieldsWrap');
    const out  = scope.querySelector('#customFieldsJson');
    if (!wrap || !out) return;

    const data = [];
    wrap.querySelectorAll('[data-field]').forEach(card => {
      const type = card.querySelector('[data-type]')?.value || 'text';
      const options = (card.querySelector('[data-options]')?.value || '').trim();

      data.push({
        active: card.querySelector('[data-active]')?.checked ? 1 : 0,
        name: card.querySelector('[data-name]')?.value || '',
        type,
        input: card.querySelector('[data-input]')?.value || '',
        description: card.querySelector('[data-desc]')?.value || '',
        minimum: Number(card.querySelector('[data-min]')?.value || 0),
        maximum: Number(card.querySelector('[data-max]')?.value || 0),
        validation: card.querySelector('[data-validation]')?.value || '',
        required: Number(card.querySelector('[data-required]')?.value || 0),
        options,
      });
    });

    out.value = JSON.stringify(data);
  }

  function bindCard(card){
    card.querySelector('[data-remove]')?.addEventListener('click', () => {
      card.remove();
      serializeFieldsInScope(form);
    });

    card.querySelector('[data-name]')?.addEventListener('input', (e) => {
      const inp = card.querySelector('[data-input]');
      if (inp && !inp.value) inp.value = slugify(e.target.value);
      serializeFieldsInScope(form);
    });

    card.querySelectorAll('input,select,textarea').forEach(el => {
      el.addEventListener('input', () => serializeFieldsInScope(form));
      el.addEventListener('change', () => {
        toggleOptions(card);
        serializeFieldsInScope(form);
      });
    });

    toggleOptions(card);
  }

  function addField(scope, prefill = null){
    const wrap = scope.querySelector('#fieldsWrap');
    const tpl  = scope.querySelector('#fieldTpl');
    if (!wrap || !tpl) return;

    const node = tpl.content.cloneNode(true);
    const card = node.querySelector('[data-field]');

    if (prefill) {
      card.querySelector('[data-active]').checked = !!prefill.active;
      card.querySelector('[data-name]').value = prefill.name || '';
      card.querySelector('[data-type]').value = prefill.type || 'text';
      card.querySelector('[data-input]').value = prefill.input || '';
      card.querySelector('[data-desc]').value = prefill.description || '';
      card.querySelector('[data-min]').value = (prefill.minimum ?? 0);
      card.querySelector('[data-max]').value = (prefill.maximum ?? 0);
      card.querySelector('[data-validation]').value = prefill.validation || '';
      card.querySelector('[data-required]').value = String(prefill.required ?? 0);
      if (prefill.options !== undefined) card.querySelector('[data-options]').value = String(prefill.options || '');
    }

    wrap.appendChild(node);
    const last = wrap.querySelectorAll('[data-field]')[wrap.querySelectorAll('[data-field]').length - 1];
    bindCard(last);
    serializeFieldsInScope(form);
  }

  function mapRemoteType(t){
    const x = String(t||'').toLowerCase();
    if (['dropdown','select'].includes(x)) return 'select';
    if (['textarea','text_area'].includes(x)) return 'textarea';
    if (['password'].includes(x)) return 'password';
    if (['email'].includes(x)) return 'email';
    if (['number','numeric','int','integer'].includes(x)) return 'number';
    return 'text';
  }

  function mapRemoteValidationByName(label){
    const n = String(label||'').toLowerCase();
    if (n.includes('imei')) return 'imei';
    if (n.includes('serial')) return 'serial';
    if (n.includes('email')) return 'email';
    return '';
  }

  // ========= basic sync for hidden required fields =========
  nameInput?.addEventListener('input', () => { if (nameEn) nameEn.value = nameInput.value; });
  if (nameInput && nameEn) nameEn.value = nameInput.value || '';

  function syncMainTypeHidden(){
    if (!mainTypeHidden || !mainFieldType) return;
    mainTypeHidden.value = mainFieldType.value || '';
  }
  mainFieldType?.addEventListener('change', syncMainTypeHidden);
  syncMainTypeHidden();

  // ========= add field button =========
  btnAdd?.addEventListener('click', () => addField(form));

  // create 1 empty field by default (optional)
  if (fieldsWrap && fieldsWrap.querySelectorAll('[data-field]').length === 0) {
    addField(form);
  }

  // ============== ✅ HOOKS used by service-modal.blade.php ===============
  window.__serverServiceApplyRemoteFields__ = function(scope, additionalFields){
    try{
      if (!scope || !Array.isArray(additionalFields)) return;

      const localWrap = scope.querySelector('#fieldsWrap');
      if (!localWrap) return;

      // امسح الكروت فقط
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
    }catch(e){
      console.warn('setMainField failed', e);
    }
  };

  // initial serialize
  serializeFieldsInScope(form);

})();
</script>
