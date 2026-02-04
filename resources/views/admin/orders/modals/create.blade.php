{{-- resources/views/admin/orders/modals/create.blade.php --}}

@php
  use App\Models\ServiceGroupPrice;

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
    $v = is_numeric($v) ? (float)$v : 0.0;
    return '$' . number_format($v, 2);
  };

  // ⬇️ نجهّز group prices لكل خدمة مرة واحدة (للاستخدام بالـ JS)
  $serviceIds = collect($services ?? [])->pluck('id')->filter()->values()->all();

  $gpRows = ServiceGroupPrice::query()
    ->where('service_type', 'imei')
    ->whereIn('service_id', $serviceIds)
    ->get(['service_id','group_id','price','discount','discount_type']);

  $gpMap = [];
  foreach ($gpRows as $r) {
    $sid = (int)$r->service_id;
    $gid = (int)$r->group_id;
    $gpMap[$sid][$gid] = [
      'price' => (float)($r->price ?? 0),
      'discount' => (float)($r->discount ?? 0),
      'discount_type' => (int)($r->discount_type ?? 1), // 1=fixed, 2=percent
    ];
  }

  $isFileKind = ($kind ?? '') === 'file';
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? 'Create order' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.store') }}" enctype="multipart/form-data" id="createOrderForm">
  @csrf

  <div class="modal-body">
    @if ($errors->any())
      <div class="alert alert-danger mb-3">
        <ul class="mb-0">
          @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if(session('err'))
      <div class="alert alert-danger mb-3">{{ session('err') }}</div>
    @endif

    <div class="row g-3">

      {{-- User --}}
      <div class="col-12">
        <label class="form-label">User</label>

        <select class="form-select js-select2 js-user" name="user_id" required data-placeholder="Choose user...">
          <option value=""></option>
          @foreach($users as $u)
            @php
              $bal = is_numeric($u->balance ?? null) ? (float)$u->balance : 0.0;
              $gid = (int)($u->group_id ?? 0);
              $label = $cleanText($u->email) . ' — ' . $fmtMoney($bal);
            @endphp
            <option value="{{ $u->id }}" data-balance="{{ $bal }}" data-group-id="{{ $gid }}">
              {{ $label }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Service (hidden until user selected) --}}
      <div class="col-12 d-none" id="wrapService">
        <label class="form-label">Service</label>

        <select class="form-select js-select2 js-service" name="service_id" required data-placeholder="Search service...">
          <option value=""></option>
          @foreach($services as $s)
            @php
              $sid = (int)$s->id;

              // base price fallback (لو ما في group price)
              $baseSell = null;
              if (isset($s->price) && is_numeric($s->price)) $baseSell = (float)$s->price;
              elseif (isset($s->sell_price) && is_numeric($s->sell_price)) $baseSell = (float)$s->sell_price;

              $cost = is_numeric($s->cost ?? null) ? (float)$s->cost : 0.0;
              $profit = is_numeric($s->profit ?? null) ? (float)$s->profit : 0.0;
              $profitType = (int)($s->profit_type ?? 1); // 1 fixed, 2 percent

              if ($baseSell === null) {
                $baseSell = $profitType === 2 ? ($cost + ($cost * $profit / 100.0)) : ($cost + $profit);
              }

              $name = $pickName($s->name ?? '');
              $groupPricesJson = json_encode($gpMap[$sid] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            @endphp

            <option
              value="{{ $sid }}"
              data-base-price="{{ (float)$baseSell }}"
              data-group-prices='@json($gpMap[$sid] ?? [])'
            >
              {{ $name }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Fields (hidden until service selected) --}}
      <div class="col-12 d-none" id="wrapFields">
        @if($isFileKind)
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file" required>
        @else
          <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" required placeholder="Enter IMEI / SN">
        @endif
      </div>

      @if(!empty($supportsQty))
        <div class="col-12 d-none" id="wrapQty">
          <label class="form-label">Quantity</label>
          <input type="number" class="form-control" name="quantity" min="1" value="1">
        </div>
      @endif

      <div class="col-12 d-none" id="wrapComments">
        <label class="form-label">Comments</label>
        <textarea class="form-control" name="comments" rows="3"></textarea>
      </div>

      {{-- Summary --}}
      <div class="col-12 d-none" id="wrapSummary">
        <div class="alert alert-info mb-0">
          <div class="d-flex flex-wrap gap-3 align-items-center">
            <div><strong>Price:</strong> <span id="selectedPrice">$0.00</span></div>
            <div><strong>Balance:</strong> <span id="selectedBalance">$0.00</span></div>
            <div><strong>After:</strong> <span id="balanceAfter">$0.00</span></div>
          </div>
          <div class="mt-2 text-danger d-none" id="balanceError">
            Insufficient balance
          </div>
        </div>
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
  const form = document.getElementById('createOrderForm');
  if (!form) return;

  const userSel = form.querySelector('.js-user');
  const serviceSel = form.querySelector('.js-service');

  const wrapService  = document.getElementById('wrapService');
  const wrapFields   = document.getElementById('wrapFields');
  const wrapQty      = document.getElementById('wrapQty');
  const wrapComments = document.getElementById('wrapComments');
  const wrapSummary  = document.getElementById('wrapSummary');

  const selectedPriceEl   = document.getElementById('selectedPrice');
  const selectedBalanceEl = document.getElementById('selectedBalance');
  const balanceAfterEl    = document.getElementById('balanceAfter');
  const balanceErrorEl    = document.getElementById('balanceError');
  const btnCreate         = document.getElementById('btnCreateOrder');

  function show(el){ if (el) el.classList.remove('d-none'); }
  function hide(el){ if (el) el.classList.add('d-none'); }

  function money(v){
    v = Number(v || 0);
    return '$' + v.toFixed(2);
  }

  function computeServicePriceForUser(){
    const userOpt = userSel?.selectedOptions?.[0];
    const gid = userOpt ? Number(userOpt.getAttribute('data-group-id') || 0) : 0;

    const svcOpt = serviceSel?.selectedOptions?.[0];
    if (!svcOpt) return 0;

    const base = Number(svcOpt.getAttribute('data-base-price') || 0);
    let gp = {};
    try { gp = JSON.parse(svcOpt.getAttribute('data-group-prices') || '{}'); } catch(e){ gp = {}; }

    // إذا فيه تسعير خاص للجروب
    if (gp && gp[String(gid)]) {
      const row = gp[String(gid)];
      let price = Number(row.price || 0);

      // لو price=0 استخدم base
      if (!price) price = base;

      const discount = Number(row.discount || 0);
      const dt = Number(row.discount_type || 1); // 1 fixed 2 percent
      if (discount > 0) {
        if (dt === 2) price = price - (price * discount / 100.0);
        else price = price - discount;
      }
      if (price < 0) price = 0;
      return price;
    }

    return base;
  }

  function updateServiceOptionText(){
    // لتحديث النص المعروض داخل القائمة بعد اختيار user (حتى تشوف السعر الصحيح)
    const userOpt = userSel?.selectedOptions?.[0];
    const gid = userOpt ? Number(userOpt.getAttribute('data-group-id') || 0) : 0;

    for (const opt of serviceSel.options) {
      if (!opt.value) continue;

      let base = Number(opt.getAttribute('data-base-price') || 0);
      let gp = {};
      try { gp = JSON.parse(opt.getAttribute('data-group-prices') || '{}'); } catch(e){ gp = {}; }

      let price = base;

      if (gp && gp[String(gid)]) {
        const row = gp[String(gid)];
        price = Number(row.price || 0) || base;

        const discount = Number(row.discount || 0);
        const dt = Number(row.discount_type || 1);
        if (discount > 0) {
          if (dt === 2) price = price - (price * discount / 100.0);
          else price = price - discount;
        }
        if (price < 0) price = 0;
      }

      // نحافظ على الاسم الأصلي قبل — إن وجد
      const raw = opt.textContent || '';
      const nameOnly = raw.split('—')[0].trim();
      opt.textContent = nameOnly + ' — ' + money(price);
      opt.setAttribute('data-price-live', String(price));
    }
  }

  function updateSummary(){
    const userOpt = userSel?.selectedOptions?.[0];
    const balance = userOpt ? Number(userOpt.getAttribute('data-balance') || 0) : 0;

    const price = computeServicePriceForUser();

    if (selectedPriceEl) selectedPriceEl.textContent = money(price);
    if (selectedBalanceEl) selectedBalanceEl.textContent = money(balance);
    if (balanceAfterEl) balanceAfterEl.textContent = money(balance - price);

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

  function resetAfterUserChange(){
    serviceSel.value = '';
    hide(wrapFields);
    hide(wrapQty);
    hide(wrapComments);
    hide(wrapSummary);
    btnCreate.disabled = true;
  }

  // Init hidden state
  hide(wrapService);
  hide(wrapFields);
  hide(wrapQty);
  hide(wrapComments);
  hide(wrapSummary);

  // Vanilla handlers
  userSel.addEventListener('change', function(){
    if (userSel.value) {
      show(wrapService);
      updateServiceOptionText();
    } else {
      hide(wrapService);
      resetAfterUserChange();
    }
    updateSummary();
  });

  serviceSel.addEventListener('change', function(){
    if (serviceSel.value) {
      show(wrapFields);
      show(wrapComments);
      show(wrapSummary);
      if (wrapQty) show(wrapQty);
    } else {
      hide(wrapFields);
      hide(wrapQty);
      hide(wrapComments);
      hide(wrapSummary);
    }
    updateSummary();
  });

  // Prevent submit if insufficient
  form.addEventListener('submit', function(e){
    updateSummary();
    if (btnCreate.disabled) {
      e.preventDefault();
      show(balanceErrorEl);
    }
  });

  // Select2 inside ajax modal (must be inline script, not @push)
  function initSelect2(){
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
    const $ = window.jQuery;

    const $modal = $(form).closest('.modal');

    $(userSel).select2({
      dropdownParent: $modal,
      width: '100%',
      placeholder: userSel.getAttribute('data-placeholder') || 'Choose user...',
      allowClear: true
    }).on('change', function(){
      // ensure vanilla change runs too
      userSel.dispatchEvent(new Event('change', { bubbles: true }));
    });

    $(serviceSel).select2({
      dropdownParent: $modal,
      width: '100%',
      placeholder: serviceSel.getAttribute('data-placeholder') || 'Search service...',
      allowClear: true
    }).on('change', function(){
      serviceSel.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  requestAnimationFrame(initSelect2);
})();
</script>
