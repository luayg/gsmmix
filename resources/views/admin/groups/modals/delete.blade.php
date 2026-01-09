<div class="modal-content">
  <form class="js-ajax-form" method="POST" action="{{ route('admin.groups.destroy', $group) }}">
    @csrf
    @method('DELETE')
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete group</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <p class="mb-0">Are you sure you want to delete group:
        <strong>{{ $group->name }}</strong> ?</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-danger" type="submit">Delete</button>
    </div>
  </form>
</div>
