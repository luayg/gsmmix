<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | View</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <table class="table table-sm">
    <tr><th style="width:180px">Service</th><td>{{ optional($row->service)->name_json['en'] ?? optional($row->service)->name ?? '—' }}</td></tr>
    <tr><th>User</th><td>{{ $row->email ?? optional($row->user)->email ?? '—' }}</td></tr>
    <tr><th>Device</th><td>{{ $row->device }}</td></tr>
    <tr><th>Provider</th><td>{{ optional($row->provider)->name ?? '—' }}</td></tr>
    <tr><th>Status</th><td>{{ strtoupper($row->status ?? 'waiting') }}</td></tr>
    <tr><th>Remote ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
    <tr><th>Created</th><td>{{ optional($row->created_at)->format('Y-m-d H:i:s') }}</td></tr>
    <tr><th>Replied at</th><td>{{ optional($row->replied_at)->format('Y-m-d H:i:s') ?? '—' }}</td></tr>
  </table>

  <div class="mt-3">
    <div class="fw-bold mb-2">Last Response (raw)</div>
    <pre class="bg-light p-3 border rounded" style="white-space:pre-wrap">{{ $row->response ?? '' }}</pre>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
