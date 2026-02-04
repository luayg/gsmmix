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

  $isFileKind = ($kind ?? '') === 'file';

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
            @endphp
            <option
              value="{{ $s->id }}"
              data-name="{{ e($name) }}"
              data-allow-bulk="{{ $allowBulk }}"
              data-group-prices='{{ $gpJson }}'
              data-base-price="{{ $fallback }}"
            >{{ $name }}</option>
          @endforeach
        </select>
      </div>

      {{-- BULK toggle + fields (hidden until service chosen) --}}
      <div class="col-12 js-step-fields d-none">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" id="bulkToggle" name="bulk">
          <label class="form-check-label" for="bulkToggle">Bulk</label>
        </div>
      </div>

      <div class="col-12 js-step-fields d-none" id="singleDeviceWrap">
        @if($isFileKind)
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file" required>
        @else
          <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" placeholder="Enter IMEI / SN">
        @endif
      </div>

      <div class="col-12 js-step-fields d-none" id="bulkDevicesWrap">
        <label class="form-label">Devices (one per line)</label>
        <textarea class="form-control" name="devices" rows="6" placeholder="Enter one IMEI per line"></textarea>
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

  const form = document.getElementById('createOrderForm');
  if (!form) return;

  const userSel     = form.querySelector('.js-step-user');
  const serviceWrap = form.querySelector('.js-step-service');
  const serviceSel  = form.querySelector('.js-service');
  const fieldsWraps = form.querySelectorAll('.js-step-fields');

  const bulkToggle = document.getElementById('bulkToggle');
  const singleWrap = document.getElementById('singleDeviceWrap');
  const bulkWrap   = document.getElementById('bulkDevicesWrap');

  const selectedPriceEl   = document.getElementById('selectedPrice');
  const selectedBalanceEl = document.getElementById('selectedBalance');
  const balanceAfterEl    = document.getElementById('balanceAfter');
  const balanceErrorEl    = document.getElementById('balanceError');
  const btnCreate         = document.getElementById('btnCreateOrder');

  function show(el){ el && el.classList.remove('d-none'); }
  function hide(el){ el && el.classList.add('d-none'); }
  function hideAllFields(){ fieldsWraps.forEach(w => hide(w)); }
  function showAllFields(){ fieldsWraps.forEach(w => show(w)); }

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

    // fallback base price
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

  function updateBulkUI(){
    const allowBulk = serviceAllowBulk();

    if (!allowBulk) {
      bulkToggle.checked = false;
      bulkToggle.disabled = true;
      show(singleWrap);
      hide(bulkWrap);
      return;
    }

    bulkToggle.disabled = false;

    if (bulkToggle.checked) {
      hide(singleWrap);
      show(bulkWrap);
    } else {
      show(singleWrap);
      hide(bulkWrap);
    }
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
  updateSummary();

  userSel.addEventListener('change', function(){
    if (userSel.value) {
      show(serviceWrap);
      updateServiceOptionLabels();
    } else {
      hide(serviceWrap);
      serviceSel.value = '';
      hideAllFields();
    }
    updateSummary();
  });

  serviceSel.addEventListener('change', function(){
    if (serviceSel.value) {
      showAllFields();
      updateBulkUI();
    } else {
      hideAllFields();
    }
    updateSummary();
  });

  bulkToggle.addEventListener('change', function(){
    updateBulkUI();
  });

  form.addEventListener('submit', function(e){
    updateSummary();
    if (btnCreate.disabled) {
      e.preventDefault();
      show(balanceErrorEl);
    }
  });

})();
</script>
