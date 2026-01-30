<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form data-ajax="update" action="{{ $updateUrl }}" method="post">
  @csrf
  @method('PUT')

  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-6">
        <table class="table table-sm">
          <tr><th style="width:180px">Service</th><td>{{ $row->service?->name_json['en'] ?? $row->service?->name ?? $row->service_id }}</td></tr>
          <tr><th>User</th><td>{{ $row->email ?: ('#'.$row->user_id) }}</td></tr>
          <tr><th>Device</th><td>{{ $row->device }}</td></tr>
          <tr><th>Order date</th><td>{{ optional($row->created_at)->format('Y-m-d H:i:s') }}</td></tr>
          <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
          <tr><th>API order ID</th><td>{{ $row->remote_id ?: '—' }}</td></tr>
        </table>

        <div class="mb-3">
          <label class="form-label">Comments</label>
          <textarea class="form-control" name="comments" rows="4">{{ $row->comments }}</textarea>
        </div>
      </div>

      <div class="col-md-6">
        <div class="mb-3">
          <label class="form-label">Reply (manual edit allowed)</label>
          <textarea class="form-control" name="response" rows="10">{{ $row->response }}</textarea>
          <div class="form-text">إذا تريد تكتب Reply يدوي وتغيّر الحالة يدوي.</div>
        </div>

        <div class="mb-3">
          <label class="form-label">Status</label>
          <select class="form-select" name="status" required>
            @foreach(['WAITING','INPROGRESS','SUCCESS','FAILED','MANUAL','REJECTED','CANCELLED'] as $s)
              <option value="{{ $s }}" @selected($row->status===$s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>

        <div class="alert alert-light border mb-0">
          <div class="fw-bold mb-1">Parsed provider message</div>
          <div>{{ $parsed['message'] ?: '—' }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button class="btn btn-success" type="submit">Save</button>
  </div>
</form>
