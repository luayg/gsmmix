@extends('layouts.admin')
@section('title', 'Product orders')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4">Product orders</h1>
    @can('orders.create')<a class="btn btn-primary" href="{{ route('admin.orders.product.create') }}">Create order</a>@endcan
  </div>
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <input class="form-control w-auto" name="q" value="{{ request('q') }}" aria-label="Search" placeholder="Product, email or device">
    <select class="form-select w-auto" name="status" aria-label="Status">
      <option value="">All statuses</option>
      @foreach(['waiting','inprogress','success','rejected','cancelled'] as $status)
        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
      @endforeach
    </select>
    <button class="btn btn-outline-primary">Filter</button>
    <a class="btn btn-light" href="{{ route('admin.orders.product.index') }}">Reset</a>
  </form>
  <div class="card"><div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr><th>Order</th><th>Product</th><th>Customer</th><th>Status</th><th>Credits</th><th>Created</th></tr></thead>
      <tbody>
      @forelse($rows as $order)
        <tr>
          <td><a href="{{ route('admin.orders.product.show', $order) }}">#{{ $order->id }}</a></td>
          <td>{{ $order->request['product_name'] ?? $order->product?->name ?? 'Historical product' }}</td>
          <td>{{ $order->user?->name ?? $order->email }}</td>
          <td>{{ ucfirst($order->status) }}</td><td>{{ $order->order_price }}</td><td>{{ $order->created_at }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No product orders match these filters.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div><div class="card-footer">{{ $rows->links() }}</div></div>
</div>
@endsection
