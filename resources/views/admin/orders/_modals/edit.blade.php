@php
  $svcName = \App\Http\Controllers\Admin\Orders\BaseOrdersController::serviceNameText($order->service?->name);
@endphp

<div class="modal-header">
  <h5 class="modal-title">Order #{{ $order->id }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="POST" action="{{ route($routePrefix.'.update', $order->id) }}">
  @csrf
  @method('PUT')

  <div class="modal-body">

    <div class="mb-3">
      <label class="form-label">Service</label>
      <input class="form-control" value="{{ $svcName }}" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label">Device</label>
      <input class="form-control" value="{{ $order->device }}" disabled>
    </div>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea name="comments" class="form-control" rows="3">{{ $order->comments }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        @foreach($statuses as $st)
          <option value="{{ $st }}" @selected($order->status === $st)>{{ ucfirst($st) }}</option>
        @endforeach
      </select>
      <div class="form-text">تغيير الحالة يدوياً لا يعني “إرسال” — الإرسال التلقائي يتم عند الإنشاء إذا API.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Response (raw)</label>
      <textarea name="response" class="form-control" rows="6">{{ $order->response }}</textarea>
    </div>

  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
