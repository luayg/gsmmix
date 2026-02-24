{{-- resources/views/admin/services/groups/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Service groups')

@section('content')
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Service groups</h1>
      <p class="text-muted mb-0 small">Manage your service groups quickly from one clean view.</p>
    </div>

    <button type="button" class="btn btn-primary" id="btnNewGroup">
      + New group
    </button>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 pb-0">
      <div class="fw-semibold">Groups list</div>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:70px">ID</th>
            <th>Name</th>
            <th style="width:160px">Type</th>
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
            <td class="text-uppercase">{{ $r->type ?? '' }}</td>
            <td class="text-end">
              <div class="btn-group btn-group-sm" role="group" aria-label="Group actions">
                <button type="button" class="btn btn-outline-primary"
                        data-edit-group
                        data-id="{{ $r->id }}"
                        data-name="{{ e($r->name ?? '') }}"
                        data-type="{{ e($r->type ?? '') }}">
                  Edit
                </button>

                <button type="button" class="btn btn-outline-danger"
                        data-delete-group
                        data-id="{{ $r->id }}"
                        data-name="{{ e($r->name ?? '') }}">
                  Delete
                </button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="text-center text-muted py-4">No groups yet.</td>
          </tr>
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
@endsection

@push('scripts')
<script>
(function () {
  function initGroupPage() {
    const bs = window.bootstrap;
    if (!bs) return;

    const groupModalEl = document.getElementById('groupModal');
    const groupModal = groupModalEl ? bs.Modal.getOrCreateInstance(groupModalEl) : null;

    const delEl = document.getElementById('groupDeleteModal');
    const delModal = delEl ? bs.Modal.getOrCreateInstance(delEl) : null;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const routes = {
      store: @json(route('admin.services.groups.store')),
      update: (id) => @json(url('/')) + '/admin/service-management/groups/' + id,
      destroy: (id) => @json(url('/')) + '/admin/service-management/groups/' + id,
    };

    const setForm = (mode, data = {}) => {
      document.getElementById('groupModalTitle').textContent = mode === 'edit' ? ('Edit group #' + data.id) : 'New group';
      document.getElementById('groupMethod').value = mode === 'edit' ? 'PUT' : 'POST';
      document.getElementById('groupId').value = data.id || '';
      document.getElementById('groupName').value = data.name || '';
      document.getElementById('groupType').value = data.type || 'imei_service';
    };

    document.getElementById('btnNewGroup')?.addEventListener('click', () => {
      setForm('new');
      groupModal?.show();
    });

    const deleteBtnEl = document.getElementById('groupDeleteBtn');
    let pendingId = null;
    let isDeleting = false;

    const resetDeleteState = () => {
      pendingId = null;
      isDeleting = false;
      if (deleteBtnEl) {
        deleteBtnEl.disabled = false;
        deleteBtnEl.textContent = 'Delete';
      }
    };

    delEl?.addEventListener('hidden.bs.modal', resetDeleteState);

    document.addEventListener('click', (e) => {
      const editBtn = e.target.closest('[data-edit-group]');
      if (editBtn) {
        setForm('edit', {
          id: editBtn.dataset.id,
          name: editBtn.dataset.name,
          type: editBtn.dataset.type,
        });
        groupModal?.show();
        return;
      }

      const deleteBtn = e.target.closest('[data-delete-group]');
      if (deleteBtn) {
        pendingId = deleteBtn.dataset.id;
        document.getElementById('groupDeleteName').textContent = deleteBtn.dataset.name || '—';
        isDeleting = false;
        if (deleteBtnEl) {
          deleteBtnEl.disabled = false;
          deleteBtnEl.textContent = 'Delete';
        }
        delModal?.show();
      }
    });

    document.getElementById('groupSaveBtn')?.addEventListener('click', async () => {
      const method = document.getElementById('groupMethod').value;
      const id = document.getElementById('groupId').value;

      const fd = new FormData(document.getElementById('groupForm'));
      const url = (method === 'PUT') ? routes.update(id) : routes.store;

      fd.set('_method', method === 'PUT' ? 'PUT' : 'POST');

      try {
        const res = await fetch(url, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          body: fd,
        });

        if (res.status === 422) {
          const j = await res.json().catch(() => ({}));
          alert(Object.values(j.errors || {}).flat().join('\n') || 'Validation error');
          return;
        }

        if (!res.ok) {
          const t = await res.text();
          alert('Save failed\n\n' + t);
          return;
        }

        groupModal?.hide();
        window.location.reload();
      } catch (err) {
        alert('Network error');
      }
    });

    deleteBtnEl?.addEventListener('click', async () => {
      if (!pendingId || isDeleting) return;
      isDeleting = true;
      if (deleteBtnEl) {
        deleteBtnEl.disabled = true;
        deleteBtnEl.textContent = 'Deleting...';
      }

      try {
        const fd = new FormData();
        fd.append('_method', 'DELETE');

        const res = await fetch(routes.destroy(pendingId), {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
          },
          body: fd,
        });

        if (!res.ok) {
          // إذا انحذف السجل فعلياً من الضغط السابق، اعتبر الحالة منتهية بنجاح.
          if (res.status === 404 || res.status === 410) {
            window.location.reload();
            return;
          }
          alert('Delete failed');
          isDeleting = false;
          if (deleteBtnEl) {
            deleteBtnEl.disabled = false;
            deleteBtnEl.textContent = 'Delete';
          }
          return;
        }

        delModal?.hide();
        resetDeleteState();
        window.location.reload();
      } catch (err) {
        alert('Network error');
        isDeleting = false;
        if (deleteBtnEl) {
          deleteBtnEl.disabled = false;
          deleteBtnEl.textContent = 'Delete';
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGroupPage, { once: true });
  } else {
    initGroupPage();
  }
})();
</script>
@endpush
