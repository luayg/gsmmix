@php
  // required:
  // $kind = 'imei'|'server'|'file'
  // $routePrefix = 'admin.orders.imei' (مثال)
  // $users, $services

  $decodeName = function ($value) {
      if (is_array($value)) {
          return $value['en'] ?? $value['fallback'] ?? (string)reset($value);
      }
      if (!is_string($value)) return (string)$value;

      $s = trim($value);
      if ($s !== '' && ($s[0] === '{' || $s[0] === '[')) {
          $j = json_decode($s, true);
          if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
              return $j['en'] ?? $j['fallback'] ?? (string)reset($j);
          }
      }
      return $value;
  };
@endphp

<div class="modal-header">
  <h5 class="modal-title">Create order</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <form id="orderCreateForm" action="{{ route($routePrefix.'.store') }}" method="POST">
    @csrf

    <div class="mb-3">
      <label class="form-label">User</label>
      <select name="user_id" id="user_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($users as $u)
          <option value="{{ $u->id }}">
            {{ $u->email ?? ('User#'.$u->id) }}
            @if(isset($u->balance))
              — Balance: ${{ number_format((float)$u->balance, 2) }}
            @endif
          </option>
        @endforeach
      </select>
      <div class="form-text text-muted">اختر المستخدم الذي سيتم إنشاء الطلب له.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Service</label>
      <select name="service_id" id="service_id" class="form-select" required>
        <option value="">Choose</option>
        @foreach($services as $s)
          @php
            $label = $decodeName($s->name ?? '');
            $price = (float)($s->price ?? 0);
            // هذه الحقول قد تختلف عندك - نضع defaults
            $fieldLabel = $s->main_field_label ?? ($kind === 'imei' ? 'Device (IMEI/SN)' : 'Device');
            $allowBulk  = (int)($s->allow_bulk_orders ?? 0);
          @endphp
          <option
            value="{{ $s->id }}"
            data-price="{{ $price }}"
            data-field-label="{{ e($fieldLabel) }}"
            data-allow-bulk="{{ $allowBulk }}"
          >
            {{ $label }} — ${{ number_format($price, 2) }}
          </option>
        @endforeach
      </select>
      <div class="form-text text-muted">عند اختيار الخدمة سيتم ضبط المدخلات تلقائياً.</div>
    </div>

    <div class="mb-3">
      <label class="form-label" id="deviceLabel">
        {{ $kind === 'imei' ? 'Device (IMEI/SN)' : 'Device' }}
      </label>
      <input type="text" name="device" id="device" class="form-control" required>
    </div>

    <div class="mb-3 d-none" id="bulkWrap">
      <label class="form-label">Bulk</label>
      <textarea name="bulk" id="bulk" class="form-control" rows="5" placeholder="كل سطر قيمة"></textarea>
      <div class="form-text text-muted">يتم إنشاء طلب لكل سطر إذا الخدمة تدعم bulk.</div>
    </div>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea name="comments" class="form-control" rows="4"></textarea>
    </div>

    <div class="alert alert-info py-2 small mb-0">
      ملاحظة: إذا كانت الخدمة مرتبطة بـ API سيتم الإرسال تلقائياً عند الحفظ (status: waiting → inprogress/failed).
    </div>
  </form>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="submit" class="btn btn-success" form="orderCreateForm">Create</button>
</div>

@push('scripts')
<script>
(function(){
  const form = document.getElementById('orderCreateForm');
  const svc  = document.getElementById('service_id');
  const lbl  = document.getElementById('deviceLabel');
  const bulkWrap = document.getElementById('bulkWrap');

  function refreshServiceUI(){
    const opt = svc.options[svc.selectedIndex];
    if(!opt) return;

    const fieldLabel = opt.getAttribute('data-field-label') || 'Device';
    const allowBulk  = opt.getAttribute('data-allow-bulk') === '1';

    lbl.textContent = fieldLabel;

    // أظهر bulk فقط إذا الخدمة تسمح
    bulkWrap.classList.toggle('d-none', !allowBulk);
  }

  svc.addEventListener('change', refreshServiceUI);
  refreshServiceUI();

  // submit via fetch (منع التحويل لصفحة ثانية)
  form.addEventListener('submit', async function(e){
    e.preventDefault();

    const url = form.getAttribute('action');
    const fd  = new FormData(form);

    try{
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': window.CSRF_TOKEN },
        body: fd
      });

      const data = await res.json().catch(()=>null);

      if(!res.ok || !data || data.ok !== true){
        const msg = (data && data.message) ? data.message : 'Failed to create order.';
        window.showToast('danger', msg);
        return;
      }

      // close modal + reload list
      const modalEl = document.getElementById('ajaxModal');
      const modal = window.bootstrap?.Modal.getInstance(modalEl);
      if(modal) modal.hide();

      window.showToast('success', data.message || 'Order created.');
      // reload الصفحة ليظهر الطلب
      setTimeout(()=>window.location.reload(), 300);
    }catch(err){
      console.error(err);
      window.showToast('danger', 'Network error.');
    }
  });
})();
</script>
@endpush
