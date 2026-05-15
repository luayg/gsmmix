<div class="modal-header bg-danger text-white">
  <h5 class="modal-title">Reply #{{ $reply->id }} | Delete</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.replies.destroy', $reply) }}">
  @csrf
  @method('DELETE')

  <div class="modal-body">
    <p class="mb-2">Are you sure you want to delete this reply?</p>

    @if($reply->used_by_product_order_id)
      <div class="alert alert-warning mb-0">
        This reply is linked to product order #{{ $reply->used_by_product_order_id }}.
      </div>
    @else
      <div class="p-2 bg-light border rounded" style="white-space:pre-wrap">{{ $reply->reply }}</div>
    @endif
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-danger">Delete</button>
  </div>
</form>