<div class="modal-header bg-warning text-white">
  <h5 class="modal-title">{{ $category->name }} | Edit</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.categories.update', $category) }}">
  @csrf
  @method('PUT')

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="{{ $category->name }}" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Ordering</label>
      <input type="number" name="ordering" class="form-control" value="{{ $category->ordering }}" min="0">
    </div>

    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" name="active" value="1" id="categoryActiveEdit" @checked($category->active)>
      <label class="form-check-label" for="categoryActiveEdit">Active</label>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>