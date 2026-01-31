<div class="modal-header">
  <h5 class="modal-title">Create order</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="post" action="{{ $storeUrl }}">
  @csrf

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">User</label>
      <select class="form-select" name="user_id">
        <option value="">Choose</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}">{{ $u->email }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Service</label>
      <select class="form-select" name="service_id" required>
        @foreach($services as $s)
          <option value="{{ $s->id }}">{{ $s->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">
        @if($kind==='imei') IMEI / Serial number
        @elseif($kind==='server') Device / Email / Code
        @else Device
        @endif
      </label>
      <input class="form-control" name="device" required>
    </div>

    @if($kind==='server')
      <div class="mb-3">
        <label class="form-label">Quantity</label>
        <input class="form-control" name="quantity" type="number" min="1" value="1">
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea class="form-control" name="comments" rows="3"></textarea>
    </div>

    <div class="alert alert-info mb-0">
      Status starts as <b>WAITING</b> ثم إذا كانت الخدمة مربوطة API سيتم الإرسال تلقائيًا ويصبح <b>INPROGRESS</b> أو <b>REJECTED</b>.
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button class="btn btn-success">Create</button>
  </div>
</form>
