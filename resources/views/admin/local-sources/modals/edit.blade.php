<div class="modal-header bg-warning text-white">
  <h5 class="modal-title">{{ $source->name }} | Edit</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.sources.update', $source) }}">
  @csrf
  @method('PUT')

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" value="{{ $source->name }}" required>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
