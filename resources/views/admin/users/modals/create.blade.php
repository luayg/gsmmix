<div class="modal-content">
  <div class="modal-header bg-success text-white">
    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Create user</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>

  <form class="js-ajax-form" action="{{ route('admin.users.store') }}" method="post">
    @csrf
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control">
        </div>
        <div class="col-md-6">
          <label class="form-label">Group</label>
          <select name="group_id" class="form-select select2" data-placeholder="Select a group">
            <option value=""></option>
            @foreach($groups as $g)
              <option value="{{ $g->id }}">{{ $g->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select name="status" class="form-select" required>
            <option value="active" selected>active</option>
            <option value="inactive">inactive</option>
          </select>
        </div>

        {{-- Password --}}
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <div class="input-group">
            <input type="password" name="password" class="form-control" minlength="8" placeholder="min 8 chars">
            <button type="button" class="btn btn-outline-secondary js-toggle-pass"><i class="fas fa-eye"></i></button>
          </div>
          <div class="form-text">اتركها فارغة لو أردت كلمة مرور افتراضية (password).</div>
        </div>

        {{-- Confirm --}}
        <div class="col-md-6">
          <label class="form-label">Confirm password</label>
          <div class="input-group">
            <input type="password" name="password_confirmation" class="form-control" minlength="8">
            <button type="button" class="btn btn-outline-secondary js-toggle-pass"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label">Roles</label>
          <select name="roles[]" class="form-select select2" multiple data-placeholder="Select roles">
            @foreach($roles as $r)
              <option value="{{ $r->name }}">{{ $r->name }}</option>
            @endforeach
          </select>
          <div class="form-text">يمكن اختيار أكثر من دور.</div>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>
