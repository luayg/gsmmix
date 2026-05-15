<div class="modal-header bg-success text-white">
  <h5 class="modal-title">Create category</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.categories.store') }}">
  @csrf

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" placeholder="Name" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Ordering</label>
      <input type="number" name="ordering" class="form-control" value="0" min="0">
    </div>

    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" name="active" value="1" id="categoryActiveCreate" checked>
      <label class="form-check-label" for="categoryActiveCreate">Active</label>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>
