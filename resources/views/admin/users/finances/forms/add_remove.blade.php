<div class="modal-header bg-warning">
  <h5 class="modal-title">Add / remove credits — {{ $user->name ?? 'User '.$user->id }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<form id="finAddRemoveForm" class="modal-body">
  @csrf

  <div class="mb-3">
    <label class="form-label">Action</label>
    <select name="action" id="arAction" class="form-select" required>
      <option value="add">Add credits</option>
      <option value="remove">Remove credits</option>
    </select>
  </div>

  <div id="paidToggle" class="mb-3" style="display:none">
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" id="arPaid" name="paid" value="1">
      <label class="form-check-label" for="arPaid">Paid</label>
    </div>
  </div>

  <div class="mb-2">
    <div class="small text-muted">
      <b>Totals:</b>
      Total receipts: <b>{{ number_format($acc->total_receipts,2) }}</b> —
      Paid: <b>{{ number_format($acc->paid_credits,2) }}</b> —
      Unpaid: <b>{{ number_format($unpaid,2) }}</b>
    </div>
  </div>

  <div id="removeHint" class="alert alert-warning small" style="display:none">
    You can remove up to <b>{{ number_format($unpaid,2) }}</b> from <i>Unpaid</i>.
    Extra removal (if any) will be refunded from <i>Paid</i>.
  </div>

  <div class="mb-3">
    <label class="form-label">Amount</label>
    <input type="number" step="0.01" min="0" name="amount" id="arAmount" class="form-control" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Note</label>
    <textarea name="note" class="form-control" rows="3"></textarea>
  </div>
</form>

<div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button class="btn btn-warning" id="btnARSubmit">Submit</button>
</div>

<script>
(() => {
  const form   = document.getElementById('finAddRemoveForm');
  const btn    = document.getElementById('btnARSubmit');
  const action = document.getElementById('arAction');
  const amount = document.getElementById('arAmount');
  const paidBox= document.getElementById('paidToggle');
  const rmHint = document.getElementById('removeHint');
  const postUrl= "{{ route('admin.users.finances.add_remove', $user) }}";

  function syncUI(){
    const isAdd = action.value === 'add';
    paidBox.style.display = isAdd ? '' : 'none';
    rmHint.style.display  = isAdd ? 'none' : '';
  }
  action.addEventListener('change', syncUI);
  syncUI();

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

      bootstrap.Modal.getInstance(document.getElementById('finActionModal'))?.hide();
      window.showToast?.('success','Credits updated');
      window.__refreshFinSummary?.();

    }catch(err){
      window.showToast?.('danger','Failed', {title:'Error', delay:5000});
      console.error(err);
    }finally{
      btn.disabled = false;
    }
  }, {once:false});
})();
</script>
