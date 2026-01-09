{{-- resources/views/admin/permissions/index.blade.php --}}
@extends('layouts.admin')
@section('title','Permissions')

@section('content')
<div class="card" id="permsPage"
     data-url-data="{{ route('admin.permissions.data') }}"
     data-url-store="{{ route('admin.permissions.store') }}"
     data-url-destroy-base="{{ route('admin.permissions.destroy', ['perm'=>0]) }}"
     data-url-update-base="{{ route('admin.permissions.update',  ['perm'=>0]) }}"
>
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="fas fa-key me-2"></i> Permissions</h5>
      <div class="btn-group">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
          Export
        </button>
        <ul class="dropdown-menu">
          <li><a class="dropdown-item" href="#" onclick="window.print()">Print</a></li>
        </ul>
        <button class="btn btn-success btn-sm ms-2" id="btnOpenCreate">
          <i class="fas fa-plus"></i> Create permission
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table id="permsTable" class="table table-striped table-bordered w-100">
        <thead>
          <tr>
            <th style="width:70px">ID</th>
            <th>Name</th>
            <th>Guard</th>
            <th>Roles</th>
            <th>Users</th>
            <th>Created</th>
            <th style="width:180px">Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

{{-- Modal: Create --}}
<div class="modal fade" id="modalCreatePerm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formCreatePerm">@csrf
      <div class="modal-header">
        <h5 class="modal-title">Create permission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Name</label>
          <input name="name" type="text" class="form-control" required maxlength="100" placeholder="e.g. users.view">
          <input type="hidden" name="guard_name" value="web">
          <div class="invalid-feedback"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: Edit --}}
<div class="modal fade" id="modalEditPerm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formEditPerm">@csrf @method('PUT')
      <div class="modal-header bg-warning">
        <h5 class="modal-title">Edit permission</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="perm_id" id="editPermId">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" id="editPermName" type="text" class="form-control" required maxlength="100">
          <div class="invalid-feedback"></div>
        </div>
        <input type="hidden" name="guard_name" id="editPermGuard" value="web">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Update</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: Delete --}}
<div class="modal fade" id="modalDeletePerm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formDeletePerm">@csrf
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Delete permission</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Are you sure you want to delete this permission?</p>
        <div class="fw-semibold text-danger" id="delPermName">—</div>
        <input type="hidden" name="perm_id" id="delPermId" value="">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" type="submit">Delete</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')

@endpush
