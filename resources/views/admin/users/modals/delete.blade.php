@php($u = $user)
<form class="modal-content js-ajax-form" action="{{ route('admin.users.destroy',$u) }}" method="POST">
  @csrf @method('DELETE')
  <div class="modal-header text-white" style="background:#dc3545">
    <h5 class="modal-title"><i class="fas fa-trash me-2"></i> Delete user</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    Are you sure you want to delete <strong>{{ $u->name }}</strong>?
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-danger" type="submit">Delete</button>
  </div>
</form>
