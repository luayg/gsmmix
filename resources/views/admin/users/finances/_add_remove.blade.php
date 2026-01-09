<div class="modal-header fin-sub bg-warning">
  <h5 class="modal-title">Add / remove credits — {{ $user->name }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form id="finAddRemoveForm" class="modal-body fin-sub">
  @csrf
  <div class="mb-3">
    <label class="form-label">Action</label>
    <select name="action" class="form-select" required>
      <option value="add">Add credits</option>
      <option value="remove">Remove credits</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label">Amount</label>
    <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Note</label>
    <textarea name="note" class="form-control" rows="3"></textarea>
  </div>
</form>

<div class="modal-footer fin-sub">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button type="submit" class="btn btn-warning" form="finAddRemoveForm">Submit</button>
</div>

<script>
(() => {
  const form = document.getElementById('finAddRemoveForm');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const url = "{{ route('admin.users.finances.add_remove', $user) }}";
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
