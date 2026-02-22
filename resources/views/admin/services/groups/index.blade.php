{{-- resources/views/admin/services/groups/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Service groups')

@section('content')
<div class="container py-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Service groups</h1>
    <button type="button" class="btn btn-primary btn-sm" id="btnNewGroup">
      + New group
    </button>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:70px">ID</th>
            <th>Name</th>
            <th style="width:160px">Type</th>
            <th style="width:100px">Active</th>
            <th style="width:100px">Ordering</th>
            <th class="text-end" style="width:180px">Actions</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td class="text-truncate" style="max-width:720px" title="{{ e($r->name ?? '') }}">
              {{ $r->name ?? '' }}
            </td>
            <td class="text-uppercase">
              {{ $r->type ?? '' }}
            </td>
            <td>
              @if((int)($r->active ?? 0) === 1)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>
            <td>{{ (int)($r->ordering ?? 0) }}</td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-warning"
                      data-edit-group
                      data-id="{{ $r->id }}"
                      data-name="{{ e($r->name ?? '') }}"
                      data-type="{{ e($r->type ?? '') }}"
                      data-active="{{ (int)($r->active ?? 0) }}"
                      data-ordering="{{ (int)($r->ordering ?? 0) }}">
                Edit
              </button>

              <button class="btn btn-sm btn-outline-danger"
                      data-delete-group
                      data-id="{{ $r->id }}"
                      data-name="{{ e($r->name ?? '') }}">
                Delete
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No groups yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($rows, 'links'))
      <div class="card-footer">{{ $rows->withQueryString()->links() }}</div>
    @endif
  </div>
</div>

{{-- ============ Create/Edit Modal ============ --}}
<div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title fw-semibold" id="groupModalTitle">New group</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="groupForm">
          @csrf
          <input type="hidden" name="_method" value="POST" id="groupMethod">
          <input type="hidden" name="id" value="" id="groupId">

          <div class="mb-2">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" id="groupName" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Type</label>
            <select class="form-select" name="type" id="groupType" required>
              <option value="imei_service">IMEI_SERVICE</option>
              <option value="server_service">SERVER_SERVICE</option>
              <option value="file_service">FILE_SERVICE</option>
            </select>
          </div>

          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Active</label>
              <select class="form-select" name="active" id="groupActive">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Ordering</label>
              <input type="number" class="form-control" name="ordering" id="groupOrdering" value="0">
            </div>
          </div>
        </form>

        <div class="small text-muted mt-2">
          Ordering: رقم لترتيب الظهور داخل القوائم (الأصغر يظهر أولاً).
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="groupSaveBtn">Save</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ Delete Modal ============ --}}
<div class="modal fade" id="groupDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title fw-semibold">Delete group</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">Are you sure you want to delete:</div>
        <div class="p-2 bg-light border rounded" id="groupDeleteName">—</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="groupDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const groupModalEl = document.getElementById('groupModal');
  const groupModal = groupModalEl ? bootstrap.Modal.getOrCreateInstance(groupModalEl) : null;

  const delEl = document.getElementById('groupDeleteModal');
  const delModal = delEl ? bootstrap.Modal.getOrCreateInstance(delEl) : null;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const routes = {
    store: @json(route('admin.services.groups.store')),
    update: (id) => @json(url('/')) + '/admin/service-management/groups/' + id,
    destroy: (id) => @json(url('/')) + '/admin/service-management/groups/' + id,
  };

  const setForm = (mode, data={}) => {
    document.getElementById('groupModalTitle').textContent = mode === 'edit' ? ('Edit group #' + data.id) : 'New group';
    document.getElementById('groupMethod').value = mode === 'edit' ? 'PUT' : 'POST';
    document.getElementById('groupId').value = data.id || '';
    document.getElementById('groupName').value = data.name || '';
    document.getElementById('groupType').value = data.type || 'imei_service';
    document.getElementById('groupActive').value = String(data.active ?? 1);
    document.getElementById('groupOrdering').value = String(data.ordering ?? 0);
  };

  document.getElementById('btnNewGroup')?.addEventListener('click', () => {
    setForm('new', {});
    groupModal?.show();
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-edit-group]');
    if(!btn) return;

    setForm('edit', {
      id: btn.dataset.id,
      name: btn.dataset.name,
      type: btn.dataset.type,
      active: Number(btn.dataset.active || 0),
      ordering: Number(btn.dataset.ordering || 0),
    });

    groupModal?.show();
  });

  document.getElementById('groupSaveBtn')?.addEventListener('click', async () => {
    const method = document.getElementById('groupMethod').value;
    const id = document.getElementById('groupId').value;

    const fd = new FormData(document.getElementById('groupForm'));
    const url = (method === 'PUT') ? routes.update(id) : routes.store;

    if(method === 'PUT'){
      fd.set('_method', 'PUT');
    }else{
      fd.set('_method', 'POST');
    }

    try{
      const res = await fetch(url, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf},
        body: fd
      });

      if(res.status === 422){
        const j = await res.json().catch(()=>({}));
        alert(Object.values(j.errors||{}).flat().join("\n") || 'Validation error');
        return;
      }

      if(!res.ok){
        const t = await res.text();
        alert('Save failed\n\n' + t);
        return;
      }

      groupModal?.hide();
      window.location.reload();
    }catch(err){
      alert('Network error');
    }
  });

  // Delete
  let pendingId = null;
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-delete-group]');
    if(!btn) return;
    pendingId = btn.dataset.id;

    const nm = btn.dataset.name || '—';
    document.getElementById('groupDeleteName').textContent = nm;
    delModal?.show();
  });

  document.getElementById('groupDeleteBtn')?.addEventListener('click', async () => {
    if(!pendingId) return;
    const url = routes.destroy(pendingId);

    try{
      const fd = new FormData();
      fd.append('_method', 'DELETE');

      const res = await fetch(url, {
        method:'POST',
        headers: {'X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN': csrf},
        body: fd
      });

      if(!res.ok){
        alert('Delete failed');
        return;
      }

      delModal?.hide();
      window.location.reload();
    }catch(err){
      alert('Network error');
    }
  });

})();
</script>
@endsection