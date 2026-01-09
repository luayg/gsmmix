<div class="modal-content">
  <form class="js-ajax-form" method="POST" action="{{ route('admin.groups.store') }}">
    @csrf
    <div class="modal-header bg-success text-white">
      <h5 class="modal-title"><i class="fas fa-plus me-2"></i> Create group</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input name="name" class="form-control" required>
        <div class="form-text">Group name must be unique.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
      <button class="btn btn-primary" type="submit">Save</button>
    </div>
  </form>
</div>
