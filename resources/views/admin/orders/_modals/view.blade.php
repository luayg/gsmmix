<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | View</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <table class="table table-sm">
    <tr><th style="width:220px">Service</th><td>{{ $row->service?->name_json['en'] ?? $row->service?->name ?? $row->service_id }}</td></tr>
    <tr><th>User</th><td>{{ $row->email ?: ('#'.$row->user_id) }}</td></tr>
    <tr><th>Device</th><td>{{ $row->device }}</td></tr>
    <tr><th>Order date</th><td>{{ optional($row->created_at)->format('Y-m-d H:i:s') }}</td></tr>
    <tr><th>Order IP</th><td>{{ $row->ip }}</td></tr>
    <tr><th>Ordered via API</th><td>{{ $row->api_order ? 'Yes' : 'No' }}</td></tr>
    <tr><th>Status</th><td><span class="badge bg-secondary">{{ $row->status }}</span></td></tr>
    <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
    <tr><th>API order ID</th><td>{{ $row->remote_id ?: '—' }}</td></tr>
    <tr><th>Reply date</th><td>{{ optional($row->replied_at)->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
    <tr><th>Parsed message</th><td>{{ $parsed['message'] ?: '—' }}</td></tr>
  </table>

  <hr>

  <h6 class="mb-2">Provider response (raw)</h6>
  <pre class="bg-light p-2 rounded" style="max-height:300px; overflow:auto;">{{ json_encode($parsed['raw'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
