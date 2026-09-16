@extends('layouts.admin')
@section('title', 'Review manual payment')
@section('content')
<div class="row g-4"><div class="col-xl-7"><div class="card h-100"><div class="card-header bg-primary text-white d-flex justify-content-between"><strong>Manual payment #{{ $payment->id }}</strong><span class="badge {{ $payment->status==='paid'?'bg-success':($payment->status==='rejected'?'bg-danger':'bg-warning text-dark') }}">{{ ucfirst($payment->status) }}</span></div><div class="card-body">
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <dl class="row mb-0"><dt class="col-sm-4">Customer</dt><dd class="col-sm-8">{{ $payment->user?->name }} · {{ $payment->user?->email }} (#{{ $payment->user_id }})</dd><dt class="col-sm-4">Method</dt><dd class="col-sm-8">{{ $payment->gateway?->name }}</dd><dt class="col-sm-4">Customer pays</dt><dd class="col-sm-8">{{ $payment->payable_currency }} {{ $payment->currency_code }}</dd><dt class="col-sm-4">Balance credit</dt><dd class="col-sm-8"><strong>{{ $payment->amount_base }} {{ $payment->currency_code }}</strong></dd><dt class="col-sm-4">Reference</dt><dd class="col-sm-8 text-break">{{ $payment->uuid }}</dd><dt class="col-sm-4">Submitted</dt><dd class="col-sm-8">{{ $payment->created_at }}</dd>@if($payment->approver)<dt class="col-sm-4">Reviewed by</dt><dd class="col-sm-8">{{ $payment->approver->name }} · {{ data_get($payment->metadata,'manual_review.reviewed_at') }}</dd>@endif @if(data_get($payment->metadata,'manual_review.reason'))<dt class="col-sm-4">Rejection reason</dt><dd class="col-sm-8 text-danger">{{ data_get($payment->metadata,'manual_review.reason') }}</dd>@endif</dl>
  @if($payment->status === 'review')<hr><div class="row g-3"><div class="col-md-6"><form method="POST" action="{{ route('admin.finances.payment-reviews.approve',$payment) }}" data-payment-confirm="approve">@csrf<label class="form-label">Bank / transfer reference (optional)</label><input class="form-control mb-2" name="reference" value="{{ old('reference') }}" maxlength="190"><label class="form-label">Internal note (optional)</label><textarea class="form-control mb-3" name="note" rows="3" maxlength="1000">{{ old('note') }}</textarea><button class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Approve and add balance</button></form></div><div class="col-md-6"><form method="POST" action="{{ route('admin.finances.payment-reviews.reject',$payment) }}" data-payment-confirm="reject">@csrf<label class="form-label">Reason shown to customer</label><textarea class="form-control mb-3" name="reason" rows="5" maxlength="1000" required>{{ old('reason') }}</textarea><button class="btn btn-outline-danger w-100"><i class="fas fa-times me-1"></i>Reject payment</button></form></div></div>@endif
  <a class="btn btn-outline-secondary mt-3" href="{{ route('admin.finances.payment-reviews.index') }}">Back to review queue</a>
</div></div></div><div class="col-xl-5"><div class="card"><div class="card-header"><strong>Transfer receipt</strong></div><div class="card-body text-center">@if($payment->proof_path)<a href="{{ route('admin.finances.payment-reviews.proof',$payment) }}" target="_blank" rel="noopener"><img class="img-fluid rounded border" style="max-height:620px" src="{{ route('admin.finances.payment-reviews.proof',$payment) }}" alt="Transfer receipt"></a><div class="small text-muted mt-2">Click the receipt to open it at full size.</div>@else<div class="alert alert-danger mb-0">No receipt was attached. Do not approve before verifying payment.</div>@endif</div></div></div></div>
@endsection

@if($payment->status === 'review')
@push('modals')
<div class="modal fade" id="paymentConfirmModal" tabindex="-1" aria-labelledby="paymentConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
    <div class="modal-header border-0 bg-light px-4 pt-4"><div><div class="text-primary small fw-bold text-uppercase">Manual payment</div><h5 class="modal-title fw-bold" id="paymentConfirmTitle">Confirm payment review</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body px-4 py-3"><div class="d-flex gap-3 align-items-start"><div id="paymentConfirmIcon" class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px"></div><div><p id="paymentConfirmMessage" class="mb-1 fw-semibold"></p><p id="paymentConfirmHint" class="small text-muted mb-0"></p></div></div></div>
    <div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button><button type="button" id="paymentConfirmSubmit" class="btn px-4">Confirm</button></div>
  </div></div>
</div>
@endpush
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalElement = document.getElementById('paymentConfirmModal');
  const modal = new window.bootstrap.Modal(modalElement);
  const title = document.getElementById('paymentConfirmTitle');
  const message = document.getElementById('paymentConfirmMessage');
  const hint = document.getElementById('paymentConfirmHint');
  const icon = document.getElementById('paymentConfirmIcon');
  const confirmButton = document.getElementById('paymentConfirmSubmit');
  let pendingForm = null;
  document.querySelectorAll('[data-payment-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (form.dataset.confirmed === '1') return;
    event.preventDefault();
    if (!form.reportValidity()) return;
    pendingForm = form;
    const approve = form.dataset.paymentConfirm === 'approve';
    title.textContent = approve ? 'Approve manual payment' : 'Reject manual payment';
    message.textContent = approve ? 'Add {{ $payment->amount_base }} {{ $payment->currency_code }} to this customer balance?' : 'Reject this payment without changing the customer balance?';
    hint.textContent = approve ? 'The receipt will be marked paid and a finance transaction will be recorded.' : 'The reason you entered will be visible to the customer.';
    icon.className = `rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ${approve ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'}`;
    icon.innerHTML = `<i class="fas ${approve ? 'fa-check' : 'fa-times'} fs-4"></i>`;
    confirmButton.className = `btn px-4 ${approve ? 'btn-success' : 'btn-danger'}`;
    confirmButton.textContent = approve ? 'Approve and add balance' : 'Reject payment';
    modal.show();
  }));
  confirmButton.addEventListener('click', () => {
    if (!pendingForm) return;
    pendingForm.dataset.confirmed = '1';
    confirmButton.disabled = true;
    confirmButton.textContent = 'Processing…';
    pendingForm.requestSubmit();
  });
  modalElement.addEventListener('hidden.bs.modal', () => { pendingForm = null; confirmButton.disabled = false; });
});
</script>
@endpush
@endif
