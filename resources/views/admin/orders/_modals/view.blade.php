@php
  $req = $row->request;
  $res = $row->response;
@endphp

<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | View</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <dl class="row mb-0">
    <dt class="col-sm-3">Service</dt>
    <dd class="col-sm-9">{{ optional($row->service)->name ?? '—' }}</dd>

    <dt class="col-sm-3">Provider</dt>
    <dd class="col-sm-9">{{ optional($row->provider)->name ?? '—' }}</dd>

    <dt class="col-sm-3">{{ $deviceLabel ?? 'Device' }}</dt>
    <dd class="col-sm-9">{{ $row->device }}</dd>

    <dt class="col-sm-3">Status</dt>
    <dd class="col-sm-9"><span class="badge bg-primary">{{ strtoupper($row->status ?? 'waiting') }}</span></dd>

    <dt class="col-sm-3">API order</dt>
    <dd class="col-sm-9">{{ (int)($row->api_order ?? 0) === 1 ? 'Yes' : 'No' }}</dd>

    <dt class="col-sm-3">Remote ID</dt>
    <dd class="col-sm-9">{{ $row->remote_id ?: '—' }}</dd>

    <dt class="col-sm-3">Comments</dt>
    <dd class="col-sm-9">{{ $row->comments ?: '—' }}</dd>
  </dl>

  <hr>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="fw-bold mb-2">Request</div>
      <pre class="bg-light p-3 border rounded" style="white-space:pre-wrap;">{{ is_string($req) ? $req : json_encode($req, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
    <div class="col-md-6">
      <div class="fw-bold mb-2">Response</div>
      <pre class="bg-light p-3 border rounded" style="white-space:pre-wrap;">{{ is_string($res) ? $res : json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
    </div>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
