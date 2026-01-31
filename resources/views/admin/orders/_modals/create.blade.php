<div class="modal-header">
  <h5 class="modal-title">Create order</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="orderCreateForm">
  <div class="modal-body">

    <div class="mb-3">
      <label class="form-label">User email (optional)</label>
      <input type="text" class="form-control" name="email" placeholder="user@email.com">
    </div>

    @if(!empty($services))
      <div class="mb-3">
        <label class="form-label">Service</label>
        <select class="form-select" name="service_id" required>
          <option value="">Choose</option>
          @foreach($services as $s)
            <option value="{{ $s->id }}">{{ $s->name ?? ('#'.$s->id) }}</option>
          @endforeach
        </select>
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label">{{ $deviceLabel ?? 'Device' }}</label>
      <input type="text" class="form-control" name="device" required>
      <div class="form-text">سيتم الإرسال تلقائيًا للمزود إذا كانت الخدمة مرتبطة بـ API.</div>
    </div>

    @if(($kind ?? '') === 'server')
      <div class="mb-3">
        <label class="form-label">Quantity (optional)</label>
        <input type="number" class="form-control" name="quantity" min="1">
      </div>
    @endif

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea class="form-control" name="comments" rows="3"></textarea>
    </div>

  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>

@push('scripts')
<script>
(function(){
  const form = document.getElementById('orderCreateForm');
  if(!form) return;

  form.addEventListener('submit', async function(e){
    e.preventDefault();

    const fd = new FormData(form);

    try{
      const r = await fetch(@json(route($routePrefix.'.store')), {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': window.CSRF_TOKEN
        },
        body: fd
      });

      const j = await r.json();
      if(!r.ok || !j.ok){
        window.showToast('danger', (j.message || 'Failed'));
        return;
      }

      window.showToast('success', 'Order created');
      // اغلق المودال
      const modalEl = document.getElementById('ajaxModal');
      window.bootstrap?.Modal.getInstance(modalEl)?.hide();

      // ✅ ريفرش الصفحة لإظهار الطلب الجديد + تحديث الحالة بعد الإرسال التلقائي
      window.location.reload();
    }catch(err){
      console.error(err);
      window.showToast('danger', 'Failed');
    }
  });
})();
</script>
@endpush
