@extends('layouts.admin')
@section('title', 'Product Orders')
@section('content')
@php
  $q=request('q',''); $status=request('status',''); $provider=request('provider','');
  $badge=fn($value)=>match(strtolower((string)$value)){'success'=>'bg-success','rejected'=>'bg-danger','inprogress'=>'bg-primary','cancelled'=>'bg-dark',default=>'bg-warning text-dark'};
@endphp
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
    <h4 class="mb-0">Product Orders</h4>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <form method="GET" class="d-flex align-items-center gap-2 mb-0">
        <input type="hidden" name="q" value="{{ $q }}"><input type="hidden" name="status" value="{{ $status }}"><input type="hidden" name="provider" value="{{ $provider }}">
        <span class="text-muted small">Show</span><select name="per_page" class="form-select form-select-sm" style="width:110px" onchange="this.form.submit()">@foreach([10,25,50,75,100,500,1000] as $count)<option value="{{ $count }}" @selected($perPage===$count)>{{ $count }}</option>@endforeach</select><span class="text-muted small">items</span>
      </form>
      <div class="dropdown"><button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">Export</button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-export="copy">Copy</button></li><li><button class="dropdown-item" data-export="csv">CSV</button></li><li><button class="dropdown-item" data-export="print">Print</button></li></ul></div>
      <div class="dropdown"><button class="btn btn-light btn-sm dropdown-toggle" id="productReloadButton" data-bs-toggle="dropdown">Reload (Manual)</button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" data-reload="manual">Manual</button></li><li><button class="dropdown-item" data-reload="60">Every 1 minute</button></li><li><button class="dropdown-item" data-reload="300">Every 5 minutes</button></li><li><button class="dropdown-item" data-reload="600">Every 10 minutes</button></li></ul></div>
      @can('orders.create')<button class="btn btn-success btn-sm js-open-modal" data-url="{{ route('admin.orders.product.modal.create') }}">New order</button>@endcan
    </div>
  </div>
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  <div class="card mb-3"><div class="card-body"><form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="col-md-4"><label class="form-label">Search</label><input class="form-control" name="q" value="{{ $q }}" placeholder="order id / product / device / email"></div>
    <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All</option>@foreach(['waiting','inprogress','success','rejected','cancelled'] as $item)<option value="{{ $item }}" @selected($status===$item)>{{ ucfirst($item) }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Provider</label><select class="form-select" name="provider"><option value="">All</option>@foreach($providers as $item)<option value="{{ $item->id }}" @selected((string)$provider===(string)$item->id)>{{ $item->name }}</option>@endforeach</select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary w-100">Apply</button><a class="btn btn-light w-100" href="{{ route('admin.orders.product.index',['per_page'=>$perPage]) }}">Reset</a></div>
  </form></div></div>
  <div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0" id="productOrdersTable">
    <thead><tr><th>ID</th><th>Date</th><th>Device</th><th>Product</th><th>Provider</th><th>Customer</th><th>Status</th><th>Credits</th><th>Actions</th></tr></thead>
    <tbody>@forelse($rows as $order)<tr>
      <td>{{ $order->id }}</td><td>{{ optional($order->created_at)->format('Y-m-d H:i') }}</td><td>{{ $order->device ?: '—' }}</td>
      <td>{{ data_get($order->request,'product_name') ?: $order->product?->name ?: 'Historical product' }}</td><td>{{ $order->provider_name }}</td><td>{{ $order->user?->name ?: $order->email }}</td>
      <td><span class="badge {{ $badge($order->status) }}">{{ strtoupper($order->status ?: 'waiting') }}</span></td><td>{{ number_format((float)$order->order_price,2) }}</td>
      <td class="text-nowrap"><a class="btn btn-sm btn-primary" href="{{ route('admin.orders.product.show',$order) }}">View</a> @can('orders.edit')<a class="btn btn-sm btn-warning" href="{{ route('admin.orders.product.show',$order) }}#manage-order">Edit</a>@endcan</td>
    </tr>@empty<tr><td colspan="9" class="text-center text-muted py-4">No product orders</td></tr>@endforelse</tbody>
  </table></div><div class="mt-3 d-flex justify-content-center">{!! $rows->links('pagination::bootstrap-5') !!}</div></div></div>
</div>
<script>
(function(){
 const table=document.getElementById('productOrdersTable'),text=()=>Array.from(table?.querySelectorAll('tr')||[]).map(r=>Array.from(r.querySelectorAll('th,td')).map(c=>(c.innerText||'').trim()).join('\t')).join('\n');
 document.querySelectorAll('[data-export]').forEach(b=>b.addEventListener('click',function(){const t=this.dataset.export;if(t==='copy'){navigator.clipboard?.writeText(text());return}if(t==='csv'){const csv=Array.from(table.querySelectorAll('tr')).map(r=>Array.from(r.querySelectorAll('th,td')).map(c=>'"'+(c.innerText||'').trim().replace(/"/g,'""')+'"').join(',')).join('\n'),u=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'})),a=document.createElement('a');a.href=u;a.download='product-orders.csv';a.click();URL.revokeObjectURL(u);return}const w=window.open('','_blank');w.document.write('<style>table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:6px}</style>'+table.outerHTML);w.document.close();w.print();w.close()}));
 let timer=null;const button=document.getElementById('productReloadButton');function reload(v){if(timer)clearInterval(timer);timer=null;localStorage.setItem('product_orders_reload',v);button.textContent=v==='manual'?'Reload (Manual)':'Reload (Every '+Number(v)/60+' minute'+(Number(v)>60?'s':'')+')';if(v!=='manual')timer=setInterval(()=>location.reload(),Number(v)*1000)}document.querySelectorAll('[data-reload]').forEach(b=>b.addEventListener('click',()=>reload(b.dataset.reload)));reload(localStorage.getItem('product_orders_reload')||'manual');
})();
</script>
@endsection
