<div class="btn-group btn-group-sm">
  <button type="button" class="btn btn-primary btn-view-role"
          data-id="{{ $role->id }}" data-name="{{ $role->name }}"
          data-perms="{{ $role->permissions_count ?? $role->permissions()->count() }}">
    <i class="fas fa-eye"></i> View
  </button>
  <button type="button" class="btn btn-warning btn-edit-role"
          data-id="{{ $role->id }}" data-name="{{ $role->name }}">
    <i class="fas fa-edit"></i> Edit
  </button>
  <button type="button" class="btn btn-danger btn-del-role"
          data-id="{{ $role->id }}" data-name="{{ $role->name }}">
    <i class="fas fa-trash"></i> Delete
  </button>
  <a class="btn btn-secondary"
     href="{{ route('admin.roles.perms',$role) }}"><i class="fas fa-key"></i> Permissions</a>
</div>
