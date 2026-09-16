@extends('layouts.admin')
@section('title', 'Manual payment reviews')
@section('content')
<div class="card"><div class="card-header bg-primary text-white d-flex justify-content-between align-items-center"><strong><i class="fas fa-receipt me-2"></i>Manual payment reviews</strong><span class="badge bg-warning text-dark">{{ $reviewCount }} awaiting review</span></div><div class="card-body">
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  <div class="d-flex gap-2 mb-3 flex-wrap">@foreach(['review'=>'Awaiting review','paid'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $value=>$label)<a class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.finances.payment-reviews.index',['status'=>$value]) }}">{{ $label }}</a>@endforeach</div>
  <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Reference</th><th>Customer</th><th>Method</th><th>Amount</th><th>Receipt</th><th>Status</th><th>Submitted</th><th></th></tr></thead><tbody>
  @forelse($payments as $payment)<tr><td><span class="text-break">{{ $payment->uuid }}</span></td><td>{{ $payment->user?->name }}<div class="small text-muted">{{ $payment->user?->email }} · #{{ $payment->user_id }}</div></td><td>{{ $payment->gateway?->name }}</td><td><strong>{{ number_format((float)$payment->amount_base,2) }}</strong> {{ $payment->currency_code }}</td><td><span class="badge {{ $payment->proof_path?'bg-success':'bg-danger' }}">{{ $payment->proof_path?'Attached':'Missing' }}</span></td><td><span class="badge {{ $payment->status==='paid'?'bg-success':($payment->status==='rejected'?'bg-danger':'bg-warning text-dark') }}">{{ ucfirst($payment->status) }}</span></td><td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td><td><a class="btn btn-sm btn-primary" href="{{ route('admin.finances.payment-reviews.show',$payment) }}">Review</a></td></tr>
  @empty<tr><td colspan="8" class="text-center text-muted py-4">No manual payments match this status.</td></tr>@endforelse
  </tbody></table></div>{{ $payments->links() }}
</div></div>
@endsection
