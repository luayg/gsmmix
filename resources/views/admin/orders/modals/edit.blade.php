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

  // Reply HTML (what we edit)
  $providerReplyHtml = (string)($resp['provider_reply_html'] ?? '');
  $providerReplyHtml = trim($providerReplyHtml);

  // Names
  $serviceName = $row?->service?->name ?? ($row->service_name ?? '—');
  $serviceName = $pickName($serviceName);

  $providerName = $row?->provider?->name ?? ($row->provider_name ?? '—');
  $providerName = $cleanText($providerName);

  // fields
  $userEmail = $cleanText($row?->email ?? ($row->user_email ?? '—'));
  $device    = $cleanText($row?->device ?? ($row->imei ?? '—'));
  $remoteId  = $cleanText($row?->remote_id ?? '—');
  $ip        = $cleanText($row?->ip ?? '—');

  $createdAt = optional($row?->created_at)->format('d/m/Y H:i:s') ?? '—';
  $repliedAt = optional($row?->replied_at)->format('d/m/Y H:i:s') ?? '—';

  $apiOrder  = ($row?->api_order ?? false) ? 'Yes' : 'No';

  // IMPORTANT: keep your original meaning:
  // - Order price: customer price (price)
  // - API processing price: provider cost (order_price)
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

      {{-- Left: info table --}}
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
              <tr><th>Final price</th><td>{{ $fmt($orderPrice) }}</td></tr>
              <tr><th>Profit</th><td>{{ $fmt($profit) }}</td></tr>

              <tr><th>Reply date</th><td>{{ $repliedAt }}</td></tr>

              <tr><th>API order ID</th><td>{{ $remoteId }}</td></tr>
              <tr><th>API name</th><td>{{ $providerName }}</td></tr>
              <tr><th>API processing price</th><td>{{ $fmt($apiCost) }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      {{-- Right: editor + status --}}
      <div class="col-lg-6">
        <div class="mb-2 fw-semibold">Reply</div>

        {{-- Summernote editor --}}
        <textarea
          name="provider_reply_html"
          class="form-control"
          rows="12"
          data-summernote="1"
          data-summernote-height="360"
        >{!! old('provider_reply_html', $providerReplyHtml) !!}</textarea>

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
