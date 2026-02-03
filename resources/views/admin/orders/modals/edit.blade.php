{{-- resources/views/admin/orders/modals/edit.blade.php --}}

@php
  $row = $row ?? ($order ?? null);
  $routePrefix = $routePrefix ?? 'admin.orders.imei';

  $cleanText = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $v = str_replace(["✖","❌","✘"], '', $v);
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    return trim($v);
  };

  $pickName = function ($v) use ($cleanText) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) {
          $v = $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
        }
      }
    }
    return $cleanText($v);
  };

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

  // Badges
  $renderValue = function ($label, $value) use ($cleanText) {
    $labelL = mb_strtolower($cleanText($label));
    $val    = $cleanText($value);
    $valL   = mb_strtolower($val);

    if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
    if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';

    if (str_contains($labelL, 'find my') || str_contains($labelL, 'fmi')) {
      if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
      if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';
    }

    if (str_contains($labelL, 'icloud')) {
      if (str_contains($valL, 'lost'))  return '<span class="badge bg-danger">'.e($val).'</span>';
      if (str_contains($valL, 'clean')) return '<span class="badge bg-success">'.e($val).'</span>';
      return '<span class="badge bg-secondary">'.e($val).'</span>';
    }

    if (in_array($valL, ['activated','unlocked','clean'], true)) return '<span class="badge bg-success">'.e($val).'</span>';
    if (in_array($valL, ['expired'], true)) return '<span class="badge bg-danger">'.e($val).'</span>';
    if (str_contains($valL, 'lost')) return '<span class="badge bg-danger">'.e($val).'</span>';

    return e($val);
  };

  $serviceName = $row?->service?->name ?? ($row->service_name ?? '—');
  $serviceName = $pickName($serviceName);

  $apiServiceName = $serviceName;

  $providerName = $row?->provider?->name ?? ($row->provider_name ?? '—');
  $providerName = $cleanText($providerName);

  // Reply HTML (حفظ إن وجد)
  $providerReplyHtml = $resp['provider_reply_html'] ?? '';

  // ✅ لو غير موجود: نولّد Reply كـ Table ملوّن
  if (trim((string)$providerReplyHtml) === '') {

    // 1) إذا عندنا result_items استخدمها
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
        $html .= '<tr><th style="width:220px;">'.e($cleanText($label)).'</th><td>'.$renderValue($label, $value).'</td></tr>';
      }
      $html .= '</tbody></table>';
      $providerReplyHtml = $html;

    } else {
      // 2) إذا result_text موجود: حوّله لجدول بدل <pre>
      $txt = $resp['result_text'] ?? '';
      $txt = $cleanText($txt);

      $parsed = [];
      if ($txt !== '') {
        $lines = preg_split("/\n+/", $txt);
        foreach ($lines as $line) {
          $line = trim($line);
          if ($line === '') continue;
          // key: value
          if (str_contains($line, ':')) {
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            if ($k !== '' && $v !== '') {
              $parsed[] = ['label' => $k, 'value' => $v];
            }
          }
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
        foreach ($parsed as $it) {
          $label = $it['label'];
          $value = $it['value'];
          $html .= '<tr><th style="width:220px;">'.e($cleanText($label)).'</th><td>'.$renderValue($label, $value).'</td></tr>';
        }
        $html .= '</tbody></table>';
        $providerReplyHtml = $html;
      } else {
        // 3) fallback message
        $providerReplyHtml = !empty($resp['message']) ? '<div>'.e($cleanText($resp['message'])).'</div>' : '';
      }
    }
  }

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

  <div class="modal-body" style="max-height: calc(100vh - 210px); overflow: auto;">
    <div class="row g-3">

      <div class="col-lg-6">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0">
            <tbody>
              <tr><th style="width:180px;">Service</th><td>{{ $serviceName }}</td></tr>
              <tr><th>User</th><td>{{ $userEmail }}</td></tr>
              <tr><th>IMEI/Serial number</th><td>{{ $device }}</td></tr>
              <tr><th>Comments</th><td>{{ $row?->comments ?? '—' }}</td></tr>
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
              <tr><th>Reply</th><td class="text-muted">تعديل الرد من اليمين</td></tr>
              <tr><th>API order ID</th><td>{{ $remoteId }}</td></tr>
              <tr><th>API name</th><td>{{ $providerName }}</td></tr>
              <tr><th>API service</th><td>{{ $apiServiceName }}</td></tr>
              <tr><th>API processing price</th><td>{{ $fmt($apiCost) }}</td></tr>
            </tbody>
          </table>
        </div>

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
                    <td>{!! $renderValue($label, $value) !!}</td>
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

      <div class="col-lg-6">
        <div class="mb-2 fw-semibold">Reply</div>

        <textarea
          id="replyEditor"
          name="provider_reply_html"
          class="form-control"
          rows="14"
          data-summernote="1"
          data-summernote-height="360"
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

        <div class="mt-3">
          <label class="form-label fw-semibold">Comments</label>
          <input type="text" name="comments" class="form-control" value="{{ $row?->comments ?? '' }}">
        </div>

        <div class="mt-3">
          <label class="form-label text-muted">Response (optional)</label>
          <textarea name="response" class="form-control" rows="4" placeholder="اختياري"></textarea>
        </div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
