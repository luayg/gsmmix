@php($u = $user)
<div class="modal-header bg-primary text-white">
  <h5 class="modal-title"><i class="fas fa-eye me-2"></i> User | View</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
  <dl class="row mb-0">
    <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $u->name }}</dd>
    <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $u->email }}</dd>
    <dt class="col-sm-3">Username</dt><dd class="col-sm-9">{{ $u->username ?: '-' }}</dd>
    <dt class="col-sm-3">Group</dt><dd class="col-sm-9">{{ optional($u->group)->name ?: '-' }}</dd>
    <dt class="col-sm-3">Roles</dt><dd class="col-sm-9">{{ $u->roles?->pluck('name')->implode(', ') ?: '-' }}</dd>
    <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ $u->status }}</dd>
    <dt class="col-sm-3">Registered</dt><dd class="col-sm-9">{{ optional($u->created_at)->format('Y-m-d H:i') }}</dd>
  </dl>
</div>
<div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
