@php
  $parsedType = $parsed['type'] ?? 'none';
  $parsedMsg  = $parsed['message'] ?? '';
  $raw        = $parsed['raw'] ?? null;
@endphp

<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | View</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <table class="table table-bordered">
    <tr><th style="width:220px;">Service</th><td>{{ $row->service?->name ?? '—' }}</td></tr>
    <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
    <tr><th>Device</th><td>{{ $row->device ?? '—' }}</td></tr>
    <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
    <tr><th>Status</th><td><b>{{ strtoupper($row->status) }}</b></td></tr>
    <tr><th>Provider Ref</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
    <tr><th>Created</th><td>{{ $row->created_at }}</td></tr>
  </table>

  <div class="mt-3">
    <h6 class="mb-2">Provider reply (parsed)</h6>

    @if($parsedType === 'error')
      <div class="alert alert-danger mb-0">{{ $parsedMsg }}</div>
    @elseif($parsedType === 'success')
      <div class="alert alert-success mb-0">
        {{ $parsedMsg }}
        @if(!empty($parsed['reference']))
          <div class="small">REFERENCEID: {{ $parsed['reference'] }}</div>
        @endif
      </div>
    @else
      <div class="alert alert-secondary mb-0">No parsed reply yet.</div>
    @endif
  </div>

  <div class="mt-3">
    <h6 class="mb-2">Raw response</h6>
    <pre class="p-2 bg-light border" style="max-height:260px; overflow:auto;">{{ json_encode($raw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
