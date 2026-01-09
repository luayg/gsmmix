{{-- resources/views/admin/roles/index.blade.php --}}
@extends('layouts.admin')
@section('title','Roles')

@section('content')
<div class="card" id="rolesPage"
     data-url-data="{{ route('admin.roles.data') }}"
     data-url-store="{{ route('admin.roles.store') }}"
     data-url-update-base="{{ route('admin.roles.update', ['role'=>0]) }}"
     data-url-destroy-base="{{ route('admin.roles.destroy', ['role'=>0]) }}"
     data-url-perms-base="{{ route('admin.roles.perms', ['role'=>0]) }}"
>
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i> Roles</h5>
      <button class="btn btn-success btn-sm" id="btnOpenCreate"><i class="fas fa-plus"></i> Create role</button>
    </div>

    <div class="table-responsive">
      <table id="rolesTable" class="table table-striped table-bordered w-100">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Name</th>
            <th style="width:140px"># Permissions</th>
            <th style="width:160px">Created</th>
            <th style="width:260px">Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>

{{-- Modal: Create --}}
<div class="modal fade" id="modalCreateRole" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formCreateRole">@csrf
      <div class="modal-header">
        <h5 class="modal-title">Create role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" type="text" class="form-control" required maxlength="100" placeholder="e.g. Manager">
          <input type="hidden" name="guard_name" value="web">
          <div class="invalid-feedback"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="submit">
          <span class="btn-text">Save</span>
          <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: Edit --}}
<div class="modal fade" id="modalEditRole" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formEditRole">@csrf @method('PUT')
      <div class="modal-header">
        <h5 class="modal-title">Edit role</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="role_id" value="">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" type="text" class="form-control" required maxlength="100">
          <input type="hidden" name="guard_name" value="web">
          <div class="invalid-feedback"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="submit">
          <span class="btn-text">Update</span>
          <span class="spinner-border spinner-border-sm d-none"></span>
        </button>
      </div>
    </form>
  </div>
</div>

{{-- Modal: View (بسيط للآن) --}}
<div class="modal fade" id="modalViewRole" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Role | View</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="roleViewBody">
        {{-- يملأه الجافاسكربت --}}
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

{{-- Modal: Confirm Delete --}}
<div class="modal fade" id="modalDeleteRole" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Delete | <span id="delRoleName"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Are you sure you want to delete this role?</div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-danger" id="btnConfirmDelete">Delete</button>
      </div>
    </div>
  </div>
</div>
@endsection


