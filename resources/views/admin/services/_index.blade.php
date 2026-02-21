{{-- resources/views/admin/services/_index.blade.php --}}
@php use Illuminate\Support\Str; @endphp

@if(isset($viewPrefix))
  <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <div class="fs-4 fw-semibold text-capitalize">{{ $viewPrefix }} services</div>

    <form class="ms-auto d-flex gap-2 align-items-center" method="GET" action="">
      <input class="form-control form-control-sm" name="q" placeholder="Smart search"
             value="{{ request('q') }}" style="max-width:240px">

      <select class="form-select form-select-sm" name="api_provider_id" style="max-width:220px" onchange="this.form.submit()">
        <option value="">API connection</option>
        @foreach(($apis ?? collect()) as $a)
          <option value="{{ $a->id }}" @selected((string)request('api_provider_id')===(string)$a->id)>{{ $a->name }}</option>
        @endforeach
      </select>

      <button type="button" class="btn btn-sm btn-success"
              data-create-service
              data-service-type="{{ $viewPrefix }}">
        Create service
      </button>
    </form>
  </div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0" id="servicesTable">
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
          <th class="text-end" style="width:180px">Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($rows as $r)
          @php
            // ✅ Fix display when name stored as JSON string
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

            $supplierName = $r->supplier?->name ?? 'None';
            $apiName      = $r->api?->name ?? ($r->supplier?->name ?? 'None');

            $jsonUrl   = route($routePrefix.'.show.json', $r->id);
            $updateUrl = route($routePrefix.'.update', $r->id);
            $delUrl    = route($routePrefix.'.destroy', $r->id);
          @endphp

          <tr data-remote-id="{{ $r->remote_id ?? '' }}">
            <td>{{ $r->id }}</td>

            <td title="{{ is_string($displayName) ? $displayName : '' }}">
              {{ Str::limit((string)$displayName, 80) }}
            </td>

            <td>
              @if($r->active)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>

            <td>{{ $r->group?->name ?? 'None' }}</td>

            @php
              $cost   = (float)($r->cost ?? 0);
              $profit = (float)($r->profit ?? 0);
              $ptype  = (int)($r->profit_type ?? 1);
              $price  = ($ptype === 2) ? ($cost + ($cost * $profit / 100)) : ($cost + $profit);
            @endphp

            <td class="text-end">{{ number_format($price, 2) }}</td>
            <td class="text-end">{{ number_format($cost, 2) }}</td>

            <td>{{ $supplierName }}</td>
            <td>{{ $apiName }}</td>

            <td class="text-end text-nowrap">
              <button type="button"
                      class="btn btn-sm btn-warning"
                      data-edit-service
                      data-service-type="{{ $viewPrefix }}"
                      data-json-url="{{ $jsonUrl }}"
                      data-update-url="{{ $updateUrl }}">
                Edit
              </button>

              <button type="button"
                      class="btn btn-sm btn-outline-danger"
                      data-delete-service
                      data-delete-url="{{ $delUrl }}"
                      data-title="Delete this service?">
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

{{-- ✅ Delete Modal (pretty) --}}
@once
  @push('modals')
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <div class="h6 mb-0">Confirm delete</div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div id="deleteConfirmText">Delete?</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-danger" id="deleteConfirmBtn">Delete</button>
          </div>
        </div>
      </div>
    </div>
  @endpush

  @push('scripts')
    <script>
      (function(){
        let delUrl = null;
        const modalEl = document.getElementById('deleteConfirmModal');
        const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;

        document.addEventListener('click', (e)=>{
          const btn = e.target.closest('[data-delete-service]');
          if(!btn) return;

          e.preventDefault();
          delUrl = btn.dataset.deleteUrl || null;

          document.getElementById('deleteConfirmText').innerText =
            btn.dataset.title || 'Delete?';

          modal?.show();
        });

        document.getElementById('deleteConfirmBtn')?.addEventListener('click', async ()=>{
          if(!delUrl) return;

          const res = await fetch(delUrl, {
            method: 'DELETE',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
          });

          const data = await res.json().catch(()=>null);
          if(!res.ok || !data?.ok){
            alert(data?.msg || 'Delete failed');
            return;
          }

          modal?.hide();
          location.reload();
        });
      })();
    </script>
  @endpush
@endonce