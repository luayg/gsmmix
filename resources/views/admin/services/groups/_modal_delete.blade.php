<div class="modal-header">
  <div class="h6 mb-0">Delete group</div>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  Delete group <b>#{{ $group->id }}</b> ({{ $group->name }}) ?
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
  <button type="button" class="btn btn-danger" id="btnDeleteGroup">Delete</button>
</div>

<script>
(function(){
  const btn = document.getElementById('btnDeleteGroup');
  if(!btn) return;

  btn.addEventListener('click', async ()=>{
    const url = @json(route('admin.services.groups.destroy', $group->id));
    const res = await fetch(url, {
      method: 'DELETE',
      headers: {
        'X-Requested-With':'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    });

    const j = await res.json().catch(()=>null);
    if(!res.ok || !j?.ok){
      alert(j?.msg || 'Delete failed');
      return;
    }

    bootstrap.Modal.getInstance(document.getElementById('mainAjaxModal'))?.hide();
    location.reload();
  });
})();
</script>