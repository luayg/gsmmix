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

      {{-- User --}}
      <div class="col-12">
        <label class="form-label">User</label>

        <select
          class="form-select"
          name="user_id"
          id="userSel"
          required
          onchange="
            (function(){
              var userSel = document.getElementById('userSel');
              var serviceWrap = document.getElementById('serviceWrap');
              var serviceSel = document.getElementById('serviceSel');
              var fields = document.querySelectorAll('.js-step-fields');

              function show(el){ el && el.classList.remove('d-none'); }
              function hide(el){ el && el.classList.add('d-none'); }

              // reset service + fields
              if(!userSel.value){
                hide(serviceWrap);
                if(serviceSel){ serviceSel.value=''; }
                fields.forEach(function(w){ hide(w); });
              }else{
                show(serviceWrap);

                // update service option labels based on group
                var gid = Number(userSel.selectedOptions[0].getAttribute('data-group') || 0);
                var opts = serviceSel ? Array.from(serviceSel.options) : [];
                opts.forEach(function(opt){
                  if(!opt.value) return;
                  var name = opt.getAttribute('data-name') || opt.textContent || '';
                  var gpRaw = opt.getAttribute('data-group-prices') || '{}';
                  var gp = {};
                  try { gp = JSON.parse(gpRaw); } catch(e){ gp = {}; }
                  var price = gp[String(gid)];
                  price = (typeof price === 'string') ? Number(price) : price;
                  if(!price || isNaN(price)) price = 0;
                  opt.textContent = name + ' — $' + price.toFixed(2);
                });

                // if service already selected, show fields
                if(serviceSel && serviceSel.value){
                  fields.forEach(function(w){ show(w); });
                }
              }

              // update summary (price/balance/after + create enable)
              var bal = 0;
              if(userSel.value){
                bal = Number(userSel.selectedOptions[0].getAttribute('data-balance') || 0);
              }

              var priceNow = 0;
              if(userSel.value && serviceSel && serviceSel.value){
                var gid2 = Number(userSel.selectedOptions[0].getAttribute('data-group') || 0);
                var opt2 = serviceSel.selectedOptions[0];
                var gpRaw2 = opt2.getAttribute('data-group-prices') || '{}';
                var gp2 = {};
                try { gp2 = JSON.parse(gpRaw2); } catch(e){ gp2 = {}; }
                var p2 = gp2[String(gid2)];
                p2 = (typeof p2 === 'string') ? Number(p2) : p2;
                if(p2 && !isNaN(p2)) priceNow = Number(p2);
              }

              var priceEl = document.getElementById('selectedPrice');
              var balEl   = document.getElementById('selectedBalance');
              var afterEl = document.getElementById('balanceAfter');
              if(priceEl) priceEl.textContent = '$' + Number(priceNow||0).toFixed(2);
              if(balEl)   balEl.textContent   = '$' + Number(bal||0).toFixed(2);
              if(afterEl) afterEl.textContent = '$' + (Number(bal||0) - Number(priceNow||0)).toFixed(2);

              var errEl = document.getElementById('balanceError');
              var btn   = document.getElementById('btnCreateOrder');
              var ok = (userSel.value && serviceSel && serviceSel.value && priceNow > 0 && bal >= priceNow);
              if(btn) btn.disabled = !ok;
              if(errEl){
                if(userSel.value && serviceSel && serviceSel.value && priceNow > 0 && bal < priceNow) errEl.classList.remove('d-none');
                else errEl.classList.add('d-none');
              }
            })();
          "
        >
          <option value=""></option>
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

      {{-- Service (hidden until user selected) --}}
      <div class="col-12 d-none" id="serviceWrap">
        <label class="form-label">Service</label>

        <select
          class="form-select"
          name="service_id"
          id="serviceSel"
          required
          onchange="
            (function(){
              var userSel = document.getElementById('userSel');
              var serviceSel = document.getElementById('serviceSel');
              var fields = document.querySelectorAll('.js-step-fields');

              function show(el){ el && el.classList.remove('d-none'); }
              function hide(el){ el && el.classList.add('d-none'); }

              if(serviceSel && serviceSel.value){
                fields.forEach(function(w){ show(w); });
              }else{
                fields.forEach(function(w){ hide(w); });
              }

              // bulk allow
              var bulkToggle = document.getElementById('bulkToggle');
              var singleWrap = document.getElementById('singleDeviceWrap');
              var bulkWrap   = document.getElementById('bulkDevicesWrap');

              var allowBulk = 0;
              if(serviceSel && serviceSel.value){
                allowBulk = Number(serviceSel.selectedOptions[0].getAttribute('data-allow-bulk') || 0);
              }

              if(bulkToggle){
                if(!allowBulk){
                  bulkToggle.checked = false;
                  bulkToggle.disabled = true;
                  if(singleWrap) show(singleWrap);
                  if(bulkWrap) hide(bulkWrap);
                }else{
                  bulkToggle.disabled = false;
                  if(bulkToggle.checked){
                    if(singleWrap) hide(singleWrap);
                    if(bulkWrap) show(bulkWrap);
                  }else{
                    if(singleWrap) show(singleWrap);
                    if(bulkWrap) hide(bulkWrap);
                  }
                }
              }

              // update summary
              var bal = 0;
              var gid = 0;
              if(userSel && userSel.value){
                bal = Number(userSel.selectedOptions[0].getAttribute('data-balance') || 0);
                gid = Number(userSel.selectedOptions[0].getAttribute('data-group') || 0);
              }

              var priceNow = 0;
              if(userSel && userSel.value && serviceSel && serviceSel.value){
                var opt = serviceSel.selectedOptions[0];
                var gpRaw = opt.getAttribute('data-group-prices') || '{}';
                var gp = {};
                try { gp = JSON.parse(gpRaw); } catch(e){ gp = {}; }
                var p = gp[String(gid)];
                p = (typeof p === 'string') ? Number(p) : p;
                if(p && !isNaN(p)) priceNow = Number(p);
              }

              var priceEl = document.getElementById('selectedPrice');
              var balEl   = document.getElementById('selectedBalance');
              var afterEl = document.getElementById('balanceAfter');
              if(priceEl) priceEl.textContent = '$' + Number(priceNow||0).toFixed(2);
              if(balEl)   balEl.textContent   = '$' + Number(bal||0).toFixed(2);
              if(afterEl) afterEl.textContent = '$' + (Number(bal||0) - Number(priceNow||0)).toFixed(2);

              var errEl = document.getElementById('balanceError');
              var btn   = document.getElementById('btnCreateOrder');
              var ok = (userSel && userSel.value && serviceSel && serviceSel.value && priceNow > 0 && bal >= priceNow);
              if(btn) btn.disabled = !ok;
              if(errEl){
                if(userSel && userSel.value && serviceSel && serviceSel.value && priceNow > 0 && bal < priceNow) errEl.classList.remove('d-none');
                else errEl.classList.add('d-none');
              }
            })();
          "
        >
          <option value=""></option>
          @foreach($services as $s)
            @php
              $name = $pickName($s->name);
              $allowBulk = (int)($s->allow_bulk ?? 0);

              // expects: $servicePriceMap[service_id][group_id] = price
              $gp = $servicePriceMap[$s->id] ?? [];
              $gpJson = json_encode($gp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

              // initial label (will be recalculated after user select)
              $basePrice = 0;
              if (is_array($gp)) {
                $first = reset($gp);
                if (is_numeric($first)) $basePrice = (float)$first;
              }
            @endphp
            <option
              value="{{ $s->id }}"
              data-name="{{ $name }}"
              data-allow-bulk="{{ $allowBulk }}"
              data-group-prices='{{ $gpJson }}'
            >{{ $name }} — {{ $fmtMoney($basePrice) }}</option>
          @endforeach
        </select>
      </div>

      {{-- Bulk toggle --}}
      <div class="col-12 js-step-fields d-none">
        <div class="form-check">
          <input
            class="form-check-input"
            type="checkbox"
            value="1"
            id="bulkToggle"
            name="bulk"
            onchange="
              (function(){
                var bulkToggle = document.getElementById('bulkToggle');
                var singleWrap = document.getElementById('singleDeviceWrap');
                var bulkWrap   = document.getElementById('bulkDevicesWrap');
                function show(el){ el && el.classList.remove('d-none'); }
                function hide(el){ el && el.classList.add('d-none'); }
                if(!bulkToggle) return;
                if(bulkToggle.checked){
                  if(singleWrap) hide(singleWrap);
                  if(bulkWrap) show(bulkWrap);
                }else{
                  if(singleWrap) show(singleWrap);
                  if(bulkWrap) hide(bulkWrap);
                }
              })();
            "
          >
          <label class="form-check-label" for="bulkToggle">Bulk</label>
        </div>
      </div>

      {{-- Single device / file --}}
      <div class="col-12 js-step-fields d-none" id="singleDeviceWrap">
        @if($isFileKind)
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file" required>
        @else
          <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" placeholder="Enter IMEI / SN">
        @endif
      </div>

      {{-- Bulk devices --}}
      <div class="col-12 js-step-fields d-none" id="bulkDevicesWrap">
        <label class="form-label">Devices (one per line)</label>
        <textarea class="form-control" name="devices" rows="6" placeholder="Enter one IMEI per line"></textarea>
      </div>

      {{-- Qty (if supported) --}}
      @if(!empty($supportsQty))
      <div class="col-12 js-step-fields d-none">
        <label class="form-label">Quantity</label>
        <input type="number" class="form-control" name="quantity" min="1" value="1">
      </div>
      @endif

      {{-- Comments --}}
      <div class="col-12 js-step-fields d-none">
        <label class="form-label">Comments</label>
        <textarea class="form-control" name="comments" rows="3"></textarea>
      </div>

      {{-- Summary --}}
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
