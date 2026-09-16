@extends('layouts.customer')
@section('title', 'Payment details')

@section('content')
<div class="row justify-content-center"><div class="col-xl-8"><div class="gsm-card p-4 p-lg-5">
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  <div class="d-flex justify-content-between align-items-start gap-3"><div><div class="text-muted small">PAYMENT REFERENCE</div><h1 class="h4 fw-bold text-break">{{ $payment->uuid }}</h1></div><span id="paymentStatus" class="status-pill status-{{ $payment->status }}">{{ ucfirst($payment->status) }}</span></div>
  <hr>
  <div class="row g-4">
    <div class="col-md-6"><div class="text-muted small">Method</div><div class="fw-bold">{{ $payment->gateway?->name }}</div></div>
    <div class="col-md-6"><div class="text-muted small">Total</div><div class="fw-bold">{{ $payment->payable_currency }} {{ $payment->currency_code }}</div></div>
    @if($payment->status === 'review')
      <div class="col-12"><div class="alert alert-warning mb-0"><strong>Receipt submitted successfully.</strong> Your manual payment is waiting for administrator review. Your balance will be credited after approval.</div></div>
    @elseif($payment->status === 'paid')
      <div class="col-12"><div class="alert alert-success mb-0"><strong>Payment approved.</strong> The funds were added to your balance.</div></div>
    @elseif($payment->status === 'rejected')
      <div class="col-12"><div class="alert alert-danger mb-0"><strong>Payment rejected.</strong> {{ data_get($payment->metadata, 'manual_review.reason', 'Please contact support if you need more information.') }}</div></div>
    @endif
    @if($payment->gateway?->driver === 'usdt')
      <div class="col-12"><div class="alert alert-primary"><div class="small">Send exactly</div><div class="h3 fw-bold">{{ data_get($payment->metadata, 'provider_order.amount', $payment->payable_currency) }} USDT</div><div><strong>Network:</strong> {{ data_get($payment->metadata, 'provider_order.network') }}</div><div class="text-break"><strong>Address:</strong> {{ data_get($payment->metadata, 'provider_order.address') }}</div><div class="small mt-2">The payment is confirmed automatically after the required blockchain confirmations.</div></div></div>
    @elseif(!$payment->gateway?->is_system)
      <div class="col-12"><div class="alert alert-info mb-0"><strong>Payment instructions</strong><div class="mt-2" style="white-space: pre-wrap">{{ data_get($payment->metadata, 'payment_details') ?: $payment->gateway?->instructions }}</div></div></div>
    @endif
  </div>
  <div class="d-flex gap-2 mt-4"><a class="btn btn-outline-secondary" href="{{ route('customer.payments.index') }}">Payment history</a>@if($payment->status !== 'paid')<a class="btn btn-gsm" href="{{ route('customer.payments.create') }}">Start another payment</a>@endif</div>
</div></div></div>
@endsection

@if(in_array($payment->status, ['pending', 'review'], true))
@push('scripts')
<script>
setInterval(async () => {
  const response = await fetch(@json(route('customer.payments.status', $payment)), {headers: {Accept: 'application/json'}});
  if (!response.ok) return;
  const payment = await response.json();
  if (payment.status !== @json($payment->status)) location.reload();
}, 10000);
</script>
@endpush
@endif
