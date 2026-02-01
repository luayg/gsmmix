{{-- resources/views/admin/orders/modals/create.blade.php --}}

@php
  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) return $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
      return $v;
    }
    return (string)$v;
  };

  $pickPrice = function ($svc) {
    $p = $svc->price ?? $svc->sell_price ?? 0;
    return (float)$p;
  };

  $isFileKind = ($kind ?? '') === 'file';
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? 'Create order' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="post" action="{{ route($routePrefix.'.store') }}" enctype="multipart/form-data">
  @csrf

  <div class="modal-body">
    <div class="row g-3">

      <div class="col-12">
        <label class="form-label">User</label>
        <select class="form-select" name="user_id">
          <option value="">Choose</option>
          @foreach($users as $u)
            <option value="{{ $u->id }}">{{ $u->email }}</option>
          @endforeach
        </select>
        <div class="form-text">اختر المستخدم أو اتركه فارغ واكتب Email يدوي.</div>
      </div>

      <div class="col-12">
        <label class="form-label">User email (optional)</label>
        <input type="text" class="form-control" name="email" placeholder="user@email.com">
      </div>

      <div class="col-12">
        <label class="form-label">Service</label>
        <select class="form-select" name="service_id" required>
          <option value="">Choose</option>
          @foreach($services as $s)
            <option value="{{ $s->id }}">
              {{ $pickName($s->name) }} — ${{ number_format($pickPrice($s), 2) }}
            </option>
          @endforeach
        </select>
        <div class="form-text">عند اختيار الخدمة سيتم الإرسال تلقائياً إذا كانت مرتبطة بـ API.</div>
      </div>

      @if($isFileKind)
        <div class="col-12">
          <label class="form-label">Upload file</label>
          <input type="file" class="form-control" name="file" required>
          <div class="form-text">هذا الملف سيتم رفعه وتخزينه ثم إرساله للمزوّد تلقائياً إذا كانت الخدمة مرتبطة بـ API.</div>
        </div>
      @else
        <div class="col-12">
          <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
          <input type="text" class="form-control" name="device" required>
        </div>
      @endif

      @if(!empty($supportsQty))
      <div class="col-12">
        <label class="form-label">Quantity</label>
        <input type="number" class="form-control" name="quantity" min="1" value="1">
      </div>
      @endif

      <div class="col-12">
        <label class="form-label">Comments</label>
        <textarea class="form-control" name="comments" rows="3"></textarea>
      </div>

      <div class="col-12">
        <div class="alert alert-info mb-0">
          ملاحظة: إذا كانت الخدمة مرتبطة بـ API سيتم الإرسال تلقائياً (waiting → inprogress → success/rejected).
        </div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>
