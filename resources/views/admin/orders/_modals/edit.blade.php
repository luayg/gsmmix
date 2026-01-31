<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.update', $row) }}">
  @csrf
  @method('PUT')

  <div class="modal-body">
    <table class="table table-sm">
      <tr><th style="width:180px">Service</th><td>{{ optional($row->service)->name_json['en'] ?? optional($row->service)->name ?? '—' }}</td></tr>
      <tr><th>User</th><td>{{ $row->email ?? optional($row->user)->email ?? '—' }}</td></tr>
      <tr><th>Device</th><td>{{ $row->device }}</td></tr>
      <tr><th>Provider</th><td>{{ optional($row->provider)->name ?? '—' }}</td></tr>
      <tr><th>Remote ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
    </table>

    <div class="mb-3">
      <label class="form-label">Reply</label>
      <textarea name="reply" class="form-control" rows="6">{{ old('reply', $row->response ?? '') }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
          <option value="{{ $st }}" @selected(($row->status ?? 'waiting')===$st)>{{ ucfirst($st) }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
