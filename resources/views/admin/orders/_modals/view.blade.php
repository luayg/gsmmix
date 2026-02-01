@php
  $resp = $row->response;
  $req  = $row->request;

  $pretty = function ($v) {
    if (is_array($v)) return json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    if (isه is_string($v)) return $v;
    return (string)$v;
  };
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? ('View Order #'.$row->id) }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <div class="table-responsive">
    <table class="table table-bordered">
      <tbody>
        <tr><th style="width:220px">Service</th><td>{{ $row->service?->name ?? '—' }}</td></tr>
        <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
        <tr><th>Device</th><td>{{ $row->device }}</td></tr>
        <tr><th>Order date</th><td>{{ optional($row->created_at)->format('Y-m-d H:i:s') }}</td></tr>
        <tr><th>Order IP</th><td>{{ $row->ip ?? '—' }}</td></tr>
        <tr><th>Ordered via API</th><td>{{ $row->api_order ? 'Yes' : 'No' }}</td></tr>
        <tr><th>Status</th><td><span class="badge bg-secondary">{{ strtoupper($row->status) }}</span></td></tr>
        <tr><th>API name</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
        <tr><th>API order ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
        <tr><th>Price</th><td>${{ number_format((float)$row->price, 2) }}</td></tr>
        <tr><th>Provider cost</th><td>${{ number_format((float)$row->order_price, 2) }}</td></tr>
        <tr><th>Profit</th><td>${{ number_format((float)$row->profit, 2) }}</td></tr>
        <tr><th>Comments</th><td>{{ $row->comments ?: '—' }}</td></tr>
      </tbody>
    </table>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Request (saved)</label>
      <pre class="p-3 bg-light border rounded" style="max-height:260px;overflow:auto">{{ $pretty($req) }}</pre>
    </div>
    <div class="col-md-6">
      <label class="form-label">Response</label>
      <pre class="p-3 bg-light border rounded" style="max-height:260px;overflow:auto">{{ $pretty($resp) }}</pre>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
