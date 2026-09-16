@php
  $productName = data_get($order->request, 'product_name') ?: $order->product?->name ?: 'Historical product';
  $manualOpen = $order->status !== 'success' && data_get($order->request, 'pipeline') === 'manual_product_v1';
  $statuses = ($order->status === 'success' || data_get($order->request, 'pipeline') !== 'manual_product_v1')
    ? [$order->status] : ['waiting','inprogress','success','rejected','cancelled'];
@endphp
<div class="modal-header border-0 text-white" style="background:#f39c12">
  <div><strong>Product Order #{{ $order->id }}</strong><span class="opacity-75"> | Edit</span></div>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form class="js-ajax-form" method="POST" action="{{ route('admin.orders.product.update', $order) }}">
  @csrf @method('PUT')
  <div class="modal-body">
    <div class="alert alert-light border"><strong>{{ $productName }}</strong><br><span class="small text-muted">{{ $order->user?->name ?: $order->email }} · {{ number_format((float) $order->order_price, 2) }} credits</span></div>
    <label class="form-label fw-semibold" for="productOrderStatus">Status</label>
    <select id="productOrderStatus" name="status" class="form-select mb-3" required>
      @foreach($statuses as $status)<option value="{{ $status }}" @selected($order->status === $status)>{{ ucfirst($status) }}</option>@endforeach
    </select>
    @if($manualOpen)
      <label class="form-label fw-semibold" for="productOrderResult">Delivery result <span class="text-danger">(required to complete)</span></label>
      <textarea id="productOrderResult" name="response" class="form-control mb-3" rows="6" maxlength="20000">{{ $order->response }}</textarea>
    @endif
    <label class="form-label fw-semibold" for="productOrderComments">Comments</label>
    <textarea id="productOrderComments" name="comments" class="form-control" rows="4" maxlength="5000">{{ $order->comments }}</textarea>
    <p class="text-muted small mt-3 mb-0">Cancelling an undelivered order refunds its charge once. Reactivating it charges the original amount again. Delivered orders retain their result and stock assignment.</p>
  </div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-success">Save changes</button></div>
</form>
