@extends('layouts.admin')
@section('title', 'Product orders')
@section('content')
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
    <h4 class="mb-0">Product Orders</h4>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <form method="GET" class="d-flex align-items-center gap-2 mb-0">
        <input type="hidden" name="q" value="{{ request('q') }}"><input type="hidden" name="status" value="{{ request('status') }}"><input type="hidden" name="provider" value="{{ request('provider') }}">
        <span class="text-muted small">Show</span><select name="per_page" class="form-select form-select-sm" style="width:110px" onchange="this.form.submit()">@foreach([10,25,50,75,100,500,1000] as $number)<option value="{{ $number }}" @selected($perPage===$number)>{{ $number }}</option>@endforeach</select><span class="text-muted small">items</span>
      </form>
      <div class="dropdown"><button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">Export</button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-product-export="copy">Copy</button></li><li><button class="dropdown-item" type="button" data-product-export="csv">CSV</button></li><li><button class="dropdown-item" type="button" data-product-export="print">Print</button></li></ul></div>
      <div class="dropdown"><button id="productReloadButton" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">Reload (Manual)</button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-product-reload="manual">Manual</button></li><li><button class="dropdown-item" type="button" data-product-reload="60">Every 1 minute</button></li><li><button class="dropdown-item" type="button" data-product-reload="300">Every 5 minutes</button></li><li><button class="dropdown-item" type="button" data-product-reload="600">Every 10 minutes</button></li></ul></div>
      @can('orders.create')<button class="btn btn-success btn-sm js-open-modal" data-url="{{ route('admin.orders.product.modal.create') }}">New order</button>@endcan
    </div>
  </div>

  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  <div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="col-md-4"><label class="form-label">Search</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="order ID / product / email / device"></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All</option>@foreach(['waiting','inprogress','success','rejected','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Provider</label><select class="form-select" name="provider"><option value="">All</option><option value="manual" @selected(request('provider')==='manual')>Manual</option>@foreach($apiProviders as $provider)<option value="api:{{ $provider->id }}" @selected(request('provider')==='api:'.$provider->id)>{{ $provider->name }}</option>@endforeach @foreach($localSources as $source)<option value="local:{{ $source->id }}" @selected(request('provider')==='local:'.$source->id)>{{ $source->name }} (Local)</option>@endforeach</select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary w-100">Apply</button><a class="btn btn-light w-100" href="{{ route('admin.orders.product.index',['per_page'=>$perPage]) }}">Reset</a></div>
  </form></div></div>

  <div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0" id="productOrdersTable">
    <thead><tr><th style="width:90px">ID</th><th style="width:170px">Date</th><th>Device</th><th>Product</th><th style="width:160px">Provider</th><th style="width:130px">Status</th><th style="width:110px">Credits</th><th style="width:160px">Actions</th></tr></thead>
    <tbody>@forelse($rows as $order)@php($status=strtolower($order->status ?? 'waiting'))<tr>
      <td>{{ $order->id }}</td><td>{{ $order->created_at?->format('Y-m-d H:i') }}</td><td>{{ $order->device ?: '—' }}</td><td>{{ $order->request['product_name'] ?? $order->product?->name ?? 'Historical product' }}<div class="small text-muted">{{ $order->user?->name ?? $order->email }}</div></td><td>{{ $order->admin_provider_label }}</td>
      <td>@if($status==='success')<span class="badge bg-success">SUCCESS</span>@elseif($status==='rejected')<span class="badge bg-danger">REJECTED</span>@elseif($status==='inprogress')<span class="badge bg-primary">IN PROGRESS</span>@elseif($status==='cancelled')<span class="badge bg-dark">CANCELLED</span>@else<span class="badge bg-warning text-dark">WAITING</span>@endif</td>
      <td>{{ $order->order_price }}</td><td class="text-nowrap"><a class="btn btn-sm btn-primary" href="{{ route('admin.orders.product.show',$order) }}">View</a> @can('orders.edit')<a class="btn btn-sm btn-warning" href="{{ route('admin.orders.product.show',$order) }}#update-order">Edit</a>@endcan</td>
    </tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">No product orders match these filters.</td></tr>@endforelse</tbody>
  </table></div><div class="mt-3 d-flex justify-content-center">{!! $rows->links('pagination::bootstrap-5') !!}</div></div></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const table = document.getElementById('productOrdersTable');
  const rows = () => Array.from(table.querySelectorAll('tr')).map(row => Array.from(row.querySelectorAll('th,td')).map(cell => cell.innerText.trim()));
  document.querySelectorAll('[data-product-export]').forEach(button => button.addEventListener('click', async () => {
    const action = button.dataset.productExport;
    if (action === 'copy') { await navigator.clipboard?.writeText(rows().map(row => row.join('\t')).join('\n')); return; }
    if (action === 'csv') { const csv=rows().map(row=>row.map(value=>`"${value.replaceAll('"','""')}"`).join(',')).join('\n'); const url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'})); const link=document.createElement('a'); link.href=url; link.download='product-orders.csv'; link.click(); URL.revokeObjectURL(url); return; }
    const popup=window.open('','_blank'); popup.document.write(`<html><head><title>Product orders</title><style>table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:7px;font:12px Arial}</style></head><body>${table.outerHTML}</body></html>`); popup.document.close(); popup.print();
  }));
  let timer; const reloadButton=document.getElementById('productReloadButton');
  const applyReload = value => { clearInterval(timer); localStorage.setItem('product_orders_reload',value); reloadButton.textContent=value==='manual'?'Reload (Manual)':`Reload (Every ${Number(value)/60} minute${value==='60'?'':'s'})`; if(value!=='manual') timer=setInterval(()=>location.reload(),Number(value)*1000); };
  document.querySelectorAll('[data-product-reload]').forEach(button=>button.addEventListener('click',()=>applyReload(button.dataset.productReload)));
  applyReload(localStorage.getItem('product_orders_reload') || 'manual');
});
</script>
@endpush
