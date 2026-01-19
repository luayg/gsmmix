{{-- resources/views/admin/services/imei/_modal_create.blade.php --}}

<form id="serviceCreateForm"
      class="service-create-form js-ajax-form"
      action="{{ route('admin.services.imei.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  {{-- ✅ Additional payloads (sent as JSON strings) --}}
  <input type="hidden" name="custom_fields_json" id="customFieldsHidden" value="[]">
  <input type="hidden" name="group_prices_json" id="groupPricesHidden" value="[]">

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
              <input name="name" type="text" class="form-control" required>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">
                Alias (Unique name containing only latin lowercase characters and dashes)
              </label>
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

            {{-- ✅ MAIN FIELD PRESETS --}}
            <div class="col-md-6">
              <label class="form-label mb-1">Main field type</label>
              <select name="main_field_type" class="form-select" id="mainFieldType">
                <option value="imei" selected>IMEI</option>
                <option value="imei_serial">IMEI/Serial number</option>
                <option value="serial">Serial number</option>
                <option value="custom">Custom</option>

                {{-- legacy --}}
                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="text">Text</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Type</label>
              <select name="type" class="form-select">
                <option value="imei" selected>IMEI</option>
                <option value="server">Server</option>
                <option value="file">File</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Main field label</label>
              <input name="main_field_label" id="mainFieldLabel" type="text" class="form-control" value="IMEI">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Allowed characters</label>
              <select name="allowed_characters" id="allowedChars" class="form-select">
                <option value="numbers" selected>Numbers</option>
                <option value="alnum">Letters and numbers</option>
                <option value="any">Any</option>
                <option value="hex">HEX</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <div class="input-group">
                <input name="min" id="minLen" type="number" class="form-control" value="15">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <div class="input-group">
                <input name="max" id="maxLen" type="number" class="form-control" value="15">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            {{-- Price --}}
            <div class="col-md-6">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input id="pricePreview" type="text" class="form-control" value="0.0000" disabled>
                <span class="input-group-text">Credits</span>
              </div>
              <small class="text-muted d-block mt-1">Price = Cost + Profit</small>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Converted price</label>
              <div class="input-group">
                <input id="convertedPricePreview" type="text" class="form-control" value="0.0000" disabled>
                <span class="input-group-text">USD</span>
              </div>
            </div>

            {{-- Cost/Profit --}}
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

            {{-- Source --}}
            <div class="col-12">
              <label class="form-label mb-1">Source</label>
              <select name="source" class="form-select">
                <option value="1">Manual</option>
                <option value="2" selected>API</option>
                <option value="3">Supplier</option>
                <option value="4">Local source</option>
              </select>
            </div>

            {{-- API block --}}
            <div class="col-12 js-api-block d-none">
              <div class="border rounded p-3 bg-light">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label mb-1">API connection</label>
                    <select class="form-select js-api-provider" name="api_provider_id"></select>
                  </div>
                  <div class="col-12">
                    <label class="form-label mb-1">API service</label>
                    <select class="form-select js-api-service" name="api_service_remote_id"></select>
                    <small class="text-muted">Search directly inside list.</small>
                  </div>
                </div>
              </div>
            </div>

            {{-- Switches --}}
            @php
              $toggles = [
                'use_remote_cost'    => 'Sync the cost of this service with price of remote API service',
                'use_remote_price'   => 'Sync the main and special prices of this service with price of remote API service',
                'stop_on_api_change' => 'Stop API if the remote service price went up',
                'needs_approval'     => 'Needs approval',
                'active'             => 'Active',
                'allow_bulk'         => 'Allow bulk orders',
                'allow_duplicates'   => 'Allow duplicates',
                'reply_with_latest'  => 'Reply with latest success result if possible',
                'allow_report'       => 'Allow submit to verify (success orders)',
                'allow_cancel'       => 'Allow cancel (waiting action orders)',
              ];
            @endphp

            <div class="col-12">
              @foreach($toggles as $name => $label)
                <input type="hidden" name="{{ $name }}" value="0">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input"
                         type="checkbox"
                         name="{{ $name }}"
                         value="1"
                         id="sw_{{ $name }}"
                         @checked(in_array($name,['active','allow_bulk']) ? true : false)>
                  <label class="form-check-label" for="sw_{{ $name }}">{{ $label }}</label>
                </div>
              @endforeach
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Reporting deny timeout</label>
              <div class="input-group">
                <input name="allow_report_time" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
              <small class="text-muted">Leave blank or set to 0 for unlimited</small>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Cancellation deny timeout</label>
              <div class="input-group">
                <input name="allow_cancel_time" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
              <small class="text-muted">Leave blank or set to 0 for unlimited</small>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Reply expiration</label>
              <div class="input-group">
                <input name="reply_expiration" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
            </div>

          </div>
        </div>

        {{-- RIGHT SIDE --}}
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
        {{-- LEFT: Custom fields --}}
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

        {{-- RIGHT: Groups pricing --}}
        <div class="col-lg-5">
          <div class="fw-bold mb-2">Groups</div>
          <div id="groupsPricingWrap" class="border rounded p-2 bg-white">
            {{-- سيتم بناؤه بالـ JS --}}
          </div>
          <div class="text-muted small mt-2">
            هنا يتم عرض User Groups (Basic / VIP / Reseller ..) ويتم حفظ الجدول تلقائيًا.
          </div>
        </div>
      </div>

      {{-- Template for one field --}}
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
                <option value="dropdown">Dropdown</option>
                <option value="radio">Radio</option>
                <option value="textarea">Textarea</option>
                <option value="checkbox">Checkbox</option>
                <option value="file">File</option>
                <option value="image">Image</option>
                <option value="country">Country</option>
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
              <label class="form-label mb-1">Minimum</label>
              <input type="number" class="form-control form-control-sm js-field-min" value="0">
            </div>

            <div class="col-md-3">
              <label class="form-label mb-1">Maximum</label>
              <input type="number" class="form-control form-control-sm js-field-max" value="0">
            </div>

            <div class="col-md-3">
              <label class="form-label mb-1">Validation</label>
              <select class="form-select form-select-sm js-field-validation">
                <option value="" selected>None</option>
                <option value="numeric">Numeric</option>
                <option value="alphanumeric">Alphanumeric</option>
                <option value="email">Email</option>
                <option value="url">URL</option>
                <option value="json">JSON</option>
                <option value="ip">IP</option>
                <option value="accepted">Accepted</option>
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
              <label class="form-label mb-1">Options (comma separated)</label>
              <input type="text" class="form-control form-control-sm js-field-options" placeholder="New,Existing">
            </div>
          </div>
        </div>
      </template>

    </div>

    {{-- ===================== ✅ META TAB ===================== --}}
    <div class="tab-pane" data-tab="meta">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Meta keywords</label>
          <input type="text" class="form-control" name="meta_keywords">
        </div>

        <div class="col-md-6">
          <label class="form-label">After "head" tag opening</label>
          <textarea class="form-control" rows="3" name="meta_after_head"></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Meta description</label>
          <textarea class="form-control" rows="3" name="meta_description"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Before "head" tag closing</label>
          <textarea class="form-control" rows="3" name="meta_before_head"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">After "body" tag opening</label>
          <textarea class="form-control" rows="3" name="meta_after_body"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Before "body" tag closing</label>
          <textarea class="form-control" rows="3" name="meta_before_body"></textarea>
        </div>
      </div>
    </div>

  </div>

  <div class="service-actions d-flex justify-content-end gap-2 mt-3">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>

</form>

<script>
(function(){
  // =========================
  // Main field presets
  // =========================
  const presets = {
    imei:        { label: 'IMEI',              allowed: 'numbers', min: 15, max: 15 },
    imei_serial: { label: 'IMEI/Serial number',allowed: 'alnum',   min: 10, max: 15 },
    serial:      { label: 'Serial number',     allowed: 'alnum',   min: 10, max: 13 },
    custom:      { label: 'Device',            allowed: 'alnum',   min: 10, max: 15 },
    number:      { label: 'Number',            allowed: 'numbers', min: 1,  max: 255 },
    email:       { label: 'Email',             allowed: 'any',     min: 3,  max: 255 },
    text:        { label: 'Text',              allowed: 'any',     min: 1,  max: 255 },
  };

  const mainType  = document.getElementById('mainFieldType');
  const labelEl   = document.getElementById('mainFieldLabel');
  const allowedEl = document.getElementById('allowedChars');
  const minEl     = document.getElementById('minLen');
  const maxEl     = document.getElementById('maxLen');

  function applyPreset(v){
    const p = presets[v] || null;
    if (!p) return;
    if (labelEl) labelEl.value = p.label;
    if (allowedEl) allowedEl.value = p.allowed;
    if (minEl) minEl.value = p.min;
    if (maxEl) maxEl.value = p.max;
  }
  mainType?.addEventListener('change', () => applyPreset(mainType.value));
  if (mainType) applyPreset(mainType.value);

  // =========================
  // Custom fields UI + serialization -> custom_fields_json
  // =========================
  const wrap   = document.getElementById('fieldsWrap');
  const tpl    = document.getElementById('fieldTpl');
  const btnAdd = document.getElementById('btnAddField');
  const hidden = document.getElementById('customFieldsHidden');

  function toSlugInputName(name){
    const base = (name || '').trim().toLowerCase()
      .replace(/[^a-z0-9]+/g,'_')
      .replace(/^_+|_+$/g,'');
    return base ? `service_fields_${base}` : `service_fields_${Date.now()}`;
  }

  function serializeFields(){
    if (!wrap || !hidden) return;

    const rows = [];
    wrap.querySelectorAll('[data-field]').forEach(card => {
      const type = card.querySelector('.js-field-type')?.value || 'text';

      const obj = {
        active: card.querySelector('.js-field-active')?.checked ? 1 : 0,
        name: (card.querySelector('.js-field-name')?.value || '').trim(),
        input: (card.querySelector('.js-field-input')?.value || '').trim(),
        description: (card.querySelector('.js-field-desc')?.value || '').trim(),
        minimum: parseInt(card.querySelector('.js-field-min')?.value || '0', 10),
        maximum: parseInt(card.querySelector('.js-field-max')?.value || '0', 10),
        validation: card.querySelector('.js-field-validation')?.value || '',
        required: parseInt(card.querySelector('.js-field-required')?.value || '0', 10),
        field_type: type,
        field_options: (card.querySelector('.js-field-options')?.value || '').trim(),
      };

      if (obj.name || obj.input) rows.push(obj);
    });

    hidden.value = JSON.stringify(rows);
  }

  function bindCard(card){
    const typeSel   = card.querySelector('.js-field-type');
    const optsWrap  = card.querySelector('.js-options-wrap');
    const nameEl    = card.querySelector('.js-field-name');
    const inputEl   = card.querySelector('.js-field-input');

    function refreshOptions(){
      const t = typeSel.value;
      const show = (t === 'dropdown' || t === 'radio');
      optsWrap.classList.toggle('d-none', !show);
    }

    typeSel.addEventListener('change', () => { refreshOptions(); serializeFields(); });

    nameEl.addEventListener('input', () => {
      if (!inputEl.value.trim()) inputEl.value = toSlugInputName(nameEl.value);
      serializeFields();
    });

    card.addEventListener('input', serializeFields);
    card.querySelector('.js-remove-field').addEventListener('click', () => {
      card.remove();
      serializeFields();
    });

    refreshOptions();
  }

  function addField(defaults = null){
    if (!tpl || !wrap) return;

    const node = tpl.content.cloneNode(true);
    wrap.appendChild(node);

    const cards = wrap.querySelectorAll('[data-field]');
    const cardEl = cards[cards.length - 1];

    if (defaults){
      cardEl.querySelector('.js-field-active').checked = !!defaults.active;
      cardEl.querySelector('.js-field-name').value = defaults.name || '';
      cardEl.querySelector('.js-field-type').value = defaults.field_type || defaults.type || 'text';
      cardEl.querySelector('.js-field-input').value = defaults.input || '';
      cardEl.querySelector('.js-field-desc').value = defaults.description || '';
      cardEl.querySelector('.js-field-min').value = defaults.minimum ?? 0;
      cardEl.querySelector('.js-field-max').value = defaults.maximum ?? 0;
      cardEl.querySelector('.js-field-validation').value = defaults.validation || '';
      cardEl.querySelector('.js-field-required').value = String(defaults.required ?? 0);
      if (defaults.field_options) cardEl.querySelector('.js-field-options').value = defaults.field_options;
      if (defaults.options) cardEl.querySelector('.js-field-options').value = defaults.options; // legacy
    }

    bindCard(cardEl);
    serializeFields();
  }

  btnAdd?.addEventListener('click', () => addField());

  // start with one empty field? (اختياري)
  // addField();

})();
</script>
