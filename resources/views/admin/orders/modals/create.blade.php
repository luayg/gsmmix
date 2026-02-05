{{-- resources/views/admin/orders/modals/create.blade.php --}}
@php
  $kind        = $kind ?? 'imei';
  $routePrefix = $routePrefix ?? 'admin.orders.imei';
  $supportsQty = (bool)($supportsQty ?? false);
  $deviceLabel = $deviceLabel ?? 'Device';
@endphp

<div class="modal-header" style="background:#198754;color:#fff;">
  <div class="d-flex align-items-center gap-2">
    <strong>{{ $title ?? 'Create Order' }}</strong>
  </div>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route($routePrefix.'.store') }}" enctype="multipart/form-data">
  @csrf

  <div class="modal-body" style="max-height: calc(100vh - 210px); overflow:auto;">
    <div class="row g-3">

      {{-- USER --}}
      <div class="col-12">
        <label class="form-label">User</label>
        <select name="user_id" class="form-select" required>
          <option value="">-- Select user --</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}">{{ $u->email }} (ID: {{ $u->id }})</option>
          @endforeach
        </select>
      </div>

      {{-- SERVICE --}}
      <div class="col-12">
        <label class="form-label">Service</label>
        <select name="service_id" id="jsServiceSelect" class="form-select" required>
          <option value="">-- Select service --</option>
          @foreach($services as $s)
            @php
              // params may be array (cast) or json string
              $p = $s->params;
              if (is_string($p)) $p = json_decode($p, true) ?: [];
              if (!is_array($p)) $p = [];

              $customFields = $p['custom_fields'] ?? [];
              if (!is_array($customFields)) $customFields = [];

              $deviceBased = (int)($s->device_based ?? 0);
            @endphp
            <option
              value="{{ $s->id }}"
              data-device-based="{{ $deviceBased }}"
              data-custom-fields='@json($customFields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)'
            >
              #{{ $s->id }} -
              {{ is_array($s->name) ? ($s->name['en'] ?? $s->name['fallback'] ?? 'Service') : $s->name }}
            </option>
          @endforeach
        </select>
        <div class="form-text">
          Server services will show custom fields automatically.
        </div>
      </div>

      {{-- DEVICE (IMEI/SN) OR OPTIONAL FOR SERVER --}}
      @if($kind !== 'file')
        <div class="col-12" id="jsDeviceWrap">
          <label class="form-label">{{ $deviceLabel }}</label>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" id="jsBulkSwitch" name="bulk" value="1">
            <label class="form-check-label" for="jsBulkSwitch">Bulk</label>
          </div>

          <input type="text" name="device" id="jsDeviceInput" class="form-control" placeholder="Enter {{ $deviceLabel }}">

          <textarea name="devices" id="jsDevicesTextarea" class="form-control d-none mt-2" rows="6"
            placeholder="One item per line"></textarea>

          <div class="form-text" id="jsDeviceHint">
            For Server (fields-based) device may be optional unless the service is device-based.
          </div>
        </div>
      @else
        <div class="col-12">
          <label class="form-label">File</label>
          <input type="file" name="file" class="form-control" required>
        </div>
      @endif

      {{-- QUANTITY --}}
      @if($supportsQty)
        <div class="col-md-4">
          <label class="form-label">Quantity</label>
          <input type="number" name="quantity" class="form-control" min="1" max="999" value="1">
        </div>
      @endif

      {{-- COMMENTS --}}
      <div class="col-12">
        <label class="form-label">Comments</label>
        <textarea name="comments" class="form-control" rows="3" placeholder="Optional comments..."></textarea>
      </div>

      {{-- SERVER CUSTOM FIELDS --}}
      <div class="col-12 {{ $kind === 'server' ? '' : 'd-none' }}" id="jsServerFieldsWrap">
        <label class="form-label fw-semibold">Service Fields</label>
        <div class="row g-2" id="jsServerFieldsContainer"></div>
        <div class="form-text">
          These fields come from <code>server_services.params.custom_fields</code> and will be sent as REQUIRED JSON.
        </div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>

<script>
(function () {
  const kind = @json($kind);

  const serviceSelect = document.getElementById('jsServiceSelect');
  const bulkSwitch    = document.getElementById('jsBulkSwitch');
  const deviceInput   = document.getElementById('jsDeviceInput');
  const devicesTa     = document.getElementById('jsDevicesTextarea');

  const serverWrap = document.getElementById('jsServerFieldsWrap');
  const serverBox  = document.getElementById('jsServerFieldsContainer');

  function safeJsonParse(s) {
    try { return JSON.parse(s); } catch(e) { return null; }
  }

  function splitOptions(raw) {
    if (!raw) return [];
    const s = String(raw).trim();
    if (!s) return [];
    // allow JSON array
    const j = safeJsonParse(s);
    if (Array.isArray(j)) return j.map(x => String(x));
    // allow newline or | or comma
    if (s.includes('\n')) return s.split('\n').map(x => x.trim()).filter(Boolean);
    if (s.includes('|'))  return s.split('|').map(x => x.trim()).filter(Boolean);
    if (s.includes(','))  return s.split(',').map(x => x.trim()).filter(Boolean);
    return [s];
  }

  function renderServerFields(customFields) {
    serverBox.innerHTML = '';

    if (!Array.isArray(customFields) || customFields.length === 0) {
      serverBox.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">No custom fields for this service.</div></div>';
      return;
    }

    customFields.forEach((f, idx) => {
      if (!f || String(f.active ?? 1) !== '1') return;

      const input = String(f.input || '').trim();
      if (!input) return;

      const label = String(f.name || input);
      const type  = String(f.type || 'text').toLowerCase();
      const req   = String(f.required || 0) === '1';

      const col = document.createElement('div');
      col.className = 'col-md-6';

      const lab = document.createElement('label');
      lab.className = 'form-label';
      lab.textContent = label + (req ? ' *' : '');

      let control;

      if (type === 'textarea') {
        control = document.createElement('textarea');
        control.rows = 3;
      } else if (type === 'select') {
        control = document.createElement('select');
        const opts = splitOptions(f.options || '');
        const def = document.createElement('option');
        def.value = '';
        def.textContent = '-- Select --';
        control.appendChild(def);

        opts.forEach(o => {
          const op = document.createElement('option');
          op.value = o;
          op.textContent = o;
          control.appendChild(op);
        });
      } else {
        control = document.createElement('input');
        control.type = (type === 'email') ? 'email'
                     : (type === 'number') ? 'number'
                     : 'text';
      }

      control.className = 'form-control';
      control.name = 'required[' + input + ']';
      if (req) control.required = true;

      // min/max (optional)
      const min = parseInt(f.minimum ?? 0, 10);
      const max = parseInt(f.maximum ?? 0, 10);
      if (!isNaN(min) && min > 0 && control.type === 'number') control.min = String(min);
      if (!isNaN(max) && max > 0 && control.type === 'number') control.max = String(max);

      col.appendChild(lab);
      col.appendChild(control);

      // description
      const desc = String(f.description || '').trim();
      if (desc) {
        const small = document.createElement('div');
        small.className = 'form-text';
        small.textContent = desc;
        col.appendChild(small);
      }

      serverBox.appendChild(col);
    });
  }

  function applyServiceSelection() {
    const opt = serviceSelect?.options[serviceSelect.selectedIndex];
    if (!opt) return;

    const deviceBased = String(opt.getAttribute('data-device-based') || '0') === '1';
    const cfRaw = opt.getAttribute('data-custom-fields') || '[]';
    const customFields = safeJsonParse(cfRaw) || [];

    // Bulk UI toggling
    if (bulkSwitch && deviceInput && devicesTa) {
      // server + fields-based: hide bulk switch and keep device optional
      if (kind === 'server' && !deviceBased) {
        bulkSwitch.checked = false;
        bulkSwitch.disabled = true;
        devicesTa.classList.add('d-none');
        deviceInput.classList.remove('d-none');
      } else {
        bulkSwitch.disabled = false;
      }
    }

    // Render server fields
    if (kind === 'server' && serverWrap) {
      serverWrap.classList.remove('d-none');
      renderServerFields(customFields);
    }
  }

  if (bulkSwitch && deviceInput && devicesTa) {
    bulkSwitch.addEventListener('change', function () {
      const isBulk = bulkSwitch.checked;
      if (isBulk) {
        deviceInput.classList.add('d-none');
        devicesTa.classList.remove('d-none');
      } else {
        devicesTa.classList.add('d-none');
        deviceInput.classList.remove('d-none');
      }
    });
  }

  if (serviceSelect) {
    serviceSelect.addEventListener('change', applyServiceSelection);
    // init
    applyServiceSelection();
  }
})();
</script>
