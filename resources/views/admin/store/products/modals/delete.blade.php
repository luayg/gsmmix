<div class="modal-header bg-danger text-white">
  <h5 class="modal-title">{{ $product->name }} | Delete</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.products.destroy', $product) }}">
  @csrf
  @method('DELETE')

  <div class="modal-body">
    <p class="mb-2">Are you sure you want to delete this product?</p>
    <div class="alert alert-warning mb-0">
      Existing product orders will keep working, but their product link will become empty.
      Current orders: <strong>{{ $product->orders_count }}</strong>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-danger">Delete</button>
  </div>
</form>