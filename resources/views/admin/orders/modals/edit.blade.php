{{-- resources/views/admin/orders/modals/edit.blade.php --}}
@php
  $row = $row ?? ($order ?? null);
  $routePrefix = $routePrefix ?? 'admin.orders.imei';
  $kind = $kind ?? 'imei';

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

  $params = $row?->params ?? null;
  if (is_string($params)) {
    $decoded = json_decode($params, true);
    $params = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($params)) $params = [];

  $sentFields = $params['fields'] ?? $params['required'] ?? [];
  if (!is_array($sentFields)) $sentFields = [];

  $serviceParams = $row?->service?->params ?? null;
  if (is_string($serviceParams)) {
    $decoded = json_decode($serviceParams, true);
    $serviceParams = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($serviceParams)) $serviceParams = [];

  $customFields = $serviceParams['custom_fields'] ?? [];
  if (!is_array($customFields)) $customFields = [];

  $cfLabelByInput = [];
  $cfOrder = [];
  foreach ($customFields as $cf) {
    if (!is_array($cf)) continue;
    $input = trim((string)($cf['input'] ?? ''));
    if ($input === '') continue;
    $label = trim((string)($cf['name'] ?? $input));
    $cfLabelByInput[$input] = $label;
    $cfOrder[] = $input;
  }

  $orderedFields = [];
  foreach ($cfOrder as $input) {
    if (array_key_exists($input, $sentFields)) $orderedFields[$input] = $sentFields[$input];
  }
  foreach ($sentFields as $k => $v) {
    if (!array_key_exists($k, $orderedFields)) $orderedFields[$k] = $v;
  }

  $resp = $row?->response ?? null;
  if (is_string($resp)) {
    $decoded = json_decode($resp, true);
    $resp = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($resp)) $resp = [];

  $items = (isset($resp['result_items']) && is_array($resp['result_items'])) ? $resp['result_items'] : [];
  $img   = $resp['result_image'] ?? null;
  $resultText = (string)($resp['result_text'] ?? '');
  $resultMessage = (string)($resp['message'] ?? '');

  $isSafeImg = function ($url) {
    if (!is_string($url)) return false;
    $u = trim($url);
    return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };

  $renderValue = function ($label, $value) use ($cleanText) {
    $label = strtolower($cleanText($label));
    $val   = $cleanText($value);
    $valL  = strtolower($val);

    $pill = function ($text, $bg, $fg = '#fff') {
      $text = e($text);
      return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700;background:'.$bg.';color:'.$fg.';">'.$text.'</span>';
    };

    if ($valL === 'on')  return $pill('ON',  '#dc3545');
    if ($valL === 'off') return $pill('OFF', '#198754');

    if (strpos($label, 'icloud') !== false) {
      if (strpos($valL, 'lost') !== false)  return $pill($val, '#dc3545');
      if (strpos($valL, 'clean') !== false) return $pill($val, '#198754');
      return $pill($val, '#6c757d');
    }

    if (in_array($valL, ['activated','unlocked','clean'], true)) return $pill($val, '#198754');
    if (in_array($valL, ['expired'], true)) return $pill($val, '#dc3545');
    if (strpos($valL, 'lost') !== false) return $pill($val, '#dc3545');

    return e($val);
  };

  $serviceName  = $pickName($row?->service?->name ?? ($row->service_name ?? '—'));
  $providerName = $cleanText($row?->provider?->name ?? ($row->provider_name ?? '—'));

  $providerReplyHtml = (string)($resp['provider_reply_html'] ?? '');

  if (trim($providerReplyHtml) === '') {
    $html = '';

    if ($img && $isSafeImg($img)) {
      $html .= '<div style="text-align:center;margin-bottom:12px;">'
            .  '<img src="'.e($img).'" style="max-width:280px;height:auto;border-radius:8px;" />'
            .  '</div>';
    }

    if (!empty($items)) {
      $html .= '<table class="table table-sm table-striped table-bordered"><tbody>';
      foreach ($items as $it) {
        $label = is_array($it) ? ($it['label'] ?? '') : '';
        $value = is_array($it) ? ($it['value'] ?? '') : '';

        if (trim((string)$label) === '') {
          $html .= '<tr><td colspan="2">'.$renderValue('', $value).'</td></tr>';
        } else {
          $html .= '<tr><th style="width:220px;">'.e($cleanText($label)).'</th><td>'.$renderValue($label, $value).'</td></tr>';
        }
      }
      $html .= '</tbody></table>';
    } elseif ($resultText !== '') {
      $html .= '<div style="white-space:pre-wrap;">'.e($resultText).'</div>';
    } else {
      $html .= '<div style="white-space:pre-wrap;">'.e($cleanText($resultMessage !== '' ? $resultMessage : '—')).'</div>';
    }

    $providerReplyHtml = $html;
  }

  $userEmail = $cleanText($row?->email ?? ($row->user_email ?? '—'));
  $device    = $cleanText($row?->device ?? ($row->imei ?? '—'));
  $remoteId  = $cleanText($row?->remote_id ?? '—');
  $ip        = $cleanText($row?->ip ?? '—');

  $createdAt = optional($row?->created_at)->format('d/m/Y H:i:s') ?? '—';
  $repliedAt = optional($row?->replied_at)->format('d/m/Y H:i:s') ?? '—';

  $apiOrder  = ($row?->api_order ?? false) ? 'Yes' : 'No';

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

  $deviceLabel = match($kind) {
    'imei'   => 'IMEI / Serial number',
    'file'   => 'File',
    'server' => 'Device',
    default  => 'Device',
  };

  $hasServerFields = ($kind === 'server' && count($orderedFields) > 0);

  $requestMeta = is_array($row?->request ?? null) ? $row->request : [];
  $requestUid = $requestMeta['request_uid'] ?? null;
  $dispatchError = $requestMeta['dispatch_error'] ?? null;
@endphp

<div class="modal-header border-0" style="background:#f39c12; color:#fff;">
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
    <style>
      .order-edit-card {
        border: 1px solid #e9ecef;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(0,0,0,.04);
        overflow: hidden;
      }
      .order-edit-card-header {
        padding: 12px 16px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        font-weight: 700;
      }
      .order-edit-card-body {
        padding: 16px;
      }
      .order-edit-preview {
        border: 1px solid #e9ecef;
        border-radius: 12px;
        background: #fff;
        padding: 14px;
        min-height: 120px;
      }
      .order-edit-preview img {
        max-width: 100%;
        max-height: 280px;
        border-radius: 8px;
      }
      .order-edit-meta {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 999px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        font-size: 12px;
        margin: 0 8px 8px 0;
      }
    </style>

    <div class="row g-3">

      <div class="col-lg-5">
        <div class="order-edit-card h-100">
          <div class="order-edit-card-header">Order Info</div>
          <div class="order-edit-card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered align-middle mb-0">
                <tbody>
                  <tr><th style="width:180px;">Service</th><td>{{ $serviceName }}</td></tr>
                  <tr><th>User</th><td>{{ $userEmail }}</td></tr>
                  <tr><th>{{ $deviceLabel }}</th><td>{{ $device !== '' ? $device : '—' }}</td></tr>

                  @if($hasServerFields)
                    <tr>
                      <th>Service Fields</th>
                      <td>
                        <div class="table-responsive">
                          <table class="table table-sm table-striped table-bordered mb-0">
                            <tbody>
                              @foreach($orderedFields as $input => $val)
                                @php
                                  $label = $cfLabelByInput[$input] ?? $input;
                                  $valClean = $cleanText($val);
                                @endphp
                                <tr>
                                  <th style="width:220px;">{{ $label }}</th>
                                  <td>{{ $valClean !== '' ? $valClean : '—' }}</td>
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      </td>
                    </tr>
                  @endif

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
        </div>

        @if($requestUid || $dispatchError)
          <div class="mt-3">
            @if($requestUid)
              <span class="order-edit-meta"><strong>Request UID:</strong> {{ $requestUid }}</span>
            @endif
            @if($dispatchError)
              <span class="order-edit-meta"><strong>Dispatch Error:</strong> {{ $dispatchError }}</span>
            @endif
          </div>
        @endif
      </div>

      <div class="col-lg-7">
        <div class="order-edit-card">
          <div class="order-edit-card-header">Reply Preview</div>
          <div class="order-edit-card-body">
            <div class="order-edit-preview mb-3">
              {!! $providerReplyHtml !!}
            </div>

            @if($resultText !== '')
              <details class="mb-3">
                <summary>Raw text (debug)</summary>
                <pre class="border rounded p-3 bg-light mb-0" style="white-space:pre-wrap;">{{ $resultText }}</pre>
              </details>
            @endif

            <div class="mb-3">
              <label class="form-label fw-semibold">Reply HTML</label>

              <textarea
                name="provider_reply_html"
                class="form-control"
                rows="12"
                data-editor="summernote"
                data-summernote-height="320"
              >{!! $providerReplyHtml !!}</textarea>

              <div class="form-text">
                الآن الرد يفتح داخل Summernote بدل Quill حتى تظهر الصورة والـ HTML كما وصلا من المزود ويمكن تعديلهما بسهولة.
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

    </div>
  </div>

  <div class="modal-footer border-0">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>