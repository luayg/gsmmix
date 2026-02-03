{{-- resources/views/admin/orders/modals/edit.blade.php --}}

@php
  $row = $row ?? ($order ?? null);
  $routePrefix = $routePrefix ?? 'admin.orders.imei';

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
        if (is_array($j)) {
          $v = $j['en'] ?? $j['fallback'] ?? (is_array($j) ? reset($j) : $v) ?? $v;
        }
      }
    }
    return $cleanText($v);
  };

  // response -> array
  $resp = $row?->response ?? null;
  if (is_string($resp)) {
    $decoded = json_decode($resp, true);
    $resp = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($resp)) $resp = [];

  $items = (isset($resp['result_items']) && is_array($resp['result_items'])) ? $resp['result_items'] : [];
  $img   = $resp['result_image'] ?? null;

  $isSafeImg = function ($url) {
    if (!is_string($url)) return false;
    $u = trim($url);
    return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };

  // ✅ اسم الخدمة الصحيح
  $serviceName = $row?->service?->name ?? ($row->service_name ?? '—');
  $serviceName = $pickName($serviceName);
  $apiServiceName = $serviceName;

  // ✅ Provider name
  $providerName = $row?->provider?->name ?? ($row->provider_name ?? '—');
  $providerName = $cleanText($providerName);

  // ✅ Reply HTML (نخزنه ونعدله داخل المحرر)
  $providerReplyHtml = (string)($resp['provider_reply_html'] ?? '');

  // ✅ إذا فاضي: نبنيه من result_items / result_text
  if (trim($providerReplyHtml) === '') {
    if (!empty($items)) {
      $html = '';
      if ($img && $isSafeImg($img)) {
        $html .= '<div style="text-align:center;margin-bottom:10px;">'
              .  '<img src="'.e($img).'" style="max-width:260px;height:auto;" />'
              .  '</div>';
      }

      $html .= '<table class="table table-sm table-striped table-bordered"><tbody>';
      foreach ($items as $it) {
        $label = is_array($it) ? ($it['label'] ?? '') : '';
        $value = is_array($it) ? ($it['value'] ?? '') : '';
        $html .= '<tr>'
              .  '<th style="width:220px;">'.e($cleanText($label)).'</th>'
              .  '<td>'.e($cleanText($value)).'</td>'
              .  '</tr>';
      }
      $html .= '</tbody></table>';

      $providerReplyHtml = $html;
    } else {
      $txt = $cleanText($resp['result_text'] ?? '');
      if ($txt !== '') {
        $lines = preg_split("/\n+/", $txt);
        $parsed = [];
        foreach ($lines as $line) {
          $line = trim($line);
          if ($line === '') continue;
          if (strpos($line, ':') !== false) {
            [$k,$v] = array_map('trim', explode(':', $line, 2));
            if ($k !== '' && $v !== '') $parsed[] = [$k,$v];
          }
        }

        if (count($parsed)) {
          $html = '';
          if ($img && $isSafeImg($img)) {
            $html .= '<div style="text-align:center;margin-bottom:10px;">'
                  .  '<img src="'.e($img).'" style="max-width:260px;height:auto;" />'
                  .  '</div>';
          }
          $html .= '<table class="table table-sm table-striped table-bordered"><tbody>';
          foreach ($parsed as $pair) {
            $html .= '<tr><th style="width:220px;">'.e($cleanText($pair[0])).'</th><td>'.e($cleanText($pair[1])).'</td></tr>';
          }
          $html .= '</tbody></table>';
          $providerReplyHtml = $html;
        } else {
          $providerReplyHtml = '<pre style="white-space:pre-wrap;">'.e($txt).'</pre>';
        }
      }
    }
  }

  // بقية الحقول
  $userEmail = $cleanText($row?->email ?? ($row->user_email ?? '—'));
  $device    = $cleanText($row?->device ?? ($row->imei ?? '—'));
  $remoteId  = $cleanText($row?->remote_id ?? '—');
  $ip        = $cleanText($row?->ip ?? '—');

  $createdAt = optional($row?->created_at)->format('d/m/Y H:i:s') ?? '—';
  $repliedAt = optional($row?->replied_at)->format('d/m/Y H:i:s') ?? '—';

  $apiOrder  = ($row?->api_order ?? false) ? 'Yes' : 'No';

  $orderPrice = $row?->price;
  $apiCost    = $row?->order_price;
  $profit     = $row?->profit;

  $fmt = function ($v) {
    if ($v === null || $v === '') return '—';
    if (is_numeric($v)) return '$' . number_format((float)$v, 2);
    return (string)$v;
  };

  $curStatus = $row?->status ?? 'waiting';
@endphp

<div class="modal-header" style="background:#f39c12; color:#fff;">
  <div class="d-flex align-items-center gap-2">
    <strong>Order #{{ $row?->id ?? '' }}</strong>
    <span class="opacity-75">|</span>
    <span class="opacity-75">Edit</span>
  </div>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route($routePrefix.'.update', $row?->id ?? 0) }}">
  @csrf

  <div class="modal-body" style="max-height: calc(100vh - 210px); overflow:auto;">
    <div class="row g-3">

      {{-- LEFT --}}
      <div class="col-lg-6">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0">
            <tbody>
              <tr><th style="width:180px;">Service</th><td>{{ $serviceName }}</td></tr>
              <tr><th>User</th><td>{{ $userEmail }}</td></tr>
              <tr><th>IMEI/Serial number</th><td>{{ $device }}</td></tr>
              <tr><th>Order date</th><td>{{ $createdAt }}</td></tr>
              <tr><th>Order IP</th><td>{{ $ip }}</td></tr>
              <tr><th>Ordered via API</th><td>{{ $apiOrder }}</td></tr>
              <tr><th>Order price</th><td>{{ $fmt($orderPrice) }}</td></tr>
              <tr>
                <th>Status</th>
                <td>
                  @php($st = strtolower($curStatus))
                  @if($st==='success') <span class="badge bg-success">Success</span>
                  @elseif($st==='rejected') <span class="badge bg-danger">Rejected</span>
                  @elseif($st==='inprogress') <span class="badge bg-info">In progress</span>
                  @elseif($st==='cancelled') <span class="badge bg-dark">Cancelled</span>
                  @else <span class="badge bg-secondary">Waiting</span>
                  @endif
                </td>
              </tr>
              <tr><th>Final price</th><td>{{ $fmt($orderPrice) }}</td></tr>
              <tr><th>Profit</th><td>{{ $fmt($profit) }}</td></tr>
              <tr><th>Reply date</th><td>{{ $repliedAt }}</td></tr>
              <tr><th>API order ID</th><td>{{ $remoteId }}</td></tr>
              <tr><th>API name</th><td>{{ $providerName }}</td></tr>
              <tr><th>API service</th><td>{{ $apiServiceName }}</td></tr>
              <tr><th>API processing price</th><td>{{ $fmt($apiCost) }}</td></tr>
            </tbody>
          </table>
        </div>

        {{-- ✅ Result Preview فقط باليسار (مثل الصورة القديمة) --}}
        <div class="mt-3">
          <div class="fw-semibold mb-2">Result Preview</div>
          <div class="border rounded p-2 bg-white">
            @if(!empty($img) && $isSafeImg($img))
              <div class="mb-2 text-center">
                <img src="{{ $img }}" alt="Result image" style="max-width:260px;height:auto;" class="img-fluid rounded shadow-sm">
              </div>
            @endif

            @if(is_array($items) && count($items))
              <table class="table table-sm table-striped table-bordered mb-0">
                <tbody>
                @foreach($items as $it)
                  @php
                    $label = is_array($it) ? ($it['label'] ?? '') : '';
                    $value = is_array($it) ? ($it['value'] ?? '') : '';
                  @endphp
                  <tr>
                    <th style="width:220px;">{{ $cleanText($label) }}</th>
                    <td>{{ $cleanText($value) }}</td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            @else
              <span class="text-muted">—</span>
            @endif
          </div>
        </div>
      </div>

      {{-- RIGHT --}}
      <div class="col-lg-6">
        <div class="mb-2 fw-semibold">Reply</div>

        {{-- ✅ Editor فقط (بدون Preview مكرر + بدون Comments/Response) --}}
        <textarea
          id="replyEditor"
          name="provider_reply_html"
          class="form-control"
          rows="14"
          data-summernote="1"
          data-summernote-height="420"
        >{!! old('provider_reply_html', $providerReplyHtml) !!}</textarea>

        <div class="mt-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select select2" required>
            <option value="waiting"    @selected($curStatus==='waiting')>Waiting</option>
            <option value="inprogress" @selected($curStatus==='inprogress')>In progress</option>
            <option value="success"    @selected($curStatus==='success')>Success</option>
            <option value="rejected"   @selected($curStatus==='rejected')>Rejected</option>
            <option value="cancelled"  @selected($curStatus==='cancelled')>Cancelled</option>
          </select>
        </div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
