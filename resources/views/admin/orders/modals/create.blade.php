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

  // ✅ fallback base price from service columns if group map missing
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
    $profitType = (int)($svc->profit_type ?? 1); // 1 fixed, 2 percent
    if ($profitType === 2) return max(0.0, $cost + ($cost * ($profit/100)));
    return max(0.0, $cost + $profit);
  };

  $kind = $kind ?? '';
  $isFileKind   = $kind === 'file';

  // ✅ Important: this must be passed from controller, otherwise fallback works (base price only)
  $servicePriceMap = $servicePriceMap ?? [];
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? 'Create order' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.store') }}" enctype="multipart/form-data" id="createOrderForm">
  @csrf

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

      {{-- SERVICE (hidden until user chosen) --}}
      <div class="col-12 js-step-service d-none">
        <label class="form-label">Service</label>
        <select class="form-select js-service" name="service_id" required>
          <option value="">Choose service...</option>
          @foreach($services as $s)
            @php
              $name = $pickName($s->name);
              $allowBulk = (int)($s->allow_bulk ?? 0);

              // group prices map: [group_id => final_price]
              $gp = $servicePriceMap[$s->id] ?? [];
              $gpJson = json_encode($gp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

              $fallback = $basePrice($s);

              // ✅ Custom fields (now should come from DB injected into params.custom_fields)
              $params = $s->params ?? [];
              if (is_string($params)) $params = json_decode($params, true) ?: [];
              if (!is_array($params)) $params = [];
              $customFields = $params['custom_fields'] ?? [];
              if (!is_array($customFields)) $customFields = [];
              $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            @endphp
            <option
              value="{{ $s->id }}"
              data-name="{{ e($name) }}"
              data-allow-bulk="{{ $allowBulk }}"
              data-group-prices='{{ $gpJson }}'
              data-base-price="{{ $fallback }}"
              data-custom-fields='{{ $customFieldsJson }}'
            >{{ $name }}</option>
          @endforeach
        </select>
      </div>

      {{-- ✅ bulk hidden input (we set it automatically based on service allow_bulk) --}}
      <input type="hidden" name="bulk" id="bulkHidden" value="0">

      {{-- SINGLE device / upload --}}
      <div class="col-12 js-step-fields d-none" id="singleDeviceWrap">
        @if($isFileKind)
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file" required>
        @else
          <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" placeholder="Enter value">
          <div class="form-text">
            Depending on service type, this may be required (IMEI/SN) or optional (field-based service).
          </div>
        @endif
      </div>

      {{-- BULK devices --}}
      <div class="col-12 js-step-fields d-none" id="bulkDevicesWrap">
        <label class="form-label">Devices (one per line)</label>
        <textarea class="form-control" name="devices" rows="6" placeholder="Enter one per line"></textarea>
      </div>

      {{-- ✅ CUSTOM FIELDS (for ALL kinds: imei/server/file) --}}
      <div class="col-12 js-step-fields d-none" id="serviceFieldsWrap">
        <label class="form-label fw-semibold">Service fields</label>
        <div class="row g-2" id="serviceFieldsContainer"></div>
        <div class="form-text">
          These fields are loaded from <code>custom_fields</code> table and will be sent as
          <code>required[input]</code> to the provider (DHRU: <code>&lt;REQUIRED&gt;...&lt;/REQUIRED&gt;</code>).
        </div>
      </div>

      @if(!empty($supportsQty))
      <div class="col-12 js-step-fields d-none">
        <label class="form-label">Quantity</label>
        <input type="number" class="form-control" name="quantity" min="1" value="1">
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

  const form = document.getElementById('createOrderForm');
  if (!form) return;

  const userSel     = form.querySelector('.js-step-user');
  const serviceWrap = form.querySelector('.js-step-service');
  const serviceSel  = form.querySelector('.js-service');
  const fieldsWraps = form.querySelectorAll('.js-step-fields');

  const singleWrap = document.getElementById('singleDeviceWrap');
  const bulkWrap   = document.getElementById('bulkDevicesWrap');
  const bulkHidden = document.getElementById('bulkHidden');

  // ✅ custom fields (all kinds)
  const fieldsWrap = document.getElementById('serviceFieldsWrap');
  const fieldsBox  = document.getElementById('serviceFieldsContainer');

  const selectedPriceEl   = document.getElementById('selectedPrice');
  const selectedBalanceEl = document.getElementById('selectedBalance');
  const balanceAfterEl    = document.getElementById('balanceAfter');
  const balanceErrorEl    = document.getElementById('balanceError');
  const btnCreate         = document.getElementById('btnCreateOrder');

  function show(el){ el && el.classList.remove('d-none'); }
  function hide(el){ el && el.classList.add('d-none'); }
  function hideAllFields(){ fieldsWraps.forEach(w => hide(w)); }
  function showAllFields(){ fieldsWraps.forEach(w => show(w)); }

  function safeJsonParse(s){
    try { return JSON.parse(s); } catch(e){ return null; }
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
    const opt = serviceSel && serviceSel.selectedOptions ? serviceSel.selectedOptions[0] : null;
    return opt ? Number(opt.getAttribute('data-allow-bulk') || 0) : 0;
  }

  function servicePriceForUser(){
    const opt = serviceSel && serviceSel.selectedOptions ? serviceSel.selectedOptions[0] : null;
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

  // ✅ no checkbox: if service allow_bulk=1 show bulk textarea directly, else show single input
  function applyBulkModeByService(){
    const allowBulk = serviceAllowBulk();

    if (allowBulk) {
      if (bulkHidden) bulkHidden.value = '1';
      hide(singleWrap);
      show(bulkWrap);

      // clear single device
      const deviceInput = singleWrap ? singleWrap.querySelector('input[name="device"]') : null;
      if (deviceInput) deviceInput.value = '';
    } else {
      if (bulkHidden) bulkHidden.value = '0';
      show(singleWrap);
      hide(bulkWrap);

      // clear bulk textarea
      const bulkTa = bulkWrap ? bulkWrap.querySelector('textarea[name="devices"]') : null;
      if (bulkTa) bulkTa.value = '';
    }
  }

  // ✅ CUSTOM FIELDS: render dynamic custom fields -> required[input]
  function renderCustomFields(){
    if (!fieldsWrap || !fieldsBox) return;

    fieldsBox.innerHTML = '';

    const opt = serviceSel && serviceSel.selectedOptions ? serviceSel.selectedOptions[0] : null;
    if (!opt || !opt.value) {
      hide(fieldsWrap);
      return;
    }

    let cf = safeJsonParse(opt.getAttribute('data-custom-fields') || '[]');
    if (!Array.isArray(cf)) cf = [];

    // show wrap always when service selected (even if empty -> show warning)
    show(fieldsWrap);

    if (cf.length === 0) {
      fieldsBox.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">No custom fields for this service.</div></div>';
      return;
    }

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

      // min/max (optional)
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

  function updateSummary(){
    const balance = userBalance();
    const price   = servicePriceForUser();

    selectedPriceEl.textContent   = money(price);
    selectedBalanceEl.textContent = money(balance);
    balanceAfterEl.textContent    = money(balance - price);

    const ok = (userSel.value && serviceSel.value && price > 0 && balance >= price);

    if (ok) {
      hide(balanceErrorEl);
      btnCreate.disabled = false;
    } else {
      if (userSel.value && serviceSel.value && price > 0 && balance < price) show(balanceErrorEl);
      else hide(balanceErrorEl);
      btnCreate.disabled = true;
    }
  }

  // Initial state
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
      renderCustomFields();
    } else {
      hideAllFields();
      if (bulkHidden) bulkHidden.value = '0';
      if (fieldsWrap) hide(fieldsWrap);
      if (fieldsBox) fieldsBox.innerHTML = '';
    }
    updateSummary();
  });

  form.addEventListener('submit', function(e){
    updateSummary();
    // HTML required fields will validate automatically.
    if (btnCreate.disabled) {
      e.preventDefault();
      show(balanceErrorEl);
    }
  });

})();
</script>
