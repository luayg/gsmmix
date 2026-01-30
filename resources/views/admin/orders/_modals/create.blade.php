<div class="modal-header">
  <h5 class="modal-title">Create order</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form data-ajax="create" action="{{ $storeUrl }}" method="post">
  @csrf
  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">User ID (optional)</label>
      <input class="form-control" name="user_id" placeholder="e.g. 1">
    </div>

    <div class="mb-3">
      <label class="form-label">Email (optional)</label>
      <input class="form-control" name="email" placeholder="user@email.com">
    </div>

    <div class="mb-3">
      <label class="form-label">Service</label>
      <select class="form-select" name="service_id" required>
        <option value="">Choose</option>
        @foreach($services as $s)
          <option value="{{ $s->id }}">
            {{ $s->name_json['en'] ?? $s->name ?? ('#'.$s->id) }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">{{ $deviceLabel }}</label>
      <input class="form-control" name="device" placeholder="{{ $devicePlaceholder }}">
    </div>

    @if($showQuantity)
      <div class="mb-3">
        <label class="form-label">Quantity</label>
        <input class="form-control" name="quantity" type="number" min="1" value="1">
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea class="form-control" name="comments" rows="3"></textarea>
    </div>

    <div class="alert alert-info mb-0">
      ✅ If service has supplier_id + remote_id → it will auto-send to provider and save provider response.
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button class="btn btn-success" type="submit">Create</button>
  </div>
</form>
