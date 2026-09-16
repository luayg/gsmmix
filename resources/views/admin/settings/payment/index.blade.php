@extends('layouts.admin')
@section('title', 'Payment settings')
@section('content')
@if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@php
$logos = ['paypal' => ['fab fa-paypal','text-primary'], 'binance_pay' => ['fas fa-coins','text-warning'], 'usdt' => ['fas fa-dollar-sign','text-success']];
@endphp
<div class="card mb-4">
  <div class="card-header bg-primary text-white"><i class="fas fa-bolt me-1"></i> Automatic payment gateways</div>
  <div class="card-body p-0">
    <div class="table-responsive"><table class="table table-striped align-middle mb-0">
      <thead><tr><th>Logo</th><th>Name</th><th>Tax</th><th>Fee</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>@foreach($automaticGateways as $gateway)
        <tr>
          <td style="width:90px"><i class="{{ $logos[$gateway->driver][0] ?? 'fas fa-credit-card' }} {{ $logos[$gateway->driver][1] ?? '' }} fa-2x"></i></td>
          <td><strong>{{ $gateway->name }}</strong><div class="small text-muted">{{ strtoupper(str_replace('_',' ', $gateway->driver)) }}</div></td>
          <td>{{ $gateway->tax_percent }}%</td><td>{{ $gateway->fixed_fee }} + {{ $gateway->percent_fee }}%</td>
          <td><span class="badge {{ $gateway->active ? 'bg-success' : 'bg-danger' }}">{{ $gateway->active ? 'Active' : 'Inactive' }}</span></td>
          <td class="text-end"><a class="btn btn-warning btn-sm" href="{{ route('admin.settings.payment.edit',$gateway) }}">Edit settings</a></td>
        </tr>
      @endforeach</tbody>
    </table></div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
    <span><i class="fas fa-hand-holding-usd me-1"></i> Manual payment methods</span>
    <a class="btn btn-light btn-sm" href="{{ route('admin.settings.payment.create') }}"><i class="fas fa-plus me-1"></i> Add manual method</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive"><table class="table table-striped align-middle mb-0">
      <thead><tr><th>Logo</th><th>Name</th><th>Tax</th><th>Fee</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>@forelse($manualGateways as $gateway)<tr>
        <td style="width:90px">@if($gateway->logo_path)<img src="{{ Storage::disk('public')->url($gateway->logo_path) }}" alt="" style="width:58px;height:36px;object-fit:contain">@else<i class="fas fa-university fa-2x text-secondary"></i>@endif</td>
        <td><strong>{{ $gateway->name }}</strong><div class="small text-muted">{{ $gateway->slug }} · {{ $gateway->transactions_count }} payments</div></td>
        <td>{{ $gateway->tax_percent }}%</td><td>{{ $gateway->fixed_fee }} + {{ $gateway->percent_fee }}%</td>
        <td><span class="badge {{ $gateway->active ? 'bg-success' : 'bg-danger' }}">{{ $gateway->active ? 'Active' : 'Inactive' }}</span></td>
        <td class="text-end text-nowrap"><a class="btn btn-warning btn-sm" href="{{ route('admin.settings.payment.edit',$gateway) }}">Edit settings</a>
          @if($gateway->transactions_count===0)<form method="POST" class="d-inline" action="{{ route('admin.settings.payment.destroy',$gateway) }}" onsubmit="return confirm('Delete this manual payment method?')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete</button></form>@endif
        </td>
      </tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">No manual payment methods. Use “Add manual method” to create one.</td></tr>@endforelse</tbody>
    </table></div>
  </div>
  @if($manualGateways->hasPages())<div class="card-footer">{{ $manualGateways->links() }}</div>@endif
</div>
@endsection
