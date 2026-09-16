@extends('layouts.admin')
@section('title', 'Review manual payment')
@section('content')
<div class="row g-4"><div class="col-xl-7"><div class="card h-100"><div class="card-header bg-primary text-white d-flex justify-content-between"><strong>Manual payment #{{ $payment->id }}</strong><span class="badge {{ $payment->status==='paid'?'bg-success':($payment->status==='rejected'?'bg-danger':'bg-warning text-dark') }}">{{ ucfirst($payment->status) }}</span></div><div class="card-body">
  @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <dl class="row mb-0"><dt class="col-sm-4">Customer</dt><dd class="col-sm-8">{{ $payment->user?->name }} · {{ $payment->user?->email }} (#{{ $payment->user_id }})</dd><dt class="col-sm-4">Method</dt><dd class="col-sm-8">{{ $payment->gateway?->name }}</dd><dt class="col-sm-4">Customer pays</dt><dd class="col-sm-8">{{ $payment->payable_currency }} {{ $payment->currency_code }}</dd><dt class="col-sm-4">Balance credit</dt><dd class="col-sm-8"><strong>{{ $payment->amount_base }} {{ $payment->currency_code }}</strong></dd><dt class="col-sm-4">Reference</dt><dd class="col-sm-8 text-break">{{ $payment->uuid }}</dd><dt class="col-sm-4">Submitted</dt><dd class="col-sm-8">{{ $payment->created_at }}</dd>@if($payment->approver)<dt class="col-sm-4">Reviewed by</dt><dd class="col-sm-8">{{ $payment->approver->name }} · {{ data_get($payment->metadata,'manual_review.reviewed_at') }}</dd>@endif @if(data_get($payment->metadata,'manual_review.reason'))<dt class="col-sm-4">Rejection reason</dt><dd class="col-sm-8 text-danger">{{ data_get($payment->metadata,'manual_review.reason') }}</dd>@endif</dl>
  @if($payment->status === 'review')<hr><div class="row g-3"><div class="col-md-6"><form method="POST" action="{{ route('admin.finances.payment-reviews.approve',$payment) }}" data-review-confirm="approve">@csrf<label class="form-label">Bank / transfer reference (optional)</label><input class="form-control mb-2" name="reference" value="{{ old('reference') }}" maxlength="190"><label class="form-label">Internal note (optional)</label><textarea class="form-control mb-3" name="note" rows="3" maxlength="1000">{{ old('note') }}</textarea><button class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Approve and add balance</button></form></div><div class="col-md-6"><form method="POST" action="{{ route('admin.finances.payment-reviews.reject',$payment) }}" data-review-confirm="reject">@csrf<label class="form-label">Reason shown to customer</label><textarea class="form-control mb-3" name="reason" rows="5" maxlength="1000" required>{{ old('reason') }}</textarea><button class="btn btn-outline-danger w-100"><i class="fas fa-times me-1"></i>Reject payment</button></form></div></div>@endif
  <a class="btn btn-outline-secondary mt-3" href="{{ route('admin.finances.payment-reviews.index') }}">Back to review queue</a>
</div></div></div><div class="col-xl-5"><div class="card"><div class="card-header"><strong>Transfer receipt</strong></div><div class="card-body text-center">@if($payment->proof_path)<a href="{{ route('admin.finances.payment-reviews.proof',$payment) }}" target="_blank" rel="noopener"><img class="img-fluid rounded border" style="max-height:620px" src="{{ route('admin.finances.payment-reviews.proof',$payment) }}" alt="Transfer receipt"></a><div class="small text-muted mt-2">Click the receipt to open it at full size.</div>@else<div class="alert alert-danger mb-0">No receipt was attached. Do not approve before verifying payment.</div>@endif</div></div></div></div>

@if($payment->status === 'review')
<div class="modal fade" id="paymentReviewConfirmModal" tabindex="-1" aria-labelledby="paymentReviewConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="paymentReviewConfirmTitle">Confirm payment review</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="alert mb-0" data-confirm-alert><i class="fas fa-exclamation-triangle me-2"></i><span data-confirm-message></span></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn" data-confirm-submit>Confirm</button></div>
  </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const element = document.getElementById('paymentReviewConfirmModal');
  if (!element || !window.bootstrap) return;
  const modal = new bootstrap.Modal(element);
  const title = element.querySelector('[data-confirm-message]');
  const alert = element.querySelector('[data-confirm-alert]');
  const confirmButton = element.querySelector('[data-confirm-submit]');
  let pendingForm = null;
  document.querySelectorAll('form[data-review-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.confirmed === '1') return;
      event.preventDefault();
      pendingForm = form;
      const approve = form.dataset.reviewConfirm === 'approve';
      title.textContent = approve
        ? 'Approve this payment and credit the customer balance?'
        : 'Reject this payment without changing the customer balance?';
      alert.className = 'alert mb-0 ' + (approve ? 'alert-success' : 'alert-danger');
      confirmButton.className = 'btn ' + (approve ? 'btn-success' : 'btn-danger');
      confirmButton.textContent = approve ? 'Approve payment' : 'Reject payment';
      modal.show();
    });
  });
  confirmButton.addEventListener('click', function () {
    if (!pendingForm) return;
    pendingForm.dataset.confirmed = '1';
    modal.hide();
    pendingForm.requestSubmit();
  });
});
</script>
@endif
@endsection
