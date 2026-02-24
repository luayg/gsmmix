{{-- resources/views/admin/services/_index.blade.php --}}
@php use Illuminate\Support\Str; @endphp

@if(isset($viewPrefix))
  <style>
    .svc-page-toolbar{display:flex;align-items:end;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}
    .svc-filter-grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:.75rem;align-items:end}
    .svc-filter-grid .form-control,.svc-filter-grid .form-select{min-width:0}
    .svc-table th,.svc-table td{vertical-align:middle;padding:.55rem .65rem}
    .svc-status-badge{display:inline-flex;align-items:center;justify-content:center;min-width:82px;padding:.2rem .55rem;border-radius:999px;font-size:.72rem;font-weight:700}
    .svc-status-badge--active{background:#22c55e;color:#fff}
    .svc-status-badge--inactive{background:#ef4444;color:#fff}
    @media (max-width: 992px){ .svc-filter-grid{grid-template-columns:repeat(2,minmax(150px,1fr));} }
    @media (max-width: 640px){ .svc-filter-grid{grid-template-columns:1fr;} }
  </style>

  <div class="svc-page-toolbar">
    <div class="fs-4 fw-semibold text-capitalize">{{ $viewPrefix }} services</div>

    <form class="svc-filter-grid" method="GET" action="">
      <input class="form-control form-control-sm" name="q" placeholder="Smart search" value="{{ request('q') }}">

      <select class="form-select form-select-sm" name="api_provider_id">
        <option value="">API connection</option>
        @foreach(($apis ?? collect()) as $a)
          <option value="{{ $a->id }}" @selected((string)request('api_provider_id') === (string)$a->id)>
            {{ $a->name }}
          </option>
        @endforeach
      </select>

      <select class="form-select form-select-sm" name="status">
        <option value="">Status</option>
        <option value="active" @selected(request('status')==='active')>Active</option>
        <option value="inactive" @selected(request('status')==='inactive')>Not active</option>
      </select>

      <div class="d-flex gap-2 justify-content-end">
        <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
        <a class="btn btn-sm btn-success" href="javascript:;" data-create-service data-service-type="{{ $viewPrefix }}">
          <i class="fas fa-plus me-1"></i> Create service
        </a>
      </div>
    </form>
  </div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0 svc-table">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Name</th>
          <th>Group</th>
          <th style="width:120px">Status</th>
          <th class="text-end" style="width:120px">Price</th>
          <th class="text-end" style="width:120px">Cost</th>
          <th style="min-width:180px">API connection</th>
          <th class="text-end" style="width:200px">Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($rows as $r)
          @php
            // display name may be json
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

            // ✅ Fix price: computed from cost + profit_type (same logic as modal JS :contentReference[oaicite:8]{index=8})
            $cost   = (float)($r->cost ?? 0);
            $profit = (float)($r->profit ?? 0);
            $ptype  = (int)($r->profit_type ?? 1);
            $price  = ($ptype === 2) ? ($cost + ($cost * $profit / 100)) : ($cost + $profit);

            // ✅ API connection name (prefer api relation)
            $apiConnName =
              $r->api?->name
              ?? $r->supplier?->name
              ?? (($r->supplier_id ?? null) ? ('#'.$r->supplier_id) : 'None');

            $jsonUrl   = route($routePrefix.'.show.json', $r->id);
            $updateUrl = route($routePrefix.'.update', $r->id);
            $deleteUrl = route($routePrefix.'.destroy', $r->id);
          @endphp

          <tr>
            <td>{{ $r->id }}</td>

            <td title="{{ is_string($displayName) ? $displayName : '' }}">
              {{ Str::limit((string)$displayName, 90) }}
            </td>

            <td>{{ $r->group?->name ?? 'None' }}</td>

            <td>
              @if((int)($r->active ?? 0) === 1)
                <span class="svc-status-badge svc-status-badge--active">Active</span>
              @else
                <span class="svc-status-badge svc-status-badge--inactive">Not active</span>
              @endif
            </td>

            <td class="text-end">{{ number_format((float)$price, 4) }}</td>
            <td class="text-end">{{ number_format((float)$cost, 4) }}</td>

            <td>{{ $apiConnName }}</td>

            <td class="text-end text-nowrap">
              <button type="button"
                      class="btn btn-sm btn-outline-primary"
                      data-edit-service
                      data-service-type="{{ $viewPrefix }}"
                      data-service-id="{{ $r->id }}"
                      data-json-url="{{ $jsonUrl }}"
                      data-update-url="{{ $updateUrl }}">
                <i class="fas fa-pen me-1"></i> Edit
              </button>

              <button type="button"
                      class="btn btn-sm btn-outline-danger"
                      data-delete-service
                      data-delete-url="{{ $deleteUrl }}"
                      data-name="{{ e((string)$displayName) }}">
                <i class="fas fa-trash me-1"></i> Delete
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center text-muted py-4">No services found</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer">
     {{ $rows->links('admin.components.pagination.compact') }}
  </div>
</div>

{{-- ✅ Delete confirm modal (موحّد وأجمل) --}}
<div class="modal fade" id="svcDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <div class="modal-title fw-semibold">
          <i class="fas fa-triangle-exclamation me-1"></i> Delete service
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">Are you sure you want to delete this service?</div>
        <div class="p-2 bg-light border rounded" id="svcDeleteName">—</div>
        <div class="small text-muted mt-2">This action cannot be undone.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="svcDeleteConfirmBtn">
          <i class="fas fa-trash me-1"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // ✅ Bootstrap5 OR fallback to jQuery modal (Bootstrap4)
  function modalShow(id){
    const el = document.getElementById(id);
    if (!el) return;
    if (window.bootstrap?.Modal?.getOrCreateInstance) {
      window.bootstrap.Modal.getOrCreateInstance(el).show();
      return;
    }
    if (window.jQuery && window.jQuery(el).modal) {
      window.jQuery(el).modal('show');
      return;
    }
    alert('Modal library not found (bootstrap/jQuery).');
  }
  function modalHide(id){
    const el = document.getElementById(id);
    if (!el) return;
    if (window.bootstrap?.Modal?.getOrCreateInstance) {
      window.bootstrap.Modal.getOrCreateInstance(el).hide();
      return;
    }
    if (window.jQuery && window.jQuery(el).modal) {
      window.jQuery(el).modal('hide');
    }
  }

  // Delete modal
  let pendingDeleteUrl = null;

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-delete-service]');
    if(!btn) return;

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    pendingDeleteUrl = btn.dataset.deleteUrl || null;
    document.getElementById('svcDeleteName').textContent = btn.dataset.name || '—';
    modalShow('svcDeleteModal');
  });


  const filtersForm = document.querySelector('.svc-filter-grid');
  const applyFilters = () => {
    if (!filtersForm) return;
    const url = new URL(window.location.href);
    const fd = new FormData(filtersForm);

    ['q','api_provider_id','status'].forEach((k) => url.searchParams.delete(k));
    for (const [k,v] of fd.entries()) {
      const val = String(v || '').trim();
      if (val !== '') url.searchParams.set(k, val);
    }
    url.searchParams.delete('page');
    window.location.assign(url.toString());
  };

  filtersForm?.querySelector('select[name="api_provider_id"]')?.addEventListener('change', applyFilters);
  filtersForm?.querySelector('select[name="status"]')?.addEventListener('change', applyFilters);

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

      modalHide('svcDeleteModal');
      window.location.reload();
    }catch(err){
      alert('Network error');
    }
  });
})();
</script>
