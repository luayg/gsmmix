@extends('layouts.admin')
@section('title','Groups')

@section('content')
<div class="card" id="groupsPage"
     data-url-data="{{ route('admin.groups.data') }}">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0"><i class="fas fa-users me-2"></i> Groups</h5>
      <button type="button"
              class="btn btn-success btn-sm js-open-modal"
              data-url="{{ route('admin.groups.modal.create') }}">
        <i class="fas fa-plus"></i> Create group
      </button>
    </div>

    <div class="table-responsive">
      <table id="groupsTable" class="table table-striped table-bordered w-100">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Users</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
</div>
@endsection
