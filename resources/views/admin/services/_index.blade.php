{{-- resources/views/admin/services/_index.blade.php --}}
@php use Illuminate\Support\Str; @endphp

@if(isset($viewPrefix))
  <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <div class="fs-4 fw-semibold text-capitalize">{{ $viewPrefix }} services</div>

    <form class="ms-auto d-flex gap-2 align-items-center" method="GET" action="">
      <input class="form-control form-control-sm" name="q" placeholder="Smart search"
             value="{{ request('q') }}" style="max-width:260px">

      <select class="form-select form-select-sm" name="api_provider_id" style="max-width:260px" onchange="this.form.submit()">
        <option value="">API connection</option>
        @foreach(($apis ?? collect()) as $a)
          <option value="{{ $a->id }}" @selected((string)request('api_provider_id') === (string)$a->id)>
            {{ $a->name }}
          </option>
        @endforeach
      </select>

      {{-- ✅ Create service يبقى كما هو --}}
      <a class="btn btn-sm btn-success" href="javascript:;" data-create-service data-service-type="{{ $viewPrefix }}">
        Create service
      </a>
    </form>
  </div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Name</th>
          <th>Status</th>
          <th>Group</th>
          <th class="text-end">Price</th>
          <th class="text-end">Cost</th>
          <th>Supplier</th>
          <th>API connection</th>
          <th class="text-end" style="width:190px">Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($rows as $r)
          @php
            // اسم الخدمة (قد يكون JSON)
            $displayName = $r->name;
            if (is_string($displayName)) {
              $trim = trim($displayName);
              if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $j = json_decode($trim, true);
                if (is_array($j)) {
                  $displayName = $j['fallback'] ?? $j['en'] ?? (is_array(reset($j)) ? null : reset($j)) ?? $r->name;
                }
              }
            }

            $supplierName = $r->supplier?->name ?? null;
            $supplierId   = $r->supplier_id ?? null;

            // api connection name (نفس supplier غالباً عندك)
            $apiConnName = $supplierName ?: ($supplierId ? ('#'.$supplierId) : 'None');

            $jsonUrl = route($routePrefix.'.show.json', $r->id);
            $updateUrl = route($routePrefix.'.update', $r->id);
            $deleteUrl = route($routePrefix.'.destroy', $r->id);
          @endphp

          <tr>
            <td>{{ $r->id }}</td>

            <td title="{{ is_string($displayName) ? $displayName : '' }}">
              {{ Str::limit((string)$displayName, 90) }}
            </td>

            <td>
              @if($r->active)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>

            <td>{{ $r->group?->name ?? 'None' }}</td>

            <td class="text-end">{{ number_format((float)($r->price ?? 0), 2) }}</td>
            <td class="text-end">{{ number_format((float)($r->cost ?? 0), 2) }}</td>

            <td>{{ $supplierName ?: 'None' }}</td>
            <td>{{ $apiConnName }}</td>

            <td class="text-end text-nowrap">
              <button type="button"
                      class="btn btn-sm btn-warning"
                      data-edit-service
                      data-service-type="{{ $viewPrefix }}"
                      data-id="{{ $r->id }}"
                      data-json-url="{{ $jsonUrl }}"
                      data-update-url="{{ $updateUrl }}">
                Edit
              </button>

              <button type="button"
                      class="btn btn-sm btn-outline-danger"
                      data-delete-service
                      data-delete-url="{{ $deleteUrl }}"
                      data-name="{{ e((string)$displayName) }}">
                Delete
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center text-muted py-4">No services found</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer">
    {{ $rows->links() }}
  </div>
</div>

{{-- ✅ Delete confirm modal --}}
<div class="modal fade" id="svcDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title fw-semibold">Delete service</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">Are you sure you want to delete:</div>
        <div class="p-2 bg-light border rounded" id="svcDeleteName">—</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="svcDeleteConfirmBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // ============ Delete (nice modal) ============
  const delModalEl = document.getElementById('svcDeleteModal');
  const delModal = delModalEl ? bootstrap.Modal.getOrCreateInstance(delModalEl) : null;
  let pendingDeleteUrl = null;

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-delete-service]');
    if(!btn) return;

    pendingDeleteUrl = btn.dataset.deleteUrl || null;
    const nm = btn.dataset.name || '—';
    const out = document.getElementById('svcDeleteName');
    if(out) out.textContent = nm;

    delModal?.show();
  });

  document.getElementById('svcDeleteConfirmBtn')?.addEventListener('click', async () => {
    if(!pendingDeleteUrl) return;

    try{
      const res = await fetch(pendingDeleteUrl, {
        method: 'POST',
        headers: {
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: (() => {
          const fd = new FormData();
          fd.append('_method', 'DELETE');
          return fd;
        })()
      });

      const data = await res.json().catch(()=>null);
      if(!res.ok || !data?.ok){
        alert('Delete failed');
        return;
      }

      delModal?.hide();
      window.location.reload();
    }catch(err){
      alert('Network error');
    }
  });

  // ============ Edit (open same Create modal, but fill + PUT) ============
  // يعتمد على وجود service-modal.blade.php (المودال الأخضر) كما عندك
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-edit-service]');
    if(!btn) return;

    e.preventDefault();

    const jsonUrl = btn.dataset.jsonUrl;
    const updateUrl = btn.dataset.updateUrl;
    if(!jsonUrl || !updateUrl) return alert('Missing json/update URL');

    // افتح نفس مودال Create الموجود عندك بالضبط:
    // نستخدم نفس آلية Create: template id="serviceCreateTpl" + modal #serviceModal
    const modalEl = document.getElementById('serviceModal');
    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl || !modalEl || !body) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

    // تشغيل السكربتات داخل التمبلت (مثل ما عندك)
    (function runInjectedScripts(container){
      Array.from(container.querySelectorAll('script')).forEach(old => {
        const s = document.createElement('script');
        for (const attr of old.attributes) s.setAttribute(attr.name, attr.value);
        s.text = old.textContent || '';
        old.parentNode?.removeChild(old);
        container.appendChild(s);
      });
    })(body);

    // بدّل العنوان
    const titleEl = modalEl.querySelector('.modal-title');
    if(titleEl) titleEl.textContent = 'Edit service';

    // اجلب بيانات الخدمة
    let payload = null;
    try{
      const res = await fetch(jsonUrl, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      payload = await res.json().catch(()=>null);
      if(!res.ok || !payload?.ok) return alert('Failed to load service JSON');
    }catch(err){
      return alert('Network error');
    }

    // جهز الفورم على PUT
    const form = body.querySelector('form');
    if(form){
      form.action = updateUrl;
      form.method = 'POST';

      let m = form.querySelector('input[name="_method"]');
      if(!m){
        m = document.createElement('input');
        m.type = 'hidden';
        m.name = '_method';
        form.appendChild(m);
      }
      m.value = 'PUT';
    }

    // تعبئة الحقول الأساسية
    const setVal = (name, val) => {
      const el = body.querySelector(`[name="${name}"]`);
      if(el) el.value = (val ?? '');
    };

    setVal('name', payload.name_text || '');
    setVal('alias', payload.alias || '');
    setVal('time', payload.time_text || '');
    setVal('info', payload.info_text || '');

    setVal('group_id', payload.group_id ?? '');
    setVal('type', payload.type ?? '');
    setVal('source', payload.source ?? '');

    setVal('supplier_id', payload.supplier_id ?? '');
    setVal('remote_id', payload.remote_id ?? '');

    setVal('cost', (Number(payload.cost||0)).toFixed(4));
    setVal('profit', (Number(payload.profit||0)).toFixed(4));
    setVal('profit_type', payload.profit_type ?? 1);

    // main field
    const mf = payload.main_field || {};
    setVal('main_field_type', mf.type || 'serial');
    setVal('allowed_characters', mf.rules?.allowed || 'any');
    setVal('min', mf.rules?.minimum ?? 0);
    setVal('max', mf.rules?.maximum ?? 0);
    setVal('main_field_label', mf.label?.fallback || mf.label?.en || '');

    // group prices (إن كان تمبلت المودال عندك يبنيها ديناميكياً — سيظهرها)
    // نحقن group_prices في Inputs الموجودة إن ظهرت
    const gp = payload.group_prices || {};
    Object.keys(gp).forEach(gid=>{
      const row = gp[gid] || {};
      const p = body.querySelector(`[name="group_prices[${gid}][price]"]`);
      const d = body.querySelector(`[name="group_prices[${gid}][discount]"]`);
      const t = body.querySelector(`[name="group_prices[${gid}][discount_type]"]`);
      if(p) p.value = Number(row.price||0).toFixed(4);
      if(d) d.value = Number(row.discount||0).toFixed(4);
      if(t) t.value = String(row.discount_type||1);
    });

    // custom fields (إذا كان سكربت الـ Additional عندك يعتمد custom_fields_json)
    // نخزنها في hidden custom_fields_json إن وجد
    const cfJsonEl = body.querySelector('[name="custom_fields_json"]');
    if(cfJsonEl){
      cfJsonEl.value = JSON.stringify(payload.custom_fields || []);
    }

    // افتح المودال
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
})();
</script>