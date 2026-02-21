@php
  $isEdit = !empty($group);
@endphp

<div class="modal-header">
  <div class="h6 mb-0">{{ $isEdit ? 'Edit group' : 'New group' }}</div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form method="POST"
      action="{{ $isEdit ? route('admin.services.groups.update', $group->id) : route('admin.services.groups.store') }}"
      id="groupForm">
  @csrf
  @if($isEdit) @method('PUT') @endif

  <div class="modal-body">
    <div class="row g-2">
      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input class="form-control" name="name" value="{{ $group->name ?? '' }}" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Type</label>
        <select class="form-select" name="type" required>
          @foreach(['imei','server','file'] as $t)
            <option value="{{ $t }}" @selected(($group->type ?? '') === $t)>{{ strtoupper($t) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Ordering</label>
        <input type="number" class="form-control" name="ordering" value="{{ (int)($group->ordering ?? 0) }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Active</label>
        <select class="form-select" name="active">
          <option value="1" @selected((int)($group->active ?? 1)===1)>Yes</option>
          <option value="0" @selected((int)($group->active ?? 1)===0)>No</option>
        </select>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-primary">Save</button>
  </div>
</form>

<script>
(function(){
  const form = document.getElementById('groupForm');
  if(!form) return;

  form.addEventListener('submit', async (e)=>{
    e.preventDefault();

    const res = await fetch(form.action, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: new FormData(form)
    });

    if(res.status === 422){
      const j = await res.json().catch(()=>({}));
      alert(Object.values(j.errors||{}).flat().join("\n"));
      return;
    }

    if(!res.ok){
      alert('Save failed');
      return;
    }

    bootstrap.Modal.getInstance(document.getElementById('mainAjaxModal'))?.hide();
    location.reload();
  });
})();
</script>