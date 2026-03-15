{{-- resources/views/admin/orders/modals/view.blade.php --}}

@php
  $row = $row ?? ($order ?? null);
  $kind = $kind ?? 'imei';

  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && isset($s[0]) && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) return $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
      return $v;
    }
    return (string)$v;
  };

  $clean = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($v);
  };

  $badge = [
    'waiting'    => 'bg-secondary',
    'inprogress' => 'bg-info',
    'success'    => 'bg-success',
    'rejected'   => 'bg-danger',
    'cancelled'  => 'bg-dark',
  ][$row->status ?? 'waiting'] ?? 'bg-secondary';

  $resp = $row->response;
  if (is_string($resp)) {
    $decoded = json_decode($resp, true);
    if (is_array($decoded)) $resp = $decoded;
  }
  if (!is_array($resp)) $resp = [];

  $items = $resp['result_items'] ?? [];
  if (!is_array($items)) $items = [];

  $img = $resp['result_image'] ?? null;
  $providerReplyHtml = (string)($resp['provider_reply_html'] ?? '');
  $resultText = (string)($resp['result_text'] ?? '');
  $resultMessage = (string)($resp['message'] ?? '—');

  $isSafeImg = function ($url) {
    if (!is_string($url)) return false;
    $u = trim($url);
    return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };

  // -------- params (fields/required) --------
  $params = $row->params ?? null;
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

  $hasServerFields = ($kind === 'server' && count($orderedFields) > 0);

  $renderValue = function ($label, $value) use ($clean) {
    $labelL = mb_strtolower($clean($label));
    $val = $clean($value);
    $valL = mb_strtolower($val);

    if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
    if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';

    if (str_contains($labelL, 'find my') || str_contains($labelL, 'fmi')) {
      if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
      if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';
    }

    if (str_contains($labelL, 'icloud')) {
      if (str_contains($valL, 'lost')) return '<span class="badge bg-danger">'.e($val).'</span>';
      if (str_contains($valL, 'clean')) return '<span class="badge bg-success">'.e($val).'</span>';
      return '<span class="badge bg-secondary">'.e($val).'</span>';
    }

    if ($valL === 'activated') return '<span class="badge bg-success">Activated</span>';
    if ($valL === 'expired')   return '<span class="badge bg-danger">Expired</span>';
    if ($valL === 'unlocked')  return '<span class="badge bg-success">Unlocked</span>';
    if ($valL === 'clean')     return '<span class="badge bg-success">Clean</span>';
    if (str_contains($valL, 'lost')) return '<span class="badge bg-danger">'.e($val).'</span>';

    return e($val);
  };

  $currency = $row->currency ?? $row->curr ?? 'USD';
  $orderPrice = $row->price ?? null;
  $profit = $row->profit ?? null;
  $apiProcessingPrice = $row->service?->cost ?? $row->order_price ?? null;

  $fmtMoney = function ($v) use ($currency) {
    if ($v === null || $v === '') return '—';
    if (is_numeric($v)) return number_format((float)$v, 2) . ' ' . $currency;
    return (string)$v . ' ' . $currency;
  };

  if ($profit === null && is_numeric($orderPrice) && is_numeric($apiProcessingPrice)) {
    $profit = (float)$orderPrice - (float)$apiProcessingPrice;
  }

  $deviceLabel = match($kind) {
    'imei'   => 'IMEI / Serial',
    'file'   => 'File',
    'server' => 'Device',
    default  => 'Device',
  };

  $reqMeta = $params['request'] ?? null;
  $rawMeta = $params['response_raw'] ?? null;

  if ($reqMeta === null && is_array($row->request ?? null)) {
    $reqMeta = ($row->request['request'] ?? null);
    $rawMeta = ($row->request['response_raw'] ?? null);
  }

  $requestMeta = is_array($row->request ?? null) ? $row->request : [];
  $requestUid = $requestMeta['request_uid'] ?? null;
  $dispatchError = $requestMeta['dispatch_error'] ?? null;
  $refundReason = $requestMeta['refunded_reason'] ?? null;
  $refundedAt = $requestMeta['refunded_at'] ?? null;
  $rechargedAt = $requestMeta['recharged_at'] ?? null;
  $rechargedReason = $requestMeta['recharged_reason'] ?? null;
@endphp

<div class="modal-header border-0 pb-2">
  <div>
    <h5 class="modal-title mb-1">{{ $title ?? ('View Order #'.$row->id) }}</h5>
    <div class="text-muted small">
      {{ $row->provider?->name ?? 'No provider' }} · {{ strtoupper($kind) }} Order
    </div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body pt-2">
  <style>
    .order-view-card {
      border: 1px solid #e9ecef;
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 4px 16px rgba(0,0,0,.04);
    }
    .order-view-label {
      width: 220px;
      background: #f8f9fa;
      font-weight: 600;
      white-space: nowrap;
    }
    .order-result-box {
      border: 1px solid #e9ecef;
      border-radius: 14px;
      background: #fff;
      overflow: hidden;
    }
    .order-result-header {
      padding: 12px 16px;
      border-bottom: 1px solid #e9ecef;
      background: #f8f9fa;
      font-weight: 700;
    }
    .order-result-body {
      padding: 16px;
    }
    .order-view-img-wrap {
      text-align: center;
      border: 1px solid #e9ecef;
      border-radius: 12px;
      background: #fcfcfd;
      padding: 16px;
    }
    .order-view-img-wrap img {
      max-width: 100%;
      max-height: 300px;
      border-radius: 10px;
    }
    .order-debug-pre {
      white-space: pre-wrap;
      word-break: break-word;
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 12px;
      padding: 14px;
      margin: 0;
    }
    .order-meta-badge {
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

    <div class="col-12">
      <div class="order-view-card">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0">
            <tbody>
              <tr>
                <th class="order-view-label">Status</th>
                <td><span class="badge {{ $badge }}">{{ strtoupper($row->status) }}</span></td>
              </tr>
              <tr>
                <th class="order-view-label">Created at</th>
                <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
              </tr>
              <tr>
                <th class="order-view-label">Service</th>
                <td>{{ $row->service ? $pickName($row->service->name) : '—' }}</td>
              </tr>
              <tr>
                <th class="order-view-label">{{ $deviceLabel }}</th>
                <td>{{ $row->device ?? '—' }}</td>
              </tr>

              @if($hasServerFields)
                <tr>
                  <th class="order-view-label">Service Fields</th>
                  <td>
                    <div class="table-responsive">
                      <table class="table table-sm table-striped table-bordered mb-0">
                        <tbody>
                          @foreach($orderedFields as $input => $val)
                            @php
                              $label = $cfLabelByInput[$input] ?? $input;
                              $v = $clean($val);
                            @endphp
                            <tr>
                              <th style="width:240px">{{ $label }}</th>
                              <td>{{ $v !== '' ? $v : '—' }}</td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              @endif

              <tr>
                <th class="order-view-label">User</th>
                <td>{{ $row->email ?? '—' }}</td>
              </tr>
              <tr>
                <th class="order-view-label">Provider</th>
                <td>{{ $row->provider?->name ?? '—' }}</td>
              </tr>
              <tr>
                <th class="order-view-label">Remote ID</th>
                <td>{{ $row->remote_id ?? '—' }}</td>
              </tr>

              @if(!empty($row->ip))
                <tr>
                  <th class="order-view-label">Order IP</th>
                  <td>{{ $row->ip }}</td>
                </tr>
              @endif

              <tr>
                <th class="order-view-label">Order price</th>
                <td>{{ $fmtMoney($orderPrice) }}</td>
              </tr>
              <tr>
                <th class="order-view-label">API processing price</th>
                <td>{{ $fmtMoney($apiProcessingPrice) }}</td>
              </tr>
              <tr>
                <th class="order-view-label">Profit</th>
                <td>{{ $fmtMoney($profit) }}</td>
              </tr>
              <tr>
                <th class="order-view-label">Comments</th>
                <td>{{ $row->comments ?: '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="order-result-box">
        <div class="order-result-header">Result</div>
        <div class="order-result-body">

          @if($img && $isSafeImg($img))
            <div class="order-view-img-wrap mb-3">
              <img src="{{ $img }}" alt="Result image" class="img-fluid">
            </div>
          @endif

          @if(!empty($providerReplyHtml))
            <div class="border rounded-3 p-3 bg-white">
              {!! $providerReplyHtml !!}
            </div>
          @elseif(is_array($items) && count($items))
            <div class="table-responsive">
              <table class="table table-sm table-striped table-bordered mb-0">
                <tbody>
                  @foreach($items as $it)
                    @php
                      $label = $it['label'] ?? '';
                      $value = $it['value'] ?? '';
                    @endphp

                    @if(trim($label) === '')
                      <tr>
                        <td colspan="2">{!! $renderValue('', $value) !!}</td>
                      </tr>
                    @else
                      <tr>
                        <th style="width:240px">{{ $label }}</th>
                        <td>{!! $renderValue($label, $value) !!}</td>
                      </tr>
                    @endif
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <div class="border rounded-3 p-3 bg-light">
              {{ $resultMessage !== '' ? $resultMessage : '—' }}
            </div>
          @endif
        </div>
      </div>
    </div>

    @if($requestUid || $dispatchError || $refundReason || $refundedAt || $rechargedAt || $rechargedReason)
      <div class="col-12">
        <div class="order-result-box">
          <div class="order-result-header">Order Meta</div>
          <div class="order-result-body">
            @if($requestUid)
              <span class="order-meta-badge"><strong>Request UID:</strong> {{ $requestUid }}</span>
            @endif
            @if($dispatchError)
              <span class="order-meta-badge"><strong>Dispatch Error:</strong> {{ $dispatchError }}</span>
            @endif
            @if($refundReason)
              <span class="order-meta-badge"><strong>Refund Reason:</strong> {{ $refundReason }}</span>
            @endif
            @if($refundedAt)
              <span class="order-meta-badge"><strong>Refunded At:</strong> {{ $refundedAt }}</span>
            @endif
            @if($rechargedReason)
              <span class="order-meta-badge"><strong>Recharged Reason:</strong> {{ $rechargedReason }}</span>
            @endif
            @if($rechargedAt)
              <span class="order-meta-badge"><strong>Recharged At:</strong> {{ $rechargedAt }}</span>
            @endif
          </div>
        </div>
      </div>
    @endif

    @if(!empty($reqMeta) || !empty($rawMeta) || $resultText !== '')
      <div class="col-12">
        <details class="order-view-card p-3">
          <summary><strong>Provider Debug (last request/response)</strong></summary>

          @if(!empty($reqMeta))
            <div class="mt-3">
              <div class="fw-bold mb-2">Request</div>
              <pre class="order-debug-pre">{{ json_encode($reqMeta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
          @endif

          @if(!empty($rawMeta))
            <div class="mt-3">
              <div class="fw-bold mb-2">Response Raw</div>
              <pre class="order-debug-pre">{{ is_string($rawMeta) ? $rawMeta : json_encode($rawMeta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
          @endif

          @if($resultText !== '')
            <div class="mt-3">
              <div class="fw-bold mb-2">Raw text (debug)</div>
              <pre class="order-debug-pre">{{ $resultText }}</pre>
            </div>
          @endif
        </details>
      </div>
    @endif

  </div>
</div>

<div class="modal-footer border-0 pt-0">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>