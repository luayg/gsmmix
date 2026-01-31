{{-- resources/views/admin/orders/_modals/create.blade.php --}}
@php
  // توقع المتغيرات القادمة من الكنترولر:
  // $kind (imei|server|file|product)
  // $routePrefix مثال: admin.orders.imei
  // $users: Collection<User>
  // $services: array of ['id','label','price','allow_bulk']
@endphp

<div class="modal-header">
  <h5 class="modal-title">Create order ({{ strtoupper($kind ?? '') }})</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="orderCreateForm"
      method="post"
      action="{{ route($routePrefix . '.store') }}"
      class="modal-body">
  @csrf

  {{-- User --}}
  <div class="mb-3">
    <label class="form-label">User</label>
    <select name="user_id" id="userSelect" class="form-select">
      <option value="">— Manual (use email) —</option>
      @foreach(($users ?? []) as $u)
        <option value="{{ $u->id }}">
          {{ $u->email }}{{ $u->name ? ' — '.$u->name : '' }}
        </option>
      @endforeach
    </select>
    <div class="form-text">اختر مستخدم أو اتركها Manual واكتب Email.</div>
  </div>

  {{-- Manual email --}}
  <div class="mb-3" id="emailWrap">
    <label class="form-label">User email (optional)</label>
    <input type="email" name="email" class="form-control" placeholder="user@email.com" />
  </div>

  {{-- Service --}}
  <div class="mb-3">
    <label class="form-label">Service</label>
    <select name="service_id" id="serviceSelect" class="form-select" required>
      <option value="">Choose</option>
      @foreach(($services ?? []) as $s)
        <option value="{{ $s['id'] }}"
                data-price="{{ (float)$s['price'] }}"
                data-allow-bulk="{{ !empty($s['allow_bulk']) ? 1 : 0 }}">
          {{ $s['label'] }} — ${{ number_format((float)$s['price'], 2) }}
        </option>
      @endforeach
    </select>
  </div>

  {{-- Device / Serial / IMEI --}}
  <div class="mb-3">
    <label class="form-label">
      @if(($kind ?? '') === 'server')
        Device / Email / Code
      @elseif(($kind ?? '') === 'file')
        Device / Serial
      @else
        Device (IMEI/SN)
      @endif
    </label>
    <input type="text" name="device" class="form-control" required />
  </div>

  {{-- Bulk --}}
  <div class="mb-3 d-none" id="bulkWrap">
    <label class="form-label">Bulk</label>
    <textarea name="bulk" class="form-control" rows="4" placeholder="One item per line"></textarea>
    <div class="form-text">يظهر فقط إذا الخدمة تدعم bulk.</div>
  </div>

  {{-- Comments --}}
  <div class="mb-3">
    <label class="form-label">Comments</label>
    <textarea name="comments" class="form-control" rows="3"></textarea>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success" id="btnCreateOrder">Create</button>
  </div>
</form>

@push('scripts')
<script>
(function(){
  const form = document.getElementById('orderCreateForm');
  const userSelect = document.getElementById('userSelect');
  const emailWrap  = document.getElementById('emailWrap');
  const serviceSelect = document.getElementById('serviceSelect');
  const bulkWrap = document.getElementById('bulkWrap');

  function toggleEmail(){
    const hasUser = !!userSelect.value;
    emailWrap.classList.toggle('d-none', hasUser);
    if (hasUser) {
      const emailInput = emailWrap.querySelector('input[name="email"]');
      if (emailInput) emailInput.value = '';
    }
  }

  function toggleBulk(){
    const opt = serviceSelect.options[serviceSelect.selectedIndex];
    const allowBulk = opt && opt.getAttribute('data-allow-bulk') === '1';
    bulkWrap.classList.toggle('d-none', !allowBulk);
    if (!allowBulk) {
      const bulk = bulkWrap.querySelector('textarea[name="bulk"]');
      if (bulk) bulk.value = '';
    }
  }

  userSelect?.addEventListener('change', toggleEmail);
  serviceSelect?.addEventListener('change', toggleBulk);

  toggleEmail();
  toggleBulk();

  // Submit via AJAX so modal stays consistent
  form.addEventListener('submit', async function(e){
    e.preventDefault();

    const url = form.getAttribute('action');
    const fd = new FormData(form);

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': window.CSRF_TOKEN || ''
        },
        body: fd
      });

      const txt = await res.text();
      let json = null;
      try { json = JSON.parse(txt); } catch(_) {}

      if (!res.ok) {
        // Laravel validation غالبًا يرجع HTML/JSON
        window.showToast?.('danger', 'Create failed (HTTP ' + res.status + ')');
        console.error(txt);
        return;
      }

      if (json && json.ok) {
        window.showToast?.('success', 'Order created');
        // اغلق المودال
        const modalEl = document.getElementById('ajaxModal');
        const modal = window.bootstrap?.Modal.getOrCreateInstance(modalEl);
        modal?.hide();
        // حدّث الصفحة لإظهار الطلب
        window.location.reload();
        return;
      }

      // إذا رجع HTML بدل JSON
      window.showToast?.('warning', 'Created, but unexpected response. Reloading…');
      window.location.reload();
    } catch (err) {
      console.error(err);
      window.showToast?.('danger', 'Create failed (network error)');
    }
  });
})();
</script>
@endpush
