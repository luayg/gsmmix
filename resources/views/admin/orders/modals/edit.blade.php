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
        if (is_array($j)) $v = $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
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

  // Badge rendering
  $renderValue = function ($label, $value) use ($cleanText) {
    $label = strtolower($cleanText($label));
    $val   = $cleanText($value);
    $valL  = strtolower($val);

    if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
    if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';

    if (strpos($label, 'icloud') !== false) {
      if (strpos($valL, 'lost') !== false)  return '<span class="badge bg-danger">'.e($val).'</span>';
      if (strpos($valL, 'clean') !== false) return '<span class="badge bg-success">'.e($val).'</span>';
      return '<span class="badge bg-secondary">'.e($val).'</span>';
    }

    if (in_array($valL, ['activated','unlocked','clean'], true)) return '<span class="badge bg-success">'.e($val).'</span>';
    if (in_array($valL, ['expired'], true)) return '<span class="badge bg-danger">'.e($val).'</span>';
    if (strpos($valL, 'lost') !== false) return '<span class="badge bg-danger">'.e($val).'</span>';

    return e($val);
  };

  // Names
  $serviceName  = $pickName($row?->service?->name ?? ($row->service_name ?? '—'));
  $providerName = $cleanText($row?->provider?->name ?? ($row->provider_name ?? '—'));

  // Reply HTML build (table + image)
  $providerReplyHtml = (string)($resp['provider_reply_html'] ?? '');

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
        $html .= '<tr><th style="width:220px;">'.e($cleanText($label)).'</th><td>'.$renderValue($label, $value).'</td></tr>';
      }
      $html .= '</tbody></table>';
      $providerReplyHtml = $html;
    } else {
      $providerReplyHtml = e($cleanText($resp['message'] ?? ''));
    }
  }

  // other fields
  $userEmail = $cleanText($row?->email ?? ($row->user_email ?? '—'));
  $device    = $cleanText($row?->device ?? ($row->imei ?? '—'));
  $remoteId  = $cleanText($row?->remote_id ?? '—');
  $ip        = $cleanText($row?->ip ?? '—');

  $createdAt = optional($row?->created_at)->format('d/m/Y H:i:s') ?? '—';
  $repliedAt = optional($row?->replied_at)->format('d/m/Y H:i:s') ?? '—';

  $apiOrder  = ($row?->api_order ?? false) ? 'Yes' : 'No';

  // ✅ أسعار (الأكثر منطقية لشكل لوحة GSM):
  // price = سعر العميل
  // order_price = تكلفة الـ API
  $customerPrice = $row?->price;
  $apiCost       = $row?->order_price;
  $finalPrice    = $row?->final_price ?? $customerPrice;
  $profit        = $row?->profit ?? (
    (is_numeric($finalPrice) && is_numeric($apiCost)) ? ((float)$finalPrice - (float)$apiCost) : null
  );

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

      {{-- LEFT INFO --}}
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
              <tr><th>Order price</th><td>{{ $fmt($customerPrice) }}</td></tr>
              <tr><th>Final price</th><td>{{ $fmt($finalPrice) }}</td></tr>
              <tr><th>Profit</th><td>{{ $fmt($profit) }}</td></tr>
              <tr><th>Reply date</th><td>{{ $repliedAt }}</td></tr>
              <tr><th>API order ID</th><td>{{ $remoteId }}</td></tr>
              <tr><th>API name</th><td>{{ $providerName }}</td></tr>
              <tr><th>API processing price</th><td>{{ $fmt($apiCost) }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- RIGHT: Reply + Status --}}
      <div class="col-lg-6">
        <div class="mb-3">
          <label class="form-label">Reply</label>
          <textarea
  name="reply"
  class="form-control js-editor"
  rows="12"
  data-editor="tinymce"
  data-editor-height="320"
>{!! $providerReplyHtml !!}</textarea>


          <div class="form-text">
            يمكنك تعديل الرد بتنسيق كامل (مثل صورة 77).
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" class="form-select" required>
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
