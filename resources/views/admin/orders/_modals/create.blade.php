<div class="modal-header">
  <h5 class="modal-title">Create order ({{ strtoupper($kind) }})</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <form id="orderCreateForm" action="{{ route($routePrefix.'.store') }}" method="POST">
    @csrf

    <div class="mb-3">
      <label class="form-label">User</label>
      <select name="user_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}">
            {{ $u->email }} — Balance: ${{ number_format((float)($u->balance ?? 0), 2) }}
          </option>
        @endforeach
      </select>
      <div class="form-text">اختر المستخدم أولاً</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Service</label>
      <select name="service_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($services as $s)
          <option value="{{ $s->id }}">
            {{ $s->option_label }}
          </option>
        @endforeach
      </select>
      <div class="form-text">عند اختيار الخدمة سيتم ضبط الحقول لاحقاً (Bulk/Custom) حسب الخدمة.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">
        Device ({{ $kind === 'imei' ? 'IMEI/SN' : 'Input' }})
      </label>
      <input type="text" name="device" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea name="comments" class="form-control" rows="4"></textarea>
    </div>

    <div class="alert alert-info mb-0">
      ملاحظة: إذا كانت الخدمة مرتبطة بـ API سيتم الإرسال تلقائياً (waiting → inprogress → success/rejected).
    </div>
  </form>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="button" class="btn btn-success" id="btnCreateOrder">Create</button>
</div>

<script>
(function(){
  const form = document.getElementById('orderCreateForm');
  const btn  = document.getElementById('btnCreateOrder');

  btn.addEventListener('click', async function(){
    btn.disabled = true;
    try{
      const fd = new FormData(form);
      const res = await fetch(form.action, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest'},
        body: fd
      });

      const data = await res.json().catch(()=>null);

      if(!res.ok){
        const msg = (data && (data.message || data.error)) || 'Failed to create order';
        window.showToast('danger', msg);
        btn.disabled = false;
        return;
      }

      window.showToast('success', 'Order created');
      // اغلق المودال واعمل ريفريش للجدول
      const modalEl = document.getElementById('ajaxModal');
      const m = window.bootstrap.Modal.getInstance(modalEl);
      if (m) m.hide();
      setTimeout(()=>window.location.reload(), 300);
    }catch(e){
      console.error(e);
      window.showToast('danger', 'Failed to create order');
      btn.disabled = false;
    }
  });
})();
</script>
