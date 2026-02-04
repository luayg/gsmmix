{{-- resources/views/admin/orders/modals/create.blade.php --}}

@php
  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);

      // لو JSON translations
      if ($s !== '' && isset($s[0]) && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) {
          $v = $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
        }
      }

      // decode entities + تنظيف
      $v = html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $v = trim($v);
      return $v;
    }
    return trim((string)$v);
  };

  $pickPrice = function ($svc) {
    $p = $svc->price ?? $svc->sell_price ?? 0;
    return (float)$p;
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
    <div class="row g-3">

      {{-- STEP 1: USER --}}
      <div class="col-12">
        <label class="form-label fw-semibold">User</label>
        <select class="form-select" name="user_id" id="coUser">
          <option value="">Choose user…</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}">{{ $u->email }}</option>
          @endforeach
        </select>
        <div class="form-text">اختر مستخدم (مطلوب للخصم من الرصيد).</div>
      </div>

      <div class="col-12 d-none" id="coEmailWrap">
        <label class="form-label">User email (optional)</label>
        <input type="text" class="form-control" name="email" placeholder="user@email.com">
      </div>

      {{-- STEP 2: SERVICE --}}
      <div class="col-12 d-none" id="coServiceWrap">
        <label class="form-label fw-semibold">Service</label>
        <select class="form-select" name="service_id" id="coService" required>
          <option value="">Choose service…</option>
          @foreach($services as $s)
            @php($price = $pickPrice($s))
            <option value="{{ $s->id }}" data-price="{{ $price }}">
              {{ $pickName($s->name) }} — ${{ number_format($price, 2) }}
            </option>
          @endforeach
        </select>
        <div class="form-text">عند اختيار الخدمة سيتم الإرسال تلقائياً إذا كانت مرتبطة بـ API.</div>
      </div>

      {{-- STEP 3: INPUTS --}}
      @if($isFileKind)
        <div class="col-12 d-none" id="coFileWrap">
          <label class="form-label fw-semibold">Upload file</label>
          <input type="file" class="form-control" name="file">
          <div class="form-text">سيتم رفع الملف وتخزينه ثم إرساله تلقائياً إذا كانت الخدمة مرتبطة بـ API.</div>
        </div>
      @else
        <div class="col-12 d-none" id="coModeWrap">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <label class="form-label fw-semibold mb-0">{{ $deviceLabel ?? 'Device' }}</label>

            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="coBulk" name="bulk" value="1">
              <label class="form-check-label" for="coBulk">Bulk mode</label>
            </div>
          </div>

          {{-- single --}}
          <input type="text" class="form-control mt-2" name="device" id="coDeviceSingle" placeholder="Enter IMEI/SN">

          {{-- bulk --}}
          <textarea class="form-control mt-2 d-none" name="devices" id="coDeviceBulk" rows="6"
                    placeholder="Paste IMEIs/SNs, one per line"></textarea>

          <div class="form-text">
            في Bulk: كل سطر = طلب منفصل. سيتم فحص الرصيد على إجمالي الطلبات قبل الإرسال.
          </div>
        </div>
      @endif

      @if(!empty($supportsQty))
        <div class="col-12 d-none" id="coQtyWrap">
          <label class="form-label fw-semibold">Quantity</label>
          <input type="number" class="form-control" name="quantity" min="1" value="1">
        </div>
      @endif

      <div class="col-12 d-none" id="coCommentsWrap">
        <label class="form-label fw-semibold">Comments</label>
        <textarea class="form-control" name="comments" rows="3" placeholder="Optional…"></textarea>
      </div>

      {{-- SUMMARY --}}
      <div class="col-12 d-none" id="coSummaryWrap">
        <div class="alert alert-info mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <strong>Summary:</strong>
            <span id="coSummaryText">—</span>
          </div>
          <div class="fw-semibold">
            Total: <span id="coTotal">$0.00</span>
          </div>
        </div>
        <div class="form-text">
          سيتم رفض الإرسال إذا كان رصيد المستخدم غير كافٍ.
        </div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success" id="coSubmit" disabled>Create</button>
  </div>
</form>

<script>
(function () {
  const userSel = document.getElementById('coUser');
  const svcSel  = document.getElementById('coService');
  const submit  = document.getElementById('coSubmit');

  const wService  = document.getElementById('coServiceWrap');
  const wEmail    = document.getElementById('coEmailWrap');
  const wComments = document.getElementById('coCommentsWrap');
  const wSummary  = document.getElementById('coSummaryWrap');

  const isFileKind = {{ $isFileKind ? 'true' : 'false' }};

  const wFile    = document.getElementById('coFileWrap');
  const wMode    = document.getElementById('coModeWrap');
  const bulkChk  = document.getElementById('coBulk');
  const devOne   = document.getElementById('coDeviceSingle');
  const devMany  = document.getElementById('coDeviceBulk');

  const sumText  = document.getElementById('coSummaryText');
  const totalEl  = document.getElementById('coTotal');

  function money(n){
    n = Number(n || 0);
    return '$' + n.toFixed(2);
  }

  function countBulkLines() {
    if (!devMany) return 0;
    const lines = (devMany.value || '')
      .split(/\r?\n/)
      .map(s => s.trim())
      .filter(Boolean);
    return lines.length;
  }

  function currentPrice() {
    const opt = svcSel ? svcSel.options[svcSel.selectedIndex] : null;
    if (!opt) return 0;
    return Number(opt.getAttribute('data-price') || 0);
  }

  function refreshSummary() {
    const userOk = !!(userSel && userSel.value);
    const svcOk  = !!(svcSel && svcSel.value);

    // Step gating
    wService?.classList.toggle('d-none', !userOk);
    wEmail?.classList.toggle('d-none', !userOk);

    if (!userOk) {
      wComments?.classList.add('d-none');
      wSummary?.classList.add('d-none');
      if (wFile) wFile.classList.add('d-none');
      if (wMode) wMode.classList.add('d-none');
      submit.disabled = true;
      return;
    }

    // show next after service chosen
    wComments?.classList.toggle('d-none', !svcOk);

    if (isFileKind) {
      if (wFile) wFile.classList.toggle('d-none', !svcOk);
    } else {
      if (wMode) wMode.classList.toggle('d-none', !svcOk);
    }

    // calculate total
    const price = currentPrice();
    let qty = 1;

    if (!isFileKind && bulkChk && bulkChk.checked) {
      qty = Math.max(1, countBulkLines());
    }

    const total = price * qty;

    if (svcOk) {
      wSummary?.classList.remove('d-none');
      const svcText = svcSel.options[svcSel.selectedIndex]?.textContent?.trim() || '';
      sumText.textContent = `User selected, ${svcText}${(!isFileKind && bulkChk && bulkChk.checked) ? ' (Bulk x' + qty + ')' : ''}`;
      totalEl.textContent = money(total);
    } else {
      wSummary?.classList.add('d-none');
    }

    // enable submit if have required data
    let inputOk = false;
    if (svcOk) {
      if (isFileKind) {
        inputOk = true; // file required will be checked by backend validation
      } else {
        if (bulkChk && bulkChk.checked) {
          inputOk = countBulkLines() > 0;
        } else {
          inputOk = !!(devOne && devOne.value.trim());
        }
      }
    }

    submit.disabled = !(userOk && svcOk && inputOk);
  }

  // Bulk toggle
  if (bulkChk && devOne && devMany) {
    bulkChk.addEventListener('change', () => {
      const on = bulkChk.checked;
      devOne.classList.toggle('d-none', on);
      devMany.classList.toggle('d-none', !on);
      // important: make sure backend doesn't get both
      if (on) devOne.value = '';
      refreshSummary();
    });
  }

  // events
  userSel?.addEventListener('change', refreshSummary);
  svcSel?.addEventListener('change', refreshSummary);
  devOne?.addEventListener('input', refreshSummary);
  devMany?.addEventListener('input', refreshSummary);

  // initial
  refreshSummary();
})();
</script>
