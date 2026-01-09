<div class="modal-content">
  <form class="js-ajax-form" method="POST" action="{{ route('admin.groups.update', $group) }}">
    @csrf
    @method('PUT')
    <div class="modal-header bg-warning">
      <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Edit group</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="name" class="form-control" value="{{ $group->name }}" required>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
      <button class="btn btn-primary" type="submit">Update</button>
    </div>
  </form>
</div>
