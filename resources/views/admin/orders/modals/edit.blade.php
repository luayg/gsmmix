@php
  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) return $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
      return $v;
    }
    return (string)$v;
  };

  $resp = $row->response;
  if (is_string($resp)) {
    $decoded = json_decode($resp, true);
    if (is_array($decoded)) $resp = $decoded;
  }
  if (!is_array($resp)) $resp = [];

  $items = $resp['result_items'] ?? [];
  $img   = $resp['result_image'] ?? null;

  // ✅ reply_html الذي سنعرضه في المحرر (HTML)
  $replyHtml = (string)($resp['reply_html'] ?? '');

  // إذا ما فيه reply_html، نبني HTML بسيط من النتيجة
  if ($replyHtml === '') {
    $built = '';
    if ($img) {
      $built .= '<p style="text-align:center"><img src="'.e($img).'" style="max-width:520px;height:auto;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.12);" /></p>';
    }
    if (is_array($items) && count($items)) {
      foreach ($items as $it) {
        $label = $it['label'] ?? '';
        $value = $it['value'] ?? '';
        $built .= '<div><strong>'.e($label).':</strong> '.e($value).'</div>';
      }
    } elseif (!empty($resp['message'])) {
      $built .= '<div>'.e($resp['message']).'</div>';
    }
    $replyHtml = $built;
  }

  $rawJson = json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

  $clean = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($v);
  };

  $renderValue = function ($label, $value) use ($clean) {
    $labelL = mb_strtolower($clean($label));
    $val = $clean($value);
    $valL = mb_strtolower($val);

    if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
    if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';

    if (str_contains($labelL, 'icloud')) {
      if (str_contains($valL, 'lost'))  return '<span class="badge bg-danger">'.e($val).'</span>';
      if (str_contains($valL, 'clean')) return '<span class="badge bg-success">'.e($val).'</span>';
      return '<span class="badge bg-secondary">'.e($val).'</span>';
    }

    if ($valL === 'activated') return '<span class="badge bg-success">Activated</span>';
    if ($valL === 'expired')   return '<span class="badge bg-danger">Expired</span>';
    if ($valL === 'unlocked')  return '<span class="badge bg-success">Unlocked</span>';

    return e($val);
  };

  $isSafeImg = function ($url) {
    if (!is_string($url)) return false;
    $u = trim($url);
    return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };

  // ✅ Pricing
  $currency = $row->currency ?? $row->curr ?? 'USD';
  $orderPrice = $row->price ?? null;
  $profit = $row->profit ?? null;
  $apiProcessingPrice = $row->service?->cost ?? null;

  $fmtMoney = function ($v) use ($currency) {
    if ($v === null || $v === '') return null;
    if (is_numeric($v)) return number_format((float)$v, 2) . ' ' . $currency;
    return (string)$v . ' ' . $currency;
  };

  if ($profit === null && is_numeric($orderPrice) && is_numeric($apiProcessingPrice)) {
    $profit = (float)$orderPrice - (float)$apiProcessingPrice;
  }
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? ('Edit Order #'.$row->id) }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.update', $row->id) }}">
  @csrf

  {{-- ✅ scroll واضح داخل المودال --}}
  <div class="modal-body" style="max-height:70vh; overflow:auto;">
    <div class="row g-3">

      {{-- LEFT --}}
      <div class="col-lg-6">
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <tbody>
              <tr><th style="width:220px">Service</th><td>{{ $row->service ? $pickName($row->service->name) : '—' }}</td></tr>
              <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
              <tr><th>Device</th><td>{{ $row->device }}</td></tr>
              <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
              <tr><th>API order ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>

              @if(!empty($row->ip))
                <tr><th>Order IP</th><td>{{ $row->ip }}</td></tr>
              @endif

              @if($orderPrice !== null)
                <tr><th>Order price</th><td>{{ $fmtMoney($orderPrice) }}</td></tr>
              @endif

              @if($apiProcessingPrice !== null)
                <tr><th>API processing price</th><td>{{ $fmtMoney($apiProcessingPrice) }}</td></tr>
              @endif

              @if($profit !== null)
                <tr><th>Profit</th><td>{{ $fmtMoney($profit) }}</td></tr>
              @endif
            </tbody>
          </table>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" required>
              @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
                <option value="{{ $st }}" @selected($row->status===$st)>{{ ucfirst($st) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Comments</label>
            <input type="text" class="form-control" name="comments" value="{{ $row->comments }}">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Result (Preview)</label>

          @if($img && $isSafeImg($img))
            <div class="mb-3 text-center">
              {{-- ✅ تكبير الصورة --}}
              <img src="{{ $img }}" alt="Result image"
                   style="max-width:520px; height:auto;"
                   class="img-fluid rounded shadow-sm">
            </div>
          @endif

          @if(is_array($items) && count($items))
            <div class="table-responsive">
              <table class="table table-sm table-striped table-bordered mb-0">
                <tbody>
                @foreach($items as $it)
                  @php
                    $label = $it['label'] ?? '';
                    $value = $it['value'] ?? '';
                  @endphp
                  <tr>
                    <th style="width:240px">{{ $label }}</th>
                    <td>{!! $renderValue($label, $value) !!}</td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="border rounded p-3 bg-light mb-0">
              {{ $resp['message'] ?? '—' }}
            </div>
          @endif
        </div>
      </div>

      {{-- RIGHT --}}
      <div class="col-lg-6">
        <label class="form-label">Reply</label>

        <details open class="border rounded p-2 bg-white">
          <summary class="fw-semibold">Provider reply (editable)</summary>

          <div class="mt-2">
            <div class="form-text mb-2">
              هذا الحقل يدعم تنسيق + إدراج صور. بعد التعديل اضغط <b>Save</b>.
            </div>

            {{-- ✅ Summernote --}}
            <textarea
              class="form-control"
              name="reply_html"
              data-summernote="1"
              data-summernote-height="420"
            >{!! $replyHtml !!}</textarea>
          </div>
        </details>

        <details class="mt-3">
          <summary class="fw-semibold">Raw response JSON (advanced)</summary>
          <div class="mt-2">
            <textarea class="form-control" name="response" rows="10" style="font-family: ui-monospace, Menlo, Consolas, monospace;">{{ $rawJson }}</textarea>
            <div class="form-text">إذا عدّلته لازم يكون JSON صحيح.</div>
          </div>
        </details>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
