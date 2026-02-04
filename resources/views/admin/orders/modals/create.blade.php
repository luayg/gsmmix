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

  // ✅ اختيار سعر البيع الحقيقي من عدة أعمدة محتملة (عدّل/أضف أسماء أعمدة لو عندك أعمدة أخرى)
  $pickSellPrice = function ($svc) {
    $candidates = [
      $svc->sell_price ?? null,
      $svc->price ?? null,
      $svc->final_price ?? null,
      $svc->customer_price ?? null,
      $svc->retail_price ?? null,
      $svc->user_price ?? null,
      $svc->reseller_price ?? null,
    ];
    foreach ($candidates as $p) {
      if ($p !== null && $p !== '' && is_numeric($p)) return (float)$p;
    }
    return 0.0;
  };

  $fmtMoney = function ($v) {
    $v = is_numeric($v) ? (float)$v : 0.0;
    return '$' . number_format($v, 2);
  };

  $isFileKind = (($kind ?? '') === 'file');
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
      <div class="alert alert-danger mb-2">{{ session('err') }}</div>
    @endif

    <div class="row g-3">

      {{-- User (always visible) --}}
      <div class="col-12">
        <label class="form-label">User</label>

        <select class="form-select js-select2 js-user" name="user_id" required data-placeholder="Choose user...">
          <option value=""></option>
          @foreach($users as $u)
            @php
              $bal = is_numeric($u->balance ?? null) ? (float)$u->balance : 0.0;
              $label = $cleanText($u->email) . ' — ' . $fmtMoney($bal);
            @endphp
            <option value="{{ $u->id }}" data-balance="{{ $bal }}">{{ $label }}</option>
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
              $name  = $pickName($s->name);
              $price = $pickSellPrice($s);
            @endphp
            <option value="{{ $s->id }}" data-price="{{ $price }}">
              {{ $name }} — {{ $fmtMoney($price) }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Bulk toggle (hidden until service selected) --}}
      <div class="col-12 d-none" id="wrapBulk">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="1" id="bulkToggle" name="bulk">
          <label class="form-check-label" for="bulkToggle">Bulk</label>
        </div>
      </div>

      {{-- IMEI/SN single (hidden until service selected, and when bulk off) --}}
      <div class="col-12 d-none" id="wrapDeviceSingle">
        @if(!$isFileKind)
          <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" id="deviceInput" placeholder="Enter IMEI / SN">
        @endif
      </div>

      {{-- Bulk textarea (hidden until service selected, and when bulk on) --}}
      <div class="col-12 d-none" id="wrapDeviceBulk">
        <label class="form-label">Bulk list</label>
        <textarea class="form-control" name="devices" rows="6" placeholder="One IMEI per line"></textarea>
      </div>

      {{-- File input (if file kind) --}}
      @if($isFileKind)
        <div class="col-12 d-none" id="wrapFile">
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file">
        </div>
      @endif

      {{-- Comments (hidden until service selected) --}}
      <div class="col-12 d-none" id="wrapComments">
        <label class="form-label">Comments</label>
        <textarea class="form-control" name="comments" rows="3"></textarea>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success" id="btnCreate">Create</button>
  </div>
</form>

@push('scripts')
<script>
(function () {
  const form = document.getElementById('createOrderForm');
  if (!form) return;

  const userSel    = form.querySelector('.js-user');
  const serviceSel = form.querySelector('.js-service');

  const wrapService      = document.getElementById('wrapService');
  const wrapBulk         = document.getElementById('wrapBulk');
  const bulkToggle       = document.getElementById('bulkToggle');

  const wrapDeviceSingle = document.getElementById('wrapDeviceSingle');
  const wrapDeviceBulk   = document.getElementById('wrapDeviceBulk');
  const deviceInput      = document.getElementById('deviceInput');

  const wrapComments     = document.getElementById('wrapComments');
  const wrapFile         = document.getElementById('wrapFile');

  function show(el){ if (el) el.classList.remove('d-none'); }
  function hide(el){ if (el) el.classList.add('d-none'); }

  function resetAfterUser() {
    // reset service + below
    if (serviceSel) serviceSel.value = '';
    hide(wrapBulk);
    hide(wrapDeviceSingle);
    hide(wrapDeviceBulk);
    hide(wrapComments);
    hide(wrapFile);
    if (bulkToggle) bulkToggle.checked = false;

    // enforce required behavior
    if (deviceInput) deviceInput.required = false;
  }

  function updateAfterService() {
    const hasService = !!(serviceSel && serviceSel.value);

    if (!hasService) {
      hide(wrapBulk);
      hide(wrapDeviceSingle);
      hide(wrapDeviceBulk);
      hide(wrapComments);
      hide(wrapFile);
      if (bulkToggle) bulkToggle.checked = false;
      if (deviceInput) deviceInput.required = false;
      return;
    }

    show(wrapBulk);
    show(wrapComments);

    // file kind
    if (wrapFile) show(wrapFile);

    // non-file: decide single vs bulk
    if (wrapFile) {
      // file kind doesn't need device
      return;
    }

    const isBulk = !!(bulkToggle && bulkToggle.checked);
    if (isBulk) {
      hide(wrapDeviceSingle);
      show(wrapDeviceBulk);
      if (deviceInput) deviceInput.required = false;
    } else {
      show(wrapDeviceSingle);
      hide(wrapDeviceBulk);
      if (deviceInput) deviceInput.required = true;
    }
  }

  // initial state
  hide(wrapService);
  resetAfterUser();
  updateAfterService();

  userSel.addEventListener('change', function () {
    if (userSel.value) {
      show(wrapService);
    } else {
      hide(wrapService);
      resetAfterUser();
    }
    updateAfterService();
  });

  serviceSel.addEventListener('change', function () {
    updateAfterService();
  });

  if (bulkToggle) {
    bulkToggle.addEventListener('change', function () {
      updateAfterService();
    });
  }

  // Select2 (search)
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
@endpush
