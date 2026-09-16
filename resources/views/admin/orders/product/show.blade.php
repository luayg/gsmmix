@extends('layouts.admin')
@section('title', 'Product order #' . $order->id)
@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between mb-3"><h1 class="h4">Product order #{{ $order->id }}</h1><a href="{{ route('admin.orders.product.index') }}">All product orders</a></div>
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
  <div class="card mb-3"><div class="card-body">
    <dl class="row">
      <dt class="col-sm-3">Product</dt><dd class="col-sm-9">{{ $order->request['product_name'] ?? $order->product?->name ?? 'Historical product' }}</dd>
      <dt class="col-sm-3">Customer</dt><dd class="col-sm-9">{{ $order->user?->name ?? $order->email }}</dd>
      <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ ucfirst($order->status) }}</dd>
      <dt class="col-sm-3">Price</dt><dd class="col-sm-9">{{ $order->order_price }} credits</dd>
      <dt class="col-sm-3">Financial state</dt><dd class="col-sm-9">{{ $order->request['financial_state'] ?? 'Historical — review required' }}</dd>
      <dt class="col-sm-3">Source</dt><dd class="col-sm-9">{{ $order->localSource?->name ?? 'Manual' }}</dd>
      <dt class="col-sm-3">Generated service order</dt><dd class="col-sm-9">{{ $order->service_order_type ? strtoupper($order->service_order_type).' #'.$order->service_order_id : '—' }}</dd>
      <dt class="col-sm-3">Device</dt><dd class="col-sm-9">{{ $order->device ?: '—' }}</dd>
      <dt class="col-sm-3">Delivered / closed</dt><dd class="col-sm-9">{{ $order->replied_at ?? '—' }}</dd>
    </dl>
    <h2 class="h6">Delivery result</h2><pre class="text-wrap">{{ $order->response ?: 'Awaiting delivery' }}</pre>
    @if(!empty($order->request['internal_dispatch_note']) || !empty($order->request['internal_provider_note']))
      <div class="alert alert-warning">
        <strong>Provider error:</strong>
        {{ $order->request['internal_dispatch_note'] ?? $order->request['internal_provider_note'] }}
      </div>
    @endif
    <h2 class="h6">Comments</h2><p class="text-break">{{ $order->comments ?: '—' }}</p>
  </div></div>
  @can('orders.edit')
  <form method="POST" action="{{ route('admin.orders.product.update', $order) }}" class="card"><div class="card-body">
    @csrf @method('PUT')
    <h2 class="h5">Update order</h2>
    <label class="form-label" for="productOrderStatus">Status</label>
    <select id="productOrderStatus" name="status" class="form-select mb-3">
      @foreach(($order->status === 'success' || ($order->request['pipeline'] ?? '') !== 'manual_product_v1') ? [$order->status] : ['waiting','inprogress','success','rejected','cancelled'] as $status)
        <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ ucfirst($status) }}</option>
      @endforeach
    </select>
    @if($order->status !== 'success' && ($order->request['pipeline'] ?? '') === 'manual_product_v1')
      <label class="form-label" for="productOrderResult">Delivery result (required to complete)</label>
      <textarea id="productOrderResult" name="response" class="form-control mb-3" maxlength="20000">{{ old('response') }}</textarea>
    @endif
    <label class="form-label" for="productOrderNote">Comments</label>
    <textarea id="productOrderNote" name="comments" class="form-control mb-3" maxlength="5000">{{ old('comments', $order->comments) }}</textarea>
    <p class="text-muted small">Cancelling an undelivered order refunds its charge once. Reactivating it charges the original amount again. Delivered orders retain their result and stock assignment.</p>
    <button class="btn btn-primary" type="submit">Save changes</button>
  </div></form>
  @endcan
</div>
@endsection
