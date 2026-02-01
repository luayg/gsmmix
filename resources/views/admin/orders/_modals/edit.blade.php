@php
  $pretty = function ($v) {
    if (is_array($v)) return json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    if (is_string($v)) return $v;
    return (string)$v;
  };
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? ('Edit Order #'.$row->id) }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.update', $row->id) }}">
  @csrf

  <div class="modal-body">
    <div class="row g-3">

      <div class="col-12">
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <tbody>
              <tr><th style="width:220px">Service</th><td>{{ $row->service?->name ?? '—' }}</td></tr>
              <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
              <tr><th>Device</th><td>{{ $row->device }}</td></tr>
              <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
              <tr><th>API order ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" required>
          @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
            <option value="{{ $st }}" @selected($row->status===$st)>{{ ucfirst($st) }}</option>
          @endforeach
        </select>
        <div class="form-text">تغيير الحالة يدويًا لا يرسل API (الإرسال تلقائي عند create فقط).</div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Comments</label>
        <input type="text" class="form-control" name="comments" value="{{ $row->comments }}">
      </div>

      <div class="col-12">
        <label class="form-label">Response (JSON or text)</label>
        <textarea class="form-control" name="response" rows="6">{{ $pretty($row->response) }}</textarea>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
