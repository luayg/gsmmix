{{-- resources/views/admin/services/groups/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Service groups')

@section('content')
<div class="container py-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Service groups</h1>
    <button type="button" class="btn btn-primary btn-sm" id="btnNewGroup">
      <i class="fas fa-plus me-1"></i> New group
    </button>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:70px">ID</th>
            <th>Name</th>
            <th style="width:180px">Type</th>
            <th class="text-end" style="width:190px">Actions</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td class="text-truncate" style="max-width:720px" title="{{ e($r->name ?? '') }}">
              {{ $r->name ?? '' }}
            </td>
            <td class="text-uppercase">{{ $r->type ?? '' }}</td>

            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-primary"
                      data-edit-group
                      data-id="{{ $r->id }}"
                      data-name="{{ e($r->name ?? '') }}"
                      data-type="{{ e($r->type ?? '') }}">
                <i class="fas fa-pen me-1"></i> Edit
              </button>

              <button class="btn btn-sm btn-outline-danger"
                      data-delete-group
                      data-id="{{ $r->id }}"
                      data-name="{{ e($r->name ?? '') }}">
                <i class="fas fa-trash me-1"></i> Delete
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">No groups yet.</td></tr>
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
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" id="groupSaveBtn">
          <i class="fas fa-save me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

{{-- ============ Delete Modal (أجمل) ============ --}}
<div class="modal fade" id="groupDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <div class="modal-title fw-semibold">
          <i class="fas fa-triangle-exclamation me-1"></i> Delete group
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2">Are you sure you want to delete this group?</div>
        <div class="p-2 border rounded bg-light" id="groupDeleteName">—</div>
        <div class="small text-muted mt-2">This action cannot be undone.</div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="groupDeleteBtn">
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

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const routes = {
    store: @json(route('admin.services.groups.store')),
    update: (id) => @json(url('/')) + '/admin/service-management/groups/' + id,
    destroy: (id) => @json(url('/')) + '/admin/service-management/groups/' + id,
  };

  const setForm = (mode, data={}) => {
    document.getElementById('groupModalTitle').textContent =
      mode === 'edit' ? ('Edit group #' + data.id) : 'New group';
    document.getElementById('groupMethod').value = mode === 'edit' ? 'PUT' : 'POST';
    document.getElementById('groupId').value = data.id || '';
    document.getElementById('groupName').value = data.name || '';
    document.getElementById('groupType').value = data.type || 'imei_service';
  };

  document.getElementById('btnNewGroup')?.addEventListener('click', () => {
    setForm('new', {});
    modalShow('groupModal');
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-edit-group]');
    if(!btn) return;

    setForm('edit', {
      id: btn.dataset.id,
      name: btn.dataset.name,
      type: btn.dataset.type,
    });

    modalShow('groupModal');
  });

  document.getElementById('groupSaveBtn')?.addEventListener('click', async () => {
    const method = document.getElementById('groupMethod').value;
    const id = document.getElementById('groupId').value;

    const fd = new FormData(document.getElementById('groupForm'));
    const url = (method === 'PUT') ? routes.update(id) : routes.store;
    fd.set('_method', method);

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

      modalHide('groupModal');
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
    document.getElementById('groupDeleteName').textContent = btn.dataset.name || '—';
    modalShow('groupDeleteModal');
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

      modalHide('groupDeleteModal');
      window.location.reload();
    }catch(err){
      alert('Network error');
    }
  });
})();
</script>
@endsection