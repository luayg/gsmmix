@php
  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) return $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
      return $v;
    }
    return (string)$v;
  };

  $uiMessage = function ($resp) {
    if (is_array($resp)) {
      $msg = $resp['message'] ?? null;
      $ref = $resp['reference_id'] ?? null;
      if ($msg && $ref) return $msg . " (Ref: {$ref})";
      if ($msg) return $msg;
      return json_encode($resp, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
    if (is_string($resp)) return $resp;
    return (string)$resp;
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
              <tr><th style="width:220px">Service</th><td>{{ $row->service ? $pickName($row->service->name) : '—' }}</td></tr>
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
        <label class="form-label">Provider reply (recommended)</label>
        <textarea class="form-control" rows="5" readonly>{{ $uiMessage($row->response) }}</textarea>
        <div class="form-text">
          هذه الرسالة مختصرة ومناسبة للعرض. التفاصيل الخام محفوظة في request/response_raw للتتبع.
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Response override (JSON or text) - optional</label>
        <textarea class="form-control" name="response" rows="6"></textarea>
        <div class="form-text">اتركه فارغاً إن لم ترد تعديل الرد يدوياً.</div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
