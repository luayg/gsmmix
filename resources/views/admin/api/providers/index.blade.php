@extends('layouts.admin')

@section('title', 'API Management')

@section('content')
@php
  // قيَم واجهة الاستخدام
  $q       = request('q', '');
  $type    = request('type', '');
  $status  = request('status', '');
  $perPage = (int) request('per_page', method_exists($rows, 'perPage') ? $rows->perPage() : 20);
@endphp

<div class="page-apis-index"><!-- نطاق الصفحة -->

  <div class="card">
    {{-- ===== شريط الأدوات (الأزرق) ===== --}}
    <div class="card-header">
      <div class="apis-toolbar p-2 px-3 rounded" style="background:linear-gradient(180deg,#2d6cdf,#2563eb);color:#fff;">
        <div class="row g-2 align-items-center">
          <div class="col-auto d-flex align-items-center gap-2">

            {{-- Show N items --}}
            <div class="btn-group">
              <button class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                Show {{ $perPage }} items
              </button>
              <ul class="dropdown-menu">
                @foreach([10,20,25,50,100] as $n)
                  <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['per_page'=>$n]) }}">Show {{ $n }} items</a></li>
                @endforeach
              </ul>
            </div>

            {{-- Add --}}
            <a href="#" class="btn btn-success btn-sm js-api-modal"
               data-url="{{ route('admin.apis.create') }}">Add API</a>

            {{-- Export --}}
            <button id="btn-export" type="button" class="btn btn-outline-light btn-sm">
              Export CSV (current view)
            </button>
          </div>

          {{-- Filters --}}
          <div class="col-auto">
            <form class="d-flex align-items-center gap-2" method="GET" action="{{ route('admin.apis.index') }}">
              <select name="type" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
                <option value="">DHRU / Simple link</option>
                <option value="DHRU"        {{ $type==='DHRU' ? 'selected' : '' }}>DHRU</option>
                <option value="Simple link" {{ $type==='Simple link' ? 'selected' : '' }}>Simple link</option>
              </select>
              <select name="status" class="form-select form-select-sm" style="min-width:150px" onchange="this.form.submit()">
                <option value="">Status</option>
                <option value="Active"   {{ $status==='Active' ? 'selected' : '' }}>Active</option>
                <option value="Inactive" {{ $status==='Inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
              <input type="hidden" name="per_page" value="{{ $perPage }}">
            </form>
          </div>

          {{-- Search --}}
          <div class="col ms-auto">
            <form method="GET" action="{{ route('admin.apis.index') }}" class="d-flex justify-content-end">
              <div class="input-group input-group-sm" style="max-width:300px;">
                <span class="input-group-text bg-white">Search</span>
                <input type="text" name="q" class="form-control" placeholder="Smart search" value="{{ $q }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>

    {{-- ===== Flash ===== --}}
    <div class="card-body p-0">
      @if(session('ok'))
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
          {{ session('ok') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      {{-- ===== الجدول ===== --}}
      <div class="table-responsive apis-scroll" style="overflow-x:auto; overflow-y:visible;">
        <table class="table table-striped mb-0 align-middle apis-table" id="apis-table">
          <thead class="bg-light">
            <tr>
              <th class="sortable" data-sort="id"     style="width:90px">ID <span class="sort-icon"></span></th>
              <th class="sortable" data-sort="name">Name <span class="sort-icon"></span></th>
              <th class="sortable" data-sort="type"   style="width:140px">Type <span class="sort-icon"></span></th>
              <th style="width:110px">Synced</th>
              <th style="width:120px">Auto sync</th>
              <th class="text-end sortable" data-sort="balance" style="width:140px">Balance <span class="sort-icon"></span></th>
              <th class="text-end" style="width:380px">Actions</th>
            </tr>
          </thead>
          <tbody id="apis-tbody">
            @forelse($rows as $p)
              @php
                $isSynced   = (bool)($p->synced    ?? $p->is_synced    ?? $p->has_synced ?? false);
                $isAuto     = (bool)($p->auto_sync ?? $p->is_auto_sync ?? $p->autosync   ?? false);
                $balanceVal = (float)($p->balance  ?? 0);
              @endphp
              <tr data-id="{{ $p->id }}"
                  data-name="{{ \Illuminate\Support\Str::lower($p->name) }}"
                  data-type="{{ \Illuminate\Support\Str::lower($p->type) }}"
                  data-balance="{{ $balanceVal }}">
                <td class="apis-id"><code>{{ $p->id }}</code></td>
                <td dir="auto">{{ $p->name }}</td>
                <td class="text-uppercase">{{ $p->type }}</td>

                <td>{!! $isSynced ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' !!}</td>
                <td>{!! $isAuto   ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' !!}</td>

                <td class="text-end {{ $balanceVal > 0 ? '' : 'text-danger' }}">
                  ${{ number_format($balanceVal, 2) }}
                </td>

                <td class="text-end">
                  <div class="btn-group btn-group-sm" role="group" aria-label="API actions">

                    {{-- View --}}
                    <a href="#" class="btn btn-primary js-api-modal"
                       data-url="{{ route('admin.apis.view', $p) }}">View</a>

                    {{-- Services --}}
                    <div class="btn-group position-static" role="group">
                      <button type="button"
                              class="btn btn-secondary dropdown-toggle"
                              data-bs-toggle="dropdown"
                              aria-expanded="false">
                        Services
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item js-api-modal js-close-dropdown" href="#" data-url="{{ route('admin.apis.services.imei', $p) }}">IMEI</a></li>
                        <li>
                          <a class="dropdown-item js-close-dropdown"
                             href="{{ route('admin.apis.remote.server.index', $p) }}">
                             Server
                          </a>
                        </li>
                        <li><a class="dropdown-item js-api-modal js-close-dropdown" href="#" data-url="{{ route('admin.apis.services.file', $p) }}">File</a></li>
                      </ul>
                    </div>

                    {{-- Sync now --}}
                    <form action="{{ route('admin.apis.sync', $p) }}" method="POST" class="d-inline-block">
                      @csrf
                      <button type="submit" class="btn btn-info">Sync now</button>
                    </form>

                    {{-- Edit --}}
                    <a href="#" class="btn btn-warning js-api-modal"
                       data-url="{{ route('admin.apis.edit', $p) }}">Edit</a>

                    {{-- Delete --}}
                    <form action="{{ route('admin.apis.destroy', $p) }}" method="POST" class="d-inline-block js-delete-form">
                      @csrf @method('DELETE')
                      <button type="button"
                              class="btn btn-danger js-confirm-delete"
                              data-name="{{ $p->name }}">
                        Delete
                      </button>
                    </form>

                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No APIs found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      {{-- ===== التذييل ===== --}}
      <div class="d-flex align-items-center justify-content-between flex-wrap p-3 gap-2">
        <div class="text-muted small">
          Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} items
        </div>
        <div>
          {{ $rows->appends(['q'=>$q,'type'=>$type,'status'=>$status,'per_page'=>$perPage])->links() }}
        </div>
      </div>
    </div>
  </div>

</div><!-- /.page-apis-index -->

{{-- ✅ مودال واحد فقط لهذه الصفحة --}}
<div class="modal fade" id="apiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:95vw;">
    <div class="modal-content" id="apiModalContent">
      <div class="p-4 text-center text-muted">Loading...</div>
    </div>
  </div>
</div>

{{-- ✅ Delete Confirmation Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <p class="mb-0">
          Are you sure you want to delete:
          <strong id="deleteApiName"></strong> ?
        </p>
        <small class="text-muted d-block mt-2">
          This action cannot be undone.
        </small>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="deleteConfirmBtn">
          Yes, Delete
        </button>
      </div>

    </div>
  </div>
</div>

@endsection


@push('styles')
<style>
  :root{ --apis-name-col: 280px; }

  .page-apis-index .apis-table th:nth-child(2),
  .page-apis-index .apis-table td:nth-child(2){
    width: var(--apis-name-col);
    max-width: var(--apis-name-col);
  }

  .page-apis-index .apis-table td:nth-child(2){
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .page-apis-index .apis-table th:nth-child(3),
  .page-apis-index .apis-table td:nth-child(3){ width: 110px; }
  .page-apis-index .apis-table th:nth-child(4),
  .page-apis-index .apis-table td:nth-child(4){ width: 80px; }
  .page-apis-index .apis-table th:nth-child(5),
  .page-apis-index .apis-table td:nth-child(5){ width: 100px; }
  .page-apis-index .apis-table th:nth-child(6),
  .page-apis-index .apis-table td:nth-child(6){ width: 120px; }
  .page-apis-index .apis-table th:nth-child(7),
  .page-apis-index .apis-table td:nth-child(7){ width: 360px; }

  .page-apis-index .btn-group.position-static,
  .page-apis-index .dropdown.position-static{
    position: static !important;
  }
  .page-apis-index .dropdown-menu{
    z-index: 2050 !important;
  }
</style>
@endpush


@push('scripts')
<script>
(function(){

  // ✅ افتح مودال واحد فقط (apiModal)
  async function openApiModal(url){
    const modalEl   = document.getElementById('apiModal');
    const contentEl = document.getElementById('apiModalContent');

    // ✅ أغلق أي مودال مفتوح
    document.querySelectorAll('.modal.show').forEach(el => {
      const inst = bootstrap.Modal.getInstance(el);
      if(inst) inst.hide();
    });

    contentEl.innerHTML = `<div class="p-4 text-center text-muted">Loading...</div>`;

    const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });
    modal.show();

    try{
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const html = await res.text();

      contentEl.innerHTML = html;

      // ✅ شغل scripts الموجودة داخل HTML المحمل
      executeScripts(contentEl);

    }catch(e){
      contentEl.innerHTML = `
        <div class="modal-header">
          <h5 class="modal-title">Error</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger">Failed to load content.</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      `;
    }
  }

  // ✅ تشغيل scripts داخل HTML المحمل ديناميكيًا
  function executeScripts(container){
    const scripts = container.querySelectorAll("script");
    scripts.forEach(oldScript => {
      const newScript = document.createElement("script");

      for(let i=0; i<oldScript.attributes.length; i++){
        const attr = oldScript.attributes[i];
        newScript.setAttribute(attr.name, attr.value);
      }

      newScript.appendChild(document.createTextNode(oldScript.innerHTML));
      oldScript.parentNode.replaceChild(newScript, oldScript);
    });
  }

  // ✅ إغلاق dropdown فور الضغط على IMEI/Server/File
  document.addEventListener('click', function(e){
    const item = e.target.closest('.js-close-dropdown');
    if(!item) return;

    const dropdownWrap = item.closest('.btn-group');
    if(!dropdownWrap) return;

    const toggle = dropdownWrap.querySelector('[data-bs-toggle="dropdown"]');
    if(!toggle) return;

    const inst = bootstrap.Dropdown.getOrCreateInstance(toggle);
    inst.hide();

  }, true);

  // ✅ فتح مودال من js-api-modal
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-api-modal');
    if(!btn) return;

    e.preventDefault();
    e.stopPropagation();
    if(typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

    const url = btn.getAttribute('data-url');
    if(!url) return;

    openApiModal(url);

  }, true);

})();
</script>

<script>
(function(){

  let deleteForm = null;

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-confirm-delete');
    if(!btn) return;

    e.preventDefault();
    deleteForm = btn.closest('form');

    document.getElementById('deleteApiName').innerText = btn.getAttribute('data-name') || '';

    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
  });

  document.getElementById('deleteConfirmBtn')?.addEventListener('click', function(){
    if(deleteForm){
      deleteForm.submit();
    }
  });

})();
</script>
@endpush
