<div class="modal-header bg-teal text-white">
  <h5 class="modal-title">{{ $user->name ?? 'User '.$user->id }} | Add payment</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form id="finAddPaymentForm" class="modal-body">
  @csrf
  <div class="mb-3">
    <label class="form-label">Amount</label>
    <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
  </div>

  <div class="alert alert-success small">
    User's duty is <b>{{ number_format($duty,2) }}</b> credits ({{ '$'.number_format($duty,2) }}).
    Over this amount is not allowed.
  </div>

  <div class="mb-3">
    <label class="form-label">Note</label>
    <textarea name="note" class="form-control" rows="3"></textarea>
  </div>
</form>

<div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button class="btn btn-success" id="btnPay">Add</button>
</div>

<script>
(() => {
  const form   = document.getElementById('finAddPaymentForm');
  const btn    = document.getElementById('btnPay');
  const postUrl= "{{ route('admin.users.finances.add_payment', $user) }}";

  // منع تكرار الربط
  btn?.addEventListener('click', async (e) => {
    e.preventDefault();

    const fd = new FormData(form);
    btn.disabled = true;

    try{
      const res = await fetch(postUrl, {
        method:'POST',
        headers:{'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                 'X-Requested-With':'XMLHttpRequest'},
        body: fd
      });
      if(!res.ok) throw new Error(await res.text());

      // أغلق المودال الفرعي وحدّث الملخص
      bootstrap.Modal.getInstance(document.getElementById('finActionModal'))?.hide();
      window.showToast?.('success','Payment added');
      window.__refreshFinSummary?.();

    }catch(err){
      window.showToast?.('danger', 'Failed', {title:'Error', delay:5000});
      console.error(err);
    }finally{
      btn.disabled = false;
    }
  }, {once:false});
})();
</script>
