<div class="modal-content">
  <div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="fas fa-eye me-2"></i> Group | View</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">ID</dt>     <dd class="col-sm-9">{{ $group->id }}</dd>
      <dt class="col-sm-3">Name</dt>   <dd class="col-sm-9">{{ $group->name }}</dd>
      <dt class="col-sm-3">Users</dt>  <dd class="col-sm-9">{{ \App\Models\User::where('group_id',$group->id)->count() }}</dd>
      <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ optional($group->created_at)->format('Y-m-d H:i') }}</dd>
    </dl>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  </div>
</div>
