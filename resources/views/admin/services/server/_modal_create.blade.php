{{-- resources/views/admin/services/server/_modal_create.blade.php --}}
@php
  $isCreate = true;
@endphp

<form id="serviceCreateForm"
      action="{{ route('admin.services.server.store') }}"
      method="POST"
      enctype="multipart/form-data"
      data-service-form="create"
      data-kind="server">
  @csrf

  {{-- ===== Header ===== --}}
  <div class="modal-header" style="background:#3bb37a;color:#fff;">
    <div class="d-flex flex-column">
      <div class="h6 mb-0">Create service</div>
      <div class="small opacity-75">
        Provider: <span id="modalProviderName">—</span> | Remote ID: <span id="modalRemoteId">—</span>
      </div>
    </div>

    <div class="d-flex gap-2 align-items-center ms-auto">
      <button type="button" class="btn btn-sm btn-light px-3 tab-btn" data-tab="general">General</button>
      <button type="button" class="btn btn-sm btn-light px-3 tab-btn" data-tab="additional">Additional</button>
      <button type="button" class="btn btn-sm btn-light px-3 tab-btn" data-tab="meta">Meta</button>

      <span class="badge bg-dark" id="modalTypeBadge">Type: SERVER</span>
      <span class="badge bg-dark" id="modalPriceBadge">Price: 0 Credits</span>

      <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal"></button>
    </div>
  </div>

  {{-- ===== Hidden (from remote) ===== --}}
  <input type="hidden" name="supplier_id" id="supplierId" value="">
  <input type="hidden" name="remote_id"   id="remoteId"   value="">
  <input type="hidden" name="type"        id="serviceType" value="server">
  <input type="hidden" name="name"        id="serviceNameHidden" value="">
  <input type="hidden" name="cost"        id="serviceCostHidden" value="0">
  <input type="hidden" name="params"      id="serviceParamsHidden" value="">
  <input type="hidden" name="custom_fields" id="customFieldsJson" value="[]">

  {{-- additional fields from remote (JSON string) --}}
  <input type="hidden" id="remoteAdditionalFieldsJson" value="">

  {{-- ✅ hide main_field_type for SERVER only --}}
  <input type="hidden" name="main_field_type" value="">

  {{-- ===== Body ===== --}}
  <div class="modal-body p-0">

    {{-- Tabs --}}
    <div class="tab-panels">

      {{-- =========================
           ✅ TAB: GENERAL
         ========================= --}}
      <div class="tab-panel p-3" data-tab-panel="general">

        <div class="row g-3">
          <div class="col-lg-7">

            <div class="mb-2">
              <label class="form-label mb-1">Name</label>
              <input type="text" class="form-control" name="display_name" id="serviceName" value="" required>
              <div class="form-text">Alias (unique name containing only latin lowercase characters and dashes)</div>
            </div>

            <div class="mb-2">
              <label class="form-label mb-1">Slug</label>
              <input type="text" class="form-control" name="slug" id="serviceSlug" value="">
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-4">
                <label class="form-label mb-1">Delivery time</label>
                <input type="text" class="form-control" name="delivery_time" id="deliveryTime" value="">
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Group</label>
                <select class="form-select" name="group_id" id="groupSelect"></select>
              </div>
              <div class="col-md-4">
                <label class="form-label mb-1">Type</label>
                <select class="form-select" name="service_type" id="typeSelect" disabled>
                  <option value="server" selected>Server</option>
                </select>
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label mb-1">Minimum quantity</label>
                <input type="number" class="form-control" name="min_qty" value="1">
                <div class="form-text">leave blank or set to 0 for unlimited</div>
              </div>
              <div class="col-md-6">
                <label class="form-label mb-1">Maximum quantity</label>
                <input type="number" class="form-control" name="max_qty" value="0">
                <div class="form-text">leave blank or set to 0 for unlimited</div>
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-4">
                <label class="form-label mb-1">Price</label>
                <div class="input-group">
                  <input type="number" step="0.0001" class="form-control" name="price" id="priceInput" value="0">
                  <span class="input-group-text">Credits</span>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label mb-1">Converted price</label>
                <div class="input-group">
                  <input type="number" step="0.0001" class="form-control" name="converted_price" id="convertedPrice" value="0">
                  <select class="form-select" name="converted_currency" style="max-width:90px">
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <label class="form-label mb-1">Cost</label>
                <div class="input-group">
                  <input type="number" step="0.0001" class="form-control" name="cost_value" id="costInput" value="0">
                  <span class="input-group-text">Credits</span>
                </div>
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label mb-1">Profit</label>
                <div class="input-group">
                  <input type="number" step="0.0001" class="form-control" name="profit_value" id="profitInput" value="0">
                  <select class="form-select" name="profit_type" style="max-width:90px">
                    <option value="credits">Credits</option>
                    <option value="percent">%</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label mb-1">Source</label>
              <select class="form-select" name="source" id="sourceSelect" disabled>
                <option value="api" selected>API</option>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label mb-1">API connection</label>
              <input type="text" class="form-control" name="api_connection" id="apiConnection" readonly>
            </div>

            <div class="mb-2">
              <label class="form-label mb-1">API service</label>
              <input type="text" class="form-control" name="api_service" id="apiService" readonly>
            </div>

            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="syncCostSwitch" name="sync_cost" value="1">
              <label class="form-check-label" for="syncCostSwitch">
                Sync the cost of this service with price of remote API service
              </label>
              <div class="form-text">
                enabling this will automatically set the cost of this service equal to price of remote service
              </div>
            </div>

            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="syncPricesSwitch" name="sync_prices" value="1">
              <label class="form-check-label" for="syncPricesSwitch">
                Sync the main and special prices of this service with price of remote API service
              </label>
              <div class="form-text">
                enabling this will automatically set the main and special prices for users and groups regarding their discounts.
              </div>
            </div>

            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="stopApiIfUpSwitch" name="stop_api_if_price_up" value="1">
              <label class="form-check-label" for="stopApiIfUpSwitch">Stop API if the remote service price went up</label>
              <div class="form-text">
                this will disconnect from remote service and switch your service to manual if the remote API service price went up
              </div>
            </div>

            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" role="switch" id="needsApprovalSwitch" name="needs_approval" value="1">
              <label class="form-check-label" for="needsApprovalSwitch">Needs approval</label>
              <div class="form-text">
                enabling this will not process API orders automatically until you manually approve them
              </div>
            </div>

          </div>

          <div class="col-lg-5">
            <label class="form-label mb-1">Info</label>

            {{-- ✅ Summernote textarea + hidden value --}}
            <textarea id="infoEditor"
                      class="form-control"
                      data-editor="summernote"
                      data-summernote="1"
                      data-summernote-height="360"
                      data-upload-url="{{ route('admin.uploads.summernote') }}"
                      placeholder="Write service info..."></textarea>
            <input type="hidden" id="infoHidden" name="info" value="">

            <div class="form-text mt-2">
              Rich editor inside modal (Summernote).
            </div>
          </div>
        </div>

      </div>

      {{-- =========================
           ✅ TAB: ADDITIONAL
         ========================= --}}
      <div class="tab-panel p-3" data-tab-panel="additional">

        <div class="row g-3">
          <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">Custom fields</h6>
              <a href="#" id="btnAddCustomField">Add field</a>
            </div>

            <div id="customFieldsWrap"></div>
          </div>

          <div class="col-lg-5">
            <h6 class="mb-2">Groups</h6>
            <div id="groupsPricingWrap"></div>
            <div class="small text-muted mt-2">
              Set special prices/discounts per user group for this service.
            </div>
          </div>
        </div>

      </div>

      {{-- =========================
           ✅ TAB: META
         ========================= --}}
      <div class="tab-panel p-3" data-tab-panel="meta">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label mb-1">Meta title</label>
            <input type="text" class="form-control" name="meta_title">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Meta keywords</label>
            <input type="text" class="form-control" name="meta_keywords">
          </div>
          <div class="col-12">
            <label class="form-label mb-1">Meta description</label>
            <textarea class="form-control" name="meta_description" rows="4"></textarea>
          </div>
        </div>
      </div>

    </div>{{-- /.tab-panels --}}

  </div>{{-- /.modal-body --}}

  {{-- ===== Footer ===== --}}
  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>

</form>

<script>
(function(){
  const form = document.getElementById('serviceCreateForm');
  if(!form) return;

  // ========= Tabs =========
  const setActiveTab = (name) => {
    // buttons
    form.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.toggle('btn-dark', b.dataset.tab === name);
      b.classList.toggle('btn-light', b.dataset.tab !== name);
    });

    // panels
    form.querySelectorAll('.tab-panel').forEach(p => {
      p.style.display = (p.dataset.tabPanel === name) ? '' : 'none';
    });
  };

  // default = General
  setActiveTab('general');

  // click handlers
  form.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      setActiveTab(btn.dataset.tab);
    });
  });

  // ========= Custom Fields (Additional) =========
  const customWrap = document.getElementById('customFieldsWrap');
  const hiddenJson = document.getElementById('customFieldsJson');

  const readRemoteAdditionalFields = () => {
    const raw = document.getElementById('remoteAdditionalFieldsJson')?.value || '';
    if(!raw) return [];
    try{
      const parsed = JSON.parse(raw);
      if(Array.isArray(parsed)) return parsed;
      // sometimes: { custom_fields: [...] }
      if(parsed && Array.isArray(parsed.custom_fields)) return parsed.custom_fields;
      return [];
    }catch(e){
      return [];
    }
  };

  const mapRemoteType = (t) => {
    // normalize to your UI types
    const s = String(t||'').toLowerCase();
    if(s.includes('longtext') || s.includes('textarea')) return 'longtext';
    if(s.includes('email')) return 'text';
    if(s.includes('number')) return 'number';
    if(s.includes('select')) return 'select';
    return 'text';
  };

  const escapeHtml = (str) => String(str||'')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');

  const buildFieldRow = (field, idx) => {
    const name = field.fieldname || field.name || '';
    const input = field.inputname || field.input_name || `service_fields_${idx+1}`;
    const desc = field.description || '';
    const type = mapRemoteType(field.fieldtype || field.type || 'text');
    const required = String(field.required || 'off').toLowerCase() === 'on';

    const validation = (field.validation || field.regex || '').toString();

    const html = `
      <div class="border rounded p-2 mb-2 custom-field" data-idx="${idx}">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" data-field-active ${field.active === false ? '' : 'checked'}>
            <label class="form-check-label">Active</label>
          </div>

          <button type="button" class="btn btn-sm btn-danger" data-field-remove>×</button>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label mb-1">Name</label>
            <input type="text" class="form-control form-control-sm" data-field-name value="${escapeHtml(name)}">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Field type</label>
            <select class="form-select form-select-sm" data-field-type>
              <option value="text" ${type==='text'?'selected':''}>Text</option>
              <option value="number" ${type==='number'?'selected':''}>Number</option>
              <option value="longtext" ${type==='longtext'?'selected':''}>Long text</option>
              <option value="select" ${type==='select'?'selected':''}>Select</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label mb-1">Input name</label>
            <input type="text" class="form-control form-control-sm" data-field-input value="${escapeHtml(input)}">
          </div>
          <div class="col-md-6">
            <label class="form-label mb-1">Description</label>
            <input type="text" class="form-control form-control-sm" data-field-desc value="${escapeHtml(desc)}">
          </div>

          <div class="col-md-4">
            <label class="form-label mb-1">Minimum</label>
            <input type="number" class="form-control form-control-sm" data-field-min value="${escapeHtml(field.minimum ?? 0)}">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Maximum</label>
            <input type="number" class="form-control form-control-sm" data-field-max value="${escapeHtml(field.maximum ?? 0)}">
          </div>
          <div class="col-md-4">
            <label class="form-label mb-1">Validation</label>
            <input type="text" class="form-control form-control-sm" data-field-validation value="${escapeHtml(validation)}">
          </div>

          <div class="col-md-6">
            <label class="form-label mb-1">Required</label>
            <select class="form-select form-select-sm" data-field-required>
              <option value="off" ${required ? '' : 'selected'}>No</option>
              <option value="on"  ${required ? 'selected' : ''}>Yes</option>
            </select>
          </div>

        </div>
      </div>
    `;
    const wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    return wrap.firstElementChild;
  };

  const serializeFieldsInScope = () => {
    const out = [];
    customWrap?.querySelectorAll('.custom-field').forEach((row, idx) => {
      const active = row.querySelector('[data-field-active]')?.checked ?? true;
      const fieldname = row.querySelector('[data-field-name]')?.value || '';
      const inputname = row.querySelector('[data-field-input]')?.value || `service_fields_${idx+1}`;
      const description = row.querySelector('[data-field-desc]')?.value || '';
      const fieldtype = row.querySelector('[data-field-type]')?.value || 'text';
      const minimum = row.querySelector('[data-field-min]')?.value || 0;
      const maximum = row.querySelector('[data-field-max]')?.value || 0;
      const validation = row.querySelector('[data-field-validation]')?.value || '';
      const required = row.querySelector('[data-field-required]')?.value || 'off';

      out.push({
        active,
        fieldname,
        inputname,
        description,
        fieldtype,
        minimum,
        maximum,
        validation,
        required,
      });
    });

    // wrap like original: { custom_fields: [...] }
    hiddenJson.value = JSON.stringify({ custom_fields: out });
  };

  const addField = (field = {}, idx = 0) => {
    if(!customWrap) return;
    const row = buildFieldRow(field, idx);
    customWrap.appendChild(row);

    row.querySelector('[data-field-remove]')?.addEventListener('click', () => {
      row.remove();
      serializeFieldsInScope();
    });

    row.querySelectorAll('input,select').forEach(el=>{
      el.addEventListener('input', serializeFieldsInScope);
      el.addEventListener('change', serializeFieldsInScope);
    });

    serializeFieldsInScope();
  };

  document.getElementById('btnAddCustomField')?.addEventListener('click', (e) => {
    e.preventDefault();
    addField({}, customWrap?.querySelectorAll('.custom-field').length || 0);
  });

  // ========= Groups pricing (placeholder UI) =========
  const groupsWrap = document.getElementById('groupsPricingWrap');
  function buildGroupsPricing() {
    if(!groupsWrap) return;
    groupsWrap.innerHTML = `
      <div class="border rounded p-2 mb-2">
        <div class="fw-semibold mb-1">Basic</div>
        <div class="row g-2">
          <div class="col-7">
            <label class="form-label mb-1 small">Price</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.0001" class="form-control" value="${document.getElementById('priceInput')?.value || 0}">
              <span class="input-group-text">Credits</span>
            </div>
            <div class="small text-muted mt-1">Final: <span class="js-final">${document.getElementById('priceInput')?.value || 0}</span> Credits</div>
          </div>
          <div class="col-5">
            <label class="form-label mb-1 small">Discount</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.0001" class="form-control" value="0">
              <select class="form-select" style="max-width:90px">
                <option>Credits</option>
              </select>
              <button class="btn btn-outline-secondary" type="button">Reset</button>
            </div>
          </div>
        </div>
      </div>

      <div class="border rounded p-2 mb-2">
        <div class="fw-semibold mb-1">VIP</div>
        <div class="row g-2">
          <div class="col-7">
            <label class="form-label mb-1 small">Price</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.0001" class="form-control" value="${document.getElementById('priceInput')?.value || 0}">
              <span class="input-group-text">Credits</span>
            </div>
            <div class="small text-muted mt-1">Final: <span class="js-final">${document.getElementById('priceInput')?.value || 0}</span> Credits</div>
          </div>
          <div class="col-5">
            <label class="form-label mb-1 small">Discount</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.0001" class="form-control" value="0">
              <select class="form-select" style="max-width:90px">
                <option>Credits</option>
              </select>
              <button class="btn btn-outline-secondary" type="button">Reset</button>
            </div>
          </div>
        </div>
      </div>

      <div class="border rounded p-2">
        <div class="fw-semibold mb-1">Reseller</div>
        <div class="row g-2">
          <div class="col-7">
            <label class="form-label mb-1 small">Price</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.0001" class="form-control" value="${document.getElementById('priceInput')?.value || 0}">
              <span class="input-group-text">Credits</span>
            </div>
            <div class="small text-muted mt-1">Final: <span class="js-final">${document.getElementById('priceInput')?.value || 0}</span> Credits</div>
          </div>
          <div class="col-5">
            <label class="form-label mb-1 small">Discount</label>
            <div class="input-group input-group-sm">
              <input type="number" step="0.0001" class="form-control" value="0">
              <select class="form-select" style="max-width:90px">
                <option>Credits</option>
              </select>
              <button class="btn btn-outline-secondary" type="button">Reset</button>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  buildGroupsPricing();

  // ========= Apply remote data when modal opened =========
  window.addEventListener('gsmmix:fill-create-modal', (ev) => {
    const d = ev?.detail || {};
    // d: provider_id, provider_name, remote_id, name, credit, time, group_name, additional_fields
    document.getElementById('modalProviderName').innerText = d.provider_name || '—';
    document.getElementById('modalRemoteId').innerText     = d.remote_id || '—';
    document.getElementById('serviceName').value = d.name || '';
    document.getElementById('serviceNameHidden').value = d.name || '';
    document.getElementById('serviceSlug').value = (d.name || '').toLowerCase()
      .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
    document.getElementById('priceInput').value = d.credit ?? 0;
    document.getElementById('costInput').value  = d.credit ?? 0;
    document.getElementById('modalPriceBadge').innerText = `Price: ${(Number(d.credit)||0).toFixed(4)} Credits`;
    document.getElementById('deliveryTime').value = d.time || '';
    document.getElementById('supplierId').value = d.provider_id || '';
    document.getElementById('remoteId').value   = d.remote_id || '';
    document.getElementById('apiConnection').value = d.provider_name || '';
    document.getElementById('apiService').value = `${d.name || ''} | ${d.remote_id || ''}`;

    // set remote additional fields json
    const af = d.additional_fields || '';
    document.getElementById('remoteAdditionalFieldsJson').value = af;

    // build custom fields from remote additional_fields
    customWrap.innerHTML = '';
    const remoteFields = (()=>{
      if(!af) return [];
      try{
        const parsed = JSON.parse(af);
        if(Array.isArray(parsed)) return parsed;
        if(parsed && Array.isArray(parsed.custom_fields)) return parsed.custom_fields;
        if(parsed && Array.isArray(parsed.fields)) return parsed.fields;
        return [];
      }catch(e){
        return [];
      }
    })();

    remoteFields.forEach((f,i)=> addField(f,i));
    if(!remoteFields.length){
      // init empty list
      serializeFieldsInScope();
    }

    // group select: load options (ajax) based on type=server
    const groupSel = document.getElementById('groupSelect');
    if(groupSel && !groupSel.dataset.loaded){
      groupSel.dataset.loaded = '1';
      fetch(`{{ route('admin.services.groups.options') }}?type=server`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(r=>r.json()).then(list=>{
        groupSel.innerHTML = '';
        list.forEach(opt=>{
          const o = document.createElement('option');
          o.value = opt.id;
          o.textContent = opt.name;
          groupSel.appendChild(o);
        });
      }).catch(()=>{});
    }

    // reset to General tab on open
    setActiveTab('general');
    buildGroupsPricing();
  });

})();
</script>
