@extends('layouts.admin')
@section('title', 'Role permissions')

@section('content')
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="fas fa-key me-2"></i> Permissions for: {{ $role->name }}
    </h5>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>
  <div class="card-body">
    <form method="post" action="{{ route('admin.roles.perms.sync', $role) }}">
      @csrf

      <div class="row">
        @foreach($permissions as $perm)
          @php $checked = $role->permissions->contains('id', $perm->id); @endphp
          <div class="col-md-4 mb-2">
            <div class="form-check">
              <input class="form-check-input"
                     type="checkbox"
                     id="perm_{{ $perm->id }}"
                     name="permissions[]"
                     value="{{ $perm->name }}"
                     @checked($checked)>
              <label class="form-check-label" for="perm_{{ $perm->id }}">
                {{ $perm->name }}
              </label>
            </div>
          </div>
        @endforeach
      </div>

      <div class="mt-3">
        <button class="btn btn-primary">Save</button>
        <a class="btn btn-secondary" href="{{ route('admin.roles.index') }}">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
