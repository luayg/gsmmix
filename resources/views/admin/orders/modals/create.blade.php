{{-- resources/views/admin/orders/modals/create.blade.php --}}

@php
  $cleanText = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    return trim($v);
  };

  $pickName = function ($v) use ($cleanText) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && isset($s[0]) && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) $v = $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
    }
    return $cleanText($v);
  };

  $fmtMoney = function ($v) {
    if (!is_numeric($v)) $v = 0;
    return '$' . number_format((float)$v, 2);
  };

  $basePrice = function ($svc) {
    foreach ([
      $svc->price ?? null,
      $svc->sell_price ?? null,
      $svc->final_price ?? null,
      $svc->customer_price ?? null,
      $svc->retail_price ?? null,
    ] as $p) {
      if ($p !== null && $p !== '' && is_numeric($p) && (float)$p > 0) return (float)$p;
    }

    $cost = (float)($svc->cost ?? 0);
    $profit = (float)($svc->profit ?? 0);
    $profitType = (int)($svc->profit_type ?? 1);
    if ($profitType === 2) return max(0.0, $cost + ($cost * ($profit/100)));
    return max(0.0, $cost + $profit);
  };

  $decodeArr = function ($value) {
    if (is_array($value)) return $value;
    if (is_string($value)) {
      $decoded = json_decode($value, true);
      return is_array($decoded) ? $decoded : [];
    }
    return [];
  };

  $serviceMainFieldMeta = function ($svc) use ($decodeArr) {
    $meta = $decodeArr($svc->main_field ?? []);

    $type = strtolower(trim((string)($meta['type'] ?? $svc->main_type ?? '')));
    $label = trim((string)($meta['label'] ?? ''));
    $allowed = strtolower(trim((string)($meta['allowed_characters'] ?? '')));
    $min = isset($meta['minimum']) && is_numeric($meta['minimum']) ? (int)$meta['minimum'] : null;
    $max = isset($meta['maximum']) && is_numeric($meta['maximum']) ? (int)$meta['maximum'] : null;

    if ($type === '') {
      $params = $decodeArr($svc->params ?? []);
      $type = strtolower(trim((string)($params['main_field_type'] ?? '')));
    }

    if ($type === '') {
      $type = 'text';
    }

    $presets = [
      'imei'        => ['label' => 'IMEI',        'allowed' => 'numbers',      'min' => 15, 'max' => 15],
      'serial'      => ['label' => 'IMEI/Serial', 'allowed' => 'any',          'min' => 10, 'max' => 13],
      'imei_serial' => ['label' => 'IMEI/Serial', 'allowed' => 'any',          'min' => 10, 'max' => 15],
      'number'      => ['label' => 'Number',      'allowed' => 'numbers',      'min' => 1,  'max' => 255],
      'email'       => ['label' => 'Email',       'allowed' => 'any',          'min' => 3,  'max' => 255],
      'text'        => ['label' => 'Text',        'allowed' => 'any',          'min' => 1,  'max' => 255],
      'custom'      => ['label' => 'Custom',      'allowed' => 'alphanumeric', 'min' => 1,  'max' => 255],
    ];

    $preset = $presets[$type] ?? $presets['text'];

    return [
      'type' => $type,
      'label' => $label !== '' ? $label : $preset['label'],
      'allowed_characters' => $allowed !== '' ? $allowed : $preset['allowed'],
      'minimum' => $min !== null ? $min : $preset['min'],
      'maximum' => $max !== null ? $max : $preset['max'],
    ];
  };

  $kind = $kind ?? '';
  $isFileKind   = $kind === 'file';
  $isServerKind = $kind === 'server';
  $servicePriceMap = $servicePriceMap ?? [];
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? 'Create order' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="post" action="{{ route($routePrefix.'.store') }}" enctype="multipart/form-data" id="createOrderForm">
  @csrf
  <input type="hidden" name="request_uid" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

  <div class="modal-body">

    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if(session('err'))
      <div class="alert alert-danger">{{ session('err') }}</div>
    @endif

    <div class="row g-3">

      {{-- USER --}}
      <div class="col-12">
        <label class="form-label">User</label>
        <select class="form-select js-step-user" name="user_id" required>
          <option value="">Choose user...</option>
          @foreach($users as $u)
            @php
              $bal = is_numeric($u->balance ?? null) ? (float)$u->balance : 0.0;
              $gid = (int)($u->group_id ?? 0);
              $label = $cleanText($u->email) . ' — ' . $fmtMoney($bal);
            @endphp
            <option value="{{ $u->id }}" data-balance="{{ $bal }}" data-group="{{ $gid }}">
              {{ $label }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- SERVICE --}}
      <div class="col-12 js-step-service d-none">
        <label class="form-label">Service</label>
        <select class="form-select js-service" name="service_id" required>
          <option value="">Choose service...</option>
          @foreach($services as $s)
            @php
              $name = $pickName($s->name);
              $allowBulk = (int)($s->allow_bulk ?? 0);

              $gp = $servicePriceMap[$s->id] ?? [];
              $gpJson = json_encode($gp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

              $fallback = $basePrice($s);

              $params = $decodeArr($s->params ?? []);
              $customFields = $params['custom_fields'] ?? [];
              if (!is_array($customFields)) $customFields = [];
              $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

              $mainMeta = $serviceMainFieldMeta($s);
            @endphp
            <option
              value="{{ $s->id }}"
              data-name="{{ e($name) }}"
              data-allow-bulk="{{ $allowBulk }}"
              data-group-prices='{{ $gpJson }}'
              data-base-price="{{ $fallback }}"
              data-custom-fields='{{ $customFieldsJson }}'
              data-smm-min="{{ (int)($params['smm_limits']['min'] ?? 0) }}"
              data-smm-max="{{ (int)($params['smm_limits']['max'] ?? 0) }}"
              data-main-type="{{ e($mainMeta['type']) }}"
              data-main-label="{{ e($mainMeta['label']) }}"
              data-main-allowed="{{ e($mainMeta['allowed_characters']) }}"
              data-main-min="{{ (int)$mainMeta['minimum'] }}"
              data-main-max="{{ (int)$mainMeta['maximum'] }}"
            >{{ $name }}</option>
          @endforeach
        </select>
      </div>

      <input type="hidden" name="bulk" id="bulkHidden" value="0">

      {{-- SINGLE device / upload --}}
      <div class="col-12 js-step-fields d-none" id="singleDeviceWrap">
        @if($isFileKind)
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file" required>
        @elseif($isServerKind)
          <div class="alert alert-info mb-0">
            Server Orders use <strong>Service fields</strong> only. The standard device/email input is disabled for this order type.
          </div>
        @else
          <label class="form-label" id="deviceLabelText">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" id="deviceInput" placeholder="Enter value" autocomplete="off">
          <div class="form-text" id="deviceHint"></div>
          <div class="invalid-feedback d-block d-none" id="deviceError"></div>
        @endif
      </div>

      {{-- BULK devices --}}
      <div class="col-12 js-step-fields d-none" id="bulkDevicesWrap">
        <label class="form-label">Devices (one per line)</label>
        <textarea class="form-control" name="devices" id="bulkDevicesInput" rows="6" placeholder="Enter one per line"></textarea>
        <div class="form-text" id="bulkDevicesHint"></div>
        <div class="invalid-feedback d-block d-none" id="bulkDevicesError"></div>
      </div>

      {{-- CUSTOM FIELDS --}}
      <div class="col-12 js-step-fields d-none" id="serviceFieldsWrap">
        <label class="form-label fw-semibold">Service fields</label>
        <div class="row g-2" id="serviceFieldsContainer"></div>
        <div class="form-text"></div>
      </div>

      @if(!empty($supportsQty))
      <div class="col-12 js-step-fields d-none" id="quantityWrap">
        <label class="form-label">Quantity</label>
        <input type="number" class="form-control" name="quantity" min="1" value="1">
        <div class="form-text" id="quantityHint"></div>
      </div>
      @endif

      <div class="col-12 js-step-fields d-none">
        <label class="form-label">Comments</label>
        <textarea class="form-control" name="comments" rows="3"></textarea>
      </div>

      <div class="col-12 js-step-fields d-none">
        <div class="d-flex flex-wrap gap-3 align-items-center">
          <div><strong>Price:</strong> <span id="selectedPrice">$0.00</span></div>
          <div><strong>Balance:</strong> <span id="selectedBalance">$0.00</span></div>
          <div><strong>After:</strong> <span id="balanceAfter">$0.00</span></div>
        </div>
        <div class="mt-2 text-danger d-none" id="balanceError">Insufficient balance.</div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success" id="btnCreateOrder" disabled>Create</button>
  </div>
</form>

<script>
(function () {
  function money(v){
    v = Number(v || 0);
    return '$' + v.toFixed(2);
  }

  const kind = @json($kind);
  const isServerKind = kind === 'server';
  const isFileKind   = kind === 'file';

  const form = document.getElementById('createOrderForm');
  if (!form) return;

  const userSel     = form.querySelector('.js-step-user');
  const serviceWrap = form.querySelector('.js-step-service');
  const serviceSel  = form.querySelector('.js-service');
  const fieldsWraps = form.querySelectorAll('.js-step-fields');

  const singleWrap = document.getElementById('singleDeviceWrap');
  const bulkWrap   = document.getElementById('bulkDevicesWrap');
  const bulkHidden = document.getElementById('bulkHidden');

  const fieldsWrap = document.getElementById('serviceFieldsWrap');
  const fieldsBox  = document.getElementById('serviceFieldsContainer');

  const selectedPriceEl   = document.getElementById('selectedPrice');
  const selectedBalanceEl = document.getElementById('selectedBalance');
  const balanceAfterEl    = document.getElementById('balanceAfter');
  const balanceErrorEl    = document.getElementById('balanceError');
  const btnCreate         = document.getElementById('btnCreateOrder');
  const quantityWrap      = document.getElementById('quantityWrap');
  const quantityInput     = form.querySelector('input[name="quantity"]');
  const quantityHint      = document.getElementById('quantityHint');

  const deviceInput       = document.getElementById('deviceInput');
  const deviceLabelText   = document.getElementById('deviceLabelText');
  const deviceHint        = document.getElementById('deviceHint');
  const deviceError       = document.getElementById('deviceError');
  const bulkDevicesInput  = document.getElementById('bulkDevicesInput');
  const bulkDevicesHint   = document.getElementById('bulkDevicesHint');
  const bulkDevicesError  = document.getElementById('bulkDevicesError');

  function show(el){ el && el.classList.remove('d-none'); }
  function hide(el){ el && el.classList.add('d-none'); }
  function hideAllFields(){ fieldsWraps.forEach(w => hide(w)); }
  function showAllFields(){ fieldsWraps.forEach(w => show(w)); }

  function safeJsonParse(s){
    try { return JSON.parse(s); } catch(e){ return null; }
  }

  function selectedServiceOption(){
    return serviceSel && serviceSel.selectedOptions ? serviceSel.selectedOptions[0] : null;
  }

  function userGroupId(){
    const opt = userSel && userSel.selectedOptions ? userSel.selectedOptions[0] : null;
    return opt ? Number(opt.getAttribute('data-group') || 0) : 0;
  }

  function userBalance(){
    const opt = userSel && userSel.selectedOptions ? userSel.selectedOptions[0] : null;
    return opt ? Number(opt.getAttribute('data-balance') || 0) : 0;
  }

  function serviceAllowBulk(){
    const opt = selectedServiceOption();
    return opt ? Number(opt.getAttribute('data-allow-bulk') || 0) : 0;
  }

  function servicePriceForUser(){
    const opt = selectedServiceOption();
    if (!opt) return 0;

    const gid = userGroupId();
    let gp = {};
    try { gp = JSON.parse(opt.getAttribute('data-group-prices') || '{}'); } catch(e){ gp = {}; }

    let price = gp[String(gid)];
    if (typeof price === 'string') price = Number(price);
    if (typeof price === 'number' && !isNaN(price) && price > 0) return price;

    const base = Number(opt.getAttribute('data-base-price') || 0);
    return (!isNaN(base) && base > 0) ? base : 0;
  }

  function getMainMeta(){
    const opt = selectedServiceOption();
    if (!opt) {
      return {
        type: 'text',
        label: 'Device',
        allowed: 'any',
        min: 1,
        max: 255
      };
    }

    return {
      type: String(opt.getAttribute('data-main-type') || 'text').toLowerCase().trim(),
      label: String(opt.getAttribute('data-main-label') || 'Device').trim(),
      allowed: String(opt.getAttribute('data-main-allowed') || 'any').toLowerCase().trim(),
      min: Number(opt.getAttribute('data-main-min') || 1),
      max: Number(opt.getAttribute('data-main-max') || 255)
    };
  }

  function validateAllowedCharacters(value, allowed){
    if (allowed === 'numbers' || allowed === 'numeric') {
      return /^\d+$/.test(value);
    }
    if (allowed === 'alphanumeric') {
      return /^[A-Za-z0-9]+$/.test(value);
    }
    return true;
  }

  function validateSingleDevice(value, meta){
    value = String(value || '').trim();
    if (!value) {
      return { ok: false, message: 'Value is required.' };
    }

    const type = String(meta.type || 'text').toLowerCase().trim();
    const min  = Number(meta.min || 0);
    const max  = Number(meta.max || 0);
    const allowed = String(meta.allowed || 'any').toLowerCase().trim();
    const len = value.length;

    if (type === 'email') {
      const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
      if (!ok) return { ok: false, message: 'Email format is invalid.' };
      if (min > 0 && len < min) return { ok: false, message: 'Email must be at least ' + min + ' characters.' };
      if (max > 0 && len > max) return { ok: false, message: 'Email must be at most ' + max + ' characters.' };
      return { ok: true, message: '' };
    }

    if (type === 'imei') {
      if (!/^\d+$/.test(value)) {
        return { ok: false, message: 'IMEI must contain numbers only.' };
      }
      if (len !== 15) {
        return { ok: false, message: 'IMEI must be exactly 15 digits.' };
      }
      return { ok: true, message: '' };
    }

    if (type === 'serial') {
      if (allowed !== 'any' && !validateAllowedCharacters(value, allowed)) {
        return { ok: false, message: 'Serial contains invalid characters.' };
      }
      if (len < 10) return { ok: false, message: 'Serial must be at least 10 characters.' };
      if (len > 13) return { ok: false, message: 'Serial must be at most 13 characters.' };
      return { ok: true, message: '' };
    }

    if (type === 'imei_serial') {
      if (/^\d{15}$/.test(value)) {
        return { ok: true, message: '' };
      }

      if (len >= 10 && len <= 13) {
        if (allowed !== 'any' && !validateAllowedCharacters(value, allowed)) {
          return { ok: false, message: 'IMEI/Serial contains invalid characters.' };
        }
        return { ok: true, message: '' };
      }

      return { ok: false, message: 'Value must be either a 15-digit IMEI or a Serial between 10 and 13 characters.' };
    }

    if (min > 0 && len < min) {
      return { ok: false, message: 'Value must be at least ' + min + ' characters.' };
    }

    if (max > 0 && len > max) {
      return { ok: false, message: 'Value must be at most ' + max + ' characters.' };
    }

    if (!validateAllowedCharacters(value, allowed)) {
      return { ok: false, message: 'Value contains invalid characters.' };
    }

    return { ok: true, message: '' };
  }

  function setFieldError(el, message){
    if (!el) return;
    if (message) {
      el.textContent = message;
      show(el);
    } else {
      el.textContent = '';
      hide(el);
    }
  }

  function buildMainHint(meta){
    const type = String(meta.type || 'text');
    if (type === 'imei') return 'IMEI must be exactly 15 digits.';
    if (type === 'serial') return 'Serial must be between 10 and 13 characters.';
    if (type === 'imei_serial') return 'Allowed: IMEI = 15 digits OR Serial = 10 to 13 characters.';
    if (type === 'email') return 'Please enter a valid email address.';
    if (type === 'number') return 'Numbers only.';
    return 'Allowed length: ' + meta.min + ' - ' + meta.max + ' characters.';
  }

  function updateMainFieldUI(){
    if (isFileKind || isServerKind || !deviceInput) return;

    const meta = getMainMeta();

    if (deviceLabelText) {
      deviceLabelText.textContent = meta.label || 'Device';
    }

    if (meta.type === 'email') {
      deviceInput.type = 'email';
      deviceInput.inputMode = 'email';
      deviceInput.placeholder = 'Enter email';
      deviceInput.removeAttribute('pattern');
      deviceInput.removeAttribute('maxlength');
    } else if (meta.type === 'imei') {
      deviceInput.type = 'text';
      deviceInput.inputMode = 'numeric';
      deviceInput.placeholder = 'Enter 15-digit IMEI';
      deviceInput.setAttribute('maxlength', '15');
      deviceInput.setAttribute('pattern', '\\d{15}');
    } else if (meta.type === 'serial') {
      deviceInput.type = 'text';
      deviceInput.inputMode = 'text';
      deviceInput.placeholder = 'Enter Serial (10-13 chars)';
      deviceInput.setAttribute('maxlength', '13');
      deviceInput.removeAttribute('pattern');
    } else if (meta.type === 'imei_serial') {
      deviceInput.type = 'text';
      deviceInput.inputMode = 'text';
      deviceInput.placeholder = 'Enter IMEI (15) or Serial (10-13)';
      deviceInput.setAttribute('maxlength', '15');
      deviceInput.removeAttribute('pattern');
    } else if (meta.type === 'number') {
      deviceInput.type = 'text';
      deviceInput.inputMode = 'numeric';
      deviceInput.placeholder = 'Enter number';
      deviceInput.removeAttribute('pattern');
      deviceInput.setAttribute('maxlength', String(meta.max || 255));
    } else {
      deviceInput.type = 'text';
      deviceInput.inputMode = 'text';
      deviceInput.placeholder = 'Enter value';
      deviceInput.removeAttribute('pattern');
      deviceInput.setAttribute('maxlength', String(meta.max || 255));
    }

    if (deviceHint) {
      deviceHint.textContent = buildMainHint(meta);
    }

    if (bulkDevicesHint) {
      bulkDevicesHint.textContent = buildMainHint(meta) + ' One value per line.';
    }

    validateMainDeviceInputs();
  }

  function validateMainDeviceInputs(){
    if (isFileKind || isServerKind) return true;

    const meta = getMainMeta();
    const bulkMode = bulkHidden && bulkHidden.value === '1';

    let ok = true;

    if (bulkMode) {
      const raw = bulkDevicesInput ? String(bulkDevicesInput.value || '') : '';
      const lines = raw.split(/\r\n|\n|\r/).map(v => v.trim()).filter(Boolean);

      if (lines.length < 1) {
        setFieldError(bulkDevicesError, 'Bulk list is empty.');
        ok = false;
      } else if (lines.length > 200) {
        setFieldError(bulkDevicesError, 'Too many lines (max 200).');
        ok = false;
      } else {
        let err = '';
        lines.some((line, idx) => {
          const res = validateSingleDevice(line, meta);
          if (!res.ok) {
            err = 'Line ' + (idx + 1) + ': ' + res.message;
            return true;
          }
          return false;
        });
        setFieldError(bulkDevicesError, err);
        ok = err === '';
      }

      setFieldError(deviceError, '');
    } else {
      const value = deviceInput ? String(deviceInput.value || '').trim() : '';
      const res = validateSingleDevice(value, meta);
      setFieldError(deviceError, res.ok ? '' : res.message);
      setFieldError(bulkDevicesError, '');
      ok = res.ok;
    }

    return ok;
  }

  function updateServiceOptionLabels(){
    const gid = userGroupId();
    Array.from(serviceSel.options).forEach(opt => {
      if (!opt.value) return;
      const name = opt.getAttribute('data-name') || opt.textContent || '';

      let gp = {};
      try { gp = JSON.parse(opt.getAttribute('data-group-prices') || '{}'); } catch(e){ gp = {}; }

      let price = gp[String(gid)];
      if (typeof price === 'string') price = Number(price);

      if (!(typeof price === 'number' && !isNaN(price) && price > 0)) {
        const base = Number(opt.getAttribute('data-base-price') || 0);
        price = (!isNaN(base) && base > 0) ? base : 0;
      }

      opt.textContent = name + ' — ' + money(price);
    });
  }

  function applyBulkModeByService(){
    if (isFileKind) {
      if (bulkHidden) bulkHidden.value = '0';
      show(singleWrap);
      hide(bulkWrap);
      return;
    }

    if (isServerKind) {
      if (bulkHidden) bulkHidden.value = '0';
      hide(singleWrap);
      hide(bulkWrap);
      return;
    }

    const allowBulk = serviceAllowBulk();

    if (allowBulk) {
      if (bulkHidden) bulkHidden.value = '1';
      hide(singleWrap);
      show(bulkWrap);

      if (deviceInput) deviceInput.value = '';
    } else {
      if (bulkHidden) bulkHidden.value = '0';
      show(singleWrap);
      hide(bulkWrap);

      if (bulkDevicesInput) bulkDevicesInput.value = '';
    }

    updateMainFieldUI();
  }

  function renderCustomFields(){
    if (!fieldsWrap || !fieldsBox) return;

    fieldsBox.innerHTML = '';

    const opt = selectedServiceOption();
    if (!opt || !opt.value) {
      hide(fieldsWrap);
      return;
    }

    let cf = safeJsonParse(opt.getAttribute('data-custom-fields') || '[]');
    if (!Array.isArray(cf)) cf = [];

    const hasQtyField = cf.some(f => String((f && f.input) || '').toLowerCase().trim() === 'quantity');
    const hasTargetField = cf.some(f => {
      const input = String((f && f.input) || '').toLowerCase().trim();
      return ['link', 'username', 'usernames', 'target'].includes(input);
    });

    if (kind === 'smm') {
      if (hasTargetField) {
        hide(singleWrap);
        if (deviceInput) deviceInput.value = '';
      } else {
        show(singleWrap);
      }
    }

    if (quantityWrap && kind === 'smm') {
      if (hasQtyField) hide(quantityWrap);
      else show(quantityWrap);
    }

    if (cf.length === 0) {
      hide(fieldsWrap);
      return;
    }

    show(fieldsWrap);

    const splitOptions = (raw) => {
      if (!raw) return [];
      const s = String(raw).trim();
      if (!s) return [];
      const j = safeJsonParse(s);
      if (Array.isArray(j)) return j.map(x => String(x));
      if (s.includes('\n')) return s.split('\n').map(x => x.trim()).filter(Boolean);
      if (s.includes('|'))  return s.split('|').map(x => x.trim()).filter(Boolean);
      if (s.includes(','))  return s.split(',').map(x => x.trim()).filter(Boolean);
      return [s];
    };

    cf.forEach((f) => {
      if (!f) return;
      if (String(f.active ?? 1) !== '1') return;

      const input = String(f.input || '').trim();
      if (!input) return;

      const name = String(f.name || input).trim();
      const type = String(f.type || 'text').toLowerCase();
      const req  = String(f.required || 0) === '1';
      const desc = String(f.description || '').trim();

      const col = document.createElement('div');
      col.className = 'col-md-6';

      const label = document.createElement('label');
      label.className = 'form-label';
      label.textContent = name + (req ? ' *' : '');
      col.appendChild(label);

      let control;

      if (type === 'textarea') {
        control = document.createElement('textarea');
        control.rows = 3;
      } else if (type === 'select') {
        control = document.createElement('select');
        const def = document.createElement('option');
        def.value = '';
        def.textContent = 'Choose...';
        control.appendChild(def);

        const opts = splitOptions(f.options || '');
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
                     : (type === 'password') ? 'password'
                     : 'text';
      }

      control.className = 'form-control';
      control.name = 'required[' + input + ']';
      if (req) control.required = true;

      const min = parseInt(f.minimum ?? 0, 10);
      const max = parseInt(f.maximum ?? 0, 10);
      if (control.type === 'number') {
        if (!isNaN(min) && min > 0) control.min = String(min);
        if (!isNaN(max) && max > 0) control.max = String(max);
      }

      col.appendChild(control);

      if (desc) {
        const small = document.createElement('div');
        small.className = 'form-text';
        small.textContent = desc;
        col.appendChild(small);
      }

      fieldsBox.appendChild(col);
    });
  }

  function syncQuantityLimits(){
    if (!quantityInput) return;

    const opt = selectedServiceOption();
    if (!opt || !opt.value) {
      quantityInput.min = '1';
      quantityInput.removeAttribute('max');
      if (quantityHint) quantityHint.textContent = '';
      return;
    }

    const min = Number(opt.getAttribute('data-smm-min') || 0);
    const max = Number(opt.getAttribute('data-smm-max') || 0);

    if (!isNaN(min) && min > 0) {
      quantityInput.min = String(min);
      if (Number(quantityInput.value || 0) < min) quantityInput.value = String(min);
    } else {
      quantityInput.min = '1';
    }

    if (!isNaN(max) && max > 0) {
      quantityInput.max = String(max);
      if (Number(quantityInput.value || 0) > max) quantityInput.value = String(max);
    } else {
      quantityInput.removeAttribute('max');
    }

    if (quantityHint) {
      quantityHint.textContent = (min > 0 || max > 0)
        ? ('Allowed quantity: ' + (min > 0 ? min : '?') + ' - ' + (max > 0 ? max : '?'))
        : '';
    }
  }

  function updateSummary(){
    const balance = userBalance();
    const price   = servicePriceForUser();

    selectedPriceEl.textContent   = money(price);
    selectedBalanceEl.textContent = money(balance);
    balanceAfterEl.textContent    = money(balance - price);

    const deviceOk = validateMainDeviceInputs();
    const ok = (userSel.value && serviceSel.value && price > 0 && balance >= price && deviceOk);

    if (ok) {
      hide(balanceErrorEl);
      btnCreate.disabled = false;
    } else {
      if (userSel.value && serviceSel.value && price > 0 && balance < price) show(balanceErrorEl);
      else hide(balanceErrorEl);
      btnCreate.disabled = true;
    }
  }

  function lockVisualOnly(){
    btnCreate.disabled = true;
    btnCreate.setAttribute('aria-disabled', 'true');
    btnCreate.textContent = 'Creating...';
  }

  hide(serviceWrap);
  hideAllFields();
  if (bulkHidden) bulkHidden.value = '0';
  updateSummary();

  userSel.addEventListener('change', function(){
    if (userSel.value) {
      show(serviceWrap);
      updateServiceOptionLabels();
    } else {
      hide(serviceWrap);
      serviceSel.value = '';
      hideAllFields();
      if (bulkHidden) bulkHidden.value = '0';
      if (fieldsWrap) hide(fieldsWrap);
      if (fieldsBox) fieldsBox.innerHTML = '';
    }
    updateSummary();
  });

  serviceSel.addEventListener('change', function(){
    if (serviceSel.value) {
      showAllFields();
      applyBulkModeByService();
      syncQuantityLimits();
      renderCustomFields();
      updateMainFieldUI();
    } else {
      hideAllFields();
      if (bulkHidden) bulkHidden.value = '0';
      if (fieldsWrap) hide(fieldsWrap);
      if (fieldsBox) fieldsBox.innerHTML = '';
      syncQuantityLimits();
      updateMainFieldUI();
    }
    updateSummary();
  });

  if (deviceInput) {
    deviceInput.addEventListener('input', function(){
      validateMainDeviceInputs();
      updateSummary();
    });
  }

  if (bulkDevicesInput) {
    bulkDevicesInput.addEventListener('input', function(){
      validateMainDeviceInputs();
      updateSummary();
    });
  }

  form.addEventListener('input', function(){
    updateSummary();
  });

  form.addEventListener('submit', function(e){
    updateSummary();

    const deviceOk = validateMainDeviceInputs();
    if (!deviceOk) {
      e.preventDefault();
      return false;
    }

    if (btnCreate.disabled) {
      e.preventDefault();
      show(balanceErrorEl);
      return false;
    }

    lockVisualOnly();
  });

})();
</script>