<div class="modal-header">
  <h5 class="modal-title">Create order</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.store') }}">
  @csrf

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">User</label>
      <select name="user_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}">{{ $u->email }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Service</label>
      <select name="service_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($services as $s)
          <option value="{{ $s->id }}">
            {{ $s->name_json['en'] ?? $s->name ?? ('#'.$s->id) }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Device / IMEI / SN</label>
      <input type="text" name="device" class="form-control" required>
      <div class="form-text">IMEI orders: IMEI or Serial حسب الخدمة.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea name="comments" class="form-control" rows="3"></textarea>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>
