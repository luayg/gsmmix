<div class="modal-header fin-sub bg-danger text-white">
  <h5 class="modal-title">Overdraft limit — {{ $user->name }}</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form id="finOverdraftForm" class="modal-body fin-sub">
  @csrf
  <div class="mb-3">
    <label class="form-label">Overdraft</label>
    <input type="number" step="0.01" min="0" name="overdraft" class="form-control" value="{{ $acc->overdraft_limit ?? 0 }}">
  </div>
</form>

<div class="modal-footer fin-sub">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="submit" class="btn btn-danger" form="finOverdraftForm">Save</button>
</div>

<script>
(() => {
  const form = document.getElementById('finOverdraftForm');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const url = "{{ route('admin.users.finances.set_overdraft', $user) }}";
    const res = await fetch(url, {
      method: 'POST',
      headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
      body: new FormData(form)
    });
    if (!res.ok) { alert('Save failed'); return; }
    bootstrap.Modal.getInstance(document.getElementById('finActionModal'))?.hide();
    window.__refreshFinSummary?.();
  });
})();
</script>
