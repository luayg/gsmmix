<div class="modal-header bg-success text-white">
  <h5 class="modal-title">Create product</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.products.store') }}">
  @csrf

  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Category</label>
        <select name="product_category_id" class="form-select">
          <option value="">None</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}">{{ $category->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Local source</label>
        <select name="local_source_id" class="form-select">
          <option value="">Manual</option>
          @foreach($sources as $source)
            <option value="{{ $source->id }}">{{ $source->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>

      <div class="col-12">
        <label class="form-label">Alias</label>
        <input type="text" name="alias" class="form-control" placeholder="auto-generated if empty">
      </div>

      <div class="col-md-6">
        <label class="form-label">Cost</label>
        <input type="number" step="0.01" min="0" name="cost" class="form-control" value="0">
      </div>

      <div class="col-md-6">
        <label class="form-label">Price</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="0" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Ordering</label>
        <input type="number" min="0" name="ordering" class="form-control" value="0">
      </div>

      <div class="col-md-6 d-flex align-items-end gap-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="active" value="1" id="productActiveCreate" checked>
          <label class="form-check-label" for="productActiveCreate">Active</label>
        </div>

        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="device_based" value="1" id="productDeviceBasedCreate">
          <label class="form-check-label" for="productDeviceBasedCreate">Device based</label>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4"></textarea>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>