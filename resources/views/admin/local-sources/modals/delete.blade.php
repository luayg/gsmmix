<div class="modal-header bg-danger text-white">
  <h5 class="modal-title">{{ $source->name }} | Delete</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.sources.destroy', $source) }}">
  @csrf
  @method('DELETE')

  <div class="modal-body">
    <p class="mb-2">Are you sure you want to delete this source?</p>
    <div class="alert alert-warning mb-0">
      Existing replies linked to this source will keep working, but their source will become empty.
      Current replies: <strong>{{ $source->replies_count }}</strong>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-danger">Delete</button>
  </div>
</form>
