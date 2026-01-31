<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="post" action="{{ $updateUrl }}">
  @csrf
  @method('PUT')

  <div class="modal-body">
    <table class="table table-bordered">
      <tr><th style="width:220px;">Service</th><td>{{ $row->service?->name ?? '—' }}</td></tr>
      <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
      <tr><th>Device</th><td>{{ $row->device ?? '—' }}</td></tr>
      <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
      <tr><th>Provider Ref</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
    </table>

    <div class="mb-3">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" required>
        @foreach($statuses as $st)
          <option value="{{ $st }}" @selected($row->status===$st)>{{ ucfirst($st) }}</option>
        @endforeach
      </select>
      <div class="form-text">
        الحالات فقط: waiting / inprogress / success / rejected / cancelled
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea class="form-control" name="comments" rows="3">{{ $row->comments }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Raw response (editable if you want)</label>
      <textarea class="form-control" name="response" rows="6">{{ $row->response }}</textarea>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button class="btn btn-success">Save</button>
  </div>
</form>
