@php
  $kindTitle = $title ?? 'Orders';
@endphp

<div class="modal-header">
  <h5 class="modal-title">Create order — {{ $kindTitle }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="POST" action="{{ route($routePrefix.'.store') }}">
  @csrf

  <div class="modal-body">

    <div class="mb-3">
      <label class="form-label">User</label>
      <select name="user_id" class="form-select">
        <option value="">Choose</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}">
            {{ $u->email }} — Balance: {{ number_format((float)($u->balance ?? 0), 2) }}
          </option>
        @endforeach
      </select>
      <div class="form-text">اختياري. إذا لم تختَر user يمكنك كتابة email يدوياً.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">User email (optional)</label>
      <input type="email" name="email" class="form-control" placeholder="user@email.com" value="{{ old('email') }}">
    </div>

    <div class="mb-3">
      <label class="form-label">Service</label>
      <select name="service_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($services as $s)
          @php
            $nm = \App\Http\Controllers\Admin\Orders\BaseOrdersController::serviceNameText($s->name);
            $price = (float)($s->price ?? $s->credits ?? $s->cost ?? 0);
            $suffix = $price > 0 ? (' — ' . number_format($price, 2)) : '';
          @endphp
          <option value="{{ $s->id }}">{{ $nm }}{{ $suffix }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Device (IMEI/SN)</label>
      <input type="text" name="device" class="form-control" required value="{{ old('device') }}">
    </div>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea name="comments" class="form-control" rows="4">{{ old('comments') }}</textarea>
    </div>

    <div class="alert alert-info mb-0">
      ملاحظة: عند حفظ الطلب، سيتم حفظه أولاً (status: waiting). إذا كانت الخدمة مربوطة API سيتم تحويله تلقائياً إلى inprogress ثم لاحقاً success/rejected حسب رد المزود.
    </div>

  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>
