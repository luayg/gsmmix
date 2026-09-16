@php
  $badge = match (strtolower((string) $order->status)) {
    'success' => 'bg-success', 'rejected' => 'bg-danger', 'inprogress' => 'bg-primary',
    'cancelled' => 'bg-dark', default => 'bg-warning text-dark',
  };
  $productName = data_get($order->request, 'product_name') ?: $order->product?->name ?: 'Historical product';
@endphp
<div class="modal-header border-0 pb-2">
  <div>
    <h5 class="modal-title mb-1">View Product Order #{{ $order->id }}</h5>
    <div class="text-muted small">{{ $productName }}</div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body pt-2">
  <div class="card border-0 bg-light mb-3"><div class="card-body">
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
      <tr><th style="width:220px">Product</th><td>{{ $productName }}</td></tr>
      <tr><th>Customer</th><td>{{ $order->user?->name ?: $order->email }}</td></tr>
      <tr><th>Status</th><td><span class="badge {{ $badge }}">{{ strtoupper($order->status ?: 'waiting') }}</span></td></tr>
      <tr><th>Price</th><td>{{ number_format((float) $order->order_price, 2) }} credits</td></tr>
      <tr><th>Device / target</th><td class="text-break">{{ $order->device ?: '—' }}</td></tr>
      <tr><th>Source</th><td>{{ $order->localSource?->name ?? ucfirst((string) ($order->product?->source_type ?: 'manual')) }}</td></tr>
      <tr><th>Generated service order</th><td>{{ $order->service_order_type ? strtoupper($order->service_order_type).' #'.$order->service_order_id : '—' }}</td></tr>
      <tr><th>Created</th><td>{{ optional($order->created_at)->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
      <tr><th>Delivered / closed</th><td>{{ optional($order->replied_at)->format('Y-m-d H:i:s') ?: '—' }}</td></tr>
    </tbody></table></div>
  </div></div>
  <h6>Delivery result</h6>
  <div class="border rounded bg-white p-3 text-break" style="white-space:pre-wrap">{{ $order->response ?: 'Awaiting delivery' }}</div>
  @if(!empty($order->request['internal_dispatch_note']) || !empty($order->request['internal_provider_note']))
    <div class="alert alert-warning mt-3 mb-0"><strong>Provider error:</strong> {{ $order->request['internal_dispatch_note'] ?? $order->request['internal_provider_note'] }}</div>
  @endif
  <h6 class="mt-3">Comments</h6><div class="text-break">{{ $order->comments ?: '—' }}</div>
</div>
<div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
