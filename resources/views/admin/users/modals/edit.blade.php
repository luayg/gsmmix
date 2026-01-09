<div class="modal-content">
  <div class="modal-header bg-warning">
    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit user</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>

  <form class="js-ajax-form" action="{{ route('admin.users.update', $user->id) }}" method="post">
    @csrf
    @method('PUT')

    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" value="{{ $user->username }}">
        </div>

        <div class="col-md-6">
          <label class="form-label">Group</label>
          <select name="group_id" class="form-select select2" data-placeholder="Select a group">
            <option value=""></option>
            @foreach($groups as $g)
              <option value="{{ $g->id }}" @selected($user->group_id == $g->id)>{{ $g->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select name="status" class="form-select" required>
            <option value="active" @selected($user->status==='active')>active</option>
            <option value="inactive" @selected($user->status==='inactive')>inactive</option>
          </select>
        </div>

        {{-- تغيير كلمة المرور (اختياري) --}}
        <div class="col-md-6">
          <label class="form-label">New password</label>
          <div class="input-group">
            <input type="password" name="password" class="form-control" minlength="8" placeholder="اتركه فارغًا للإبقاء على الحالية">
            <button type="button" class="btn btn-outline-secondary js-toggle-pass"><i class="fas fa-eye"></i></button>
          </div>
          <div class="form-text">اتركه فارغًا إذا لا ترغب بتغيير كلمة المرور.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Confirm new password</label>
          <div class="input-group">
            <input type="password" name="password_confirmation" class="form-control" minlength="8">
            <button type="button" class="btn btn-outline-secondary js-toggle-pass"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Roles</label>
          @php $has = $user->roles?->pluck('name')->all() ?? []; @endphp
          <select name="roles[]" class="form-select select2" multiple data-placeholder="Select roles">
            @foreach($roles as $r)
              <option value="{{ $r->name }}" @selected(in_array($r->name,$has))>{{ $r->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      <button type="submit" class="btn btn-warning">Update</button>
    </div>
  </form>
</div>
