<div class="modal-content" id="finModal" data-user-id="{{ $user->id }}">
  <div class="modal-header bg-info text-white">
    <h5 class="modal-title">{{ $user->name }} | Finances</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
  </div>
  <div class="modal-body">
    <div class="table-responsive">
      <table class="table">
        <tbody id="finSummary">
          <tr><th>Balance</th><td class="text-end"><span id="s-balance">{{ number_format($acc->balance,2) }}</span></td></tr>
          <tr><th>Available balance (Overdraft limit included)</th><td class="text-end"><span id="s-available">{{ number_format($acc->available_with_overdraft,2) }}</span></td></tr>
          <tr><th>Locked amount</th><td class="text-end"><span id="s-locked">{{ number_format($acc->locked_amount,2) }}</span></td></tr>
          <tr><th>Total credit receipts</th><td class="text-end"><span id="s-total">{{ number_format($acc->total_receipts,2) }}</span></td></tr>
          <tr><th>Paid credits</th><td class="text-end"><span id="s-paid">{{ number_format($acc->paid_credits,2) }}</span></td></tr>
          <tr><th>Unpaid credits</th><td class="text-end"><span id="s-unpaid">{{ number_format($acc->unpaid_credits,2) }}</span></td></tr>
          <tr><th>Duty</th><td class="text-end"><span id="s-duty">{{ number_format($acc->unpaid_credits,2) }}</span></td></tr>
          <tr><th>Overdraft limit</th><td class="text-end"><span id="s-overdraft">{{ number_format($acc->overdraft_limit,2) }}</span></td></tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex gap-2 mt-3">
      @include('admin.users.finances.components._gateways_btn')
      @include('admin.users.finances.components._overdraft_btn')
      @include('admin.users.finances.components._addremove_btn')
      @include('admin.users.finances.components._addpayment_btn')
      @include('admin.users.finances.components._statement_btn')
      <button class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
    </div>

    <div id="finSubModalZone" class="mt-3"></div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const root  = document.getElementById('finModal');
  const uid   = root.dataset.userId;
  const zone  = document.getElementById('finSubModalZone');

  function refreshSummary(){
    fetch(`/admin/users/${uid}/finances/summary`).then(r=>r.json()).then(s=>{
      for (const k of ['balance','available','locked','total','paid','unpaid','duty','overdraft']){
        document.getElementById('s-'+k).textContent = s[k];
      }
    });
  }

  // load sub views (components) via Ajax into zone
  function loadComp(url){
    zone.innerHTML = '<div class="text-muted p-3">Loading…</div>';
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
      .then(r=>r.text()).then(html => zone.innerHTML = html);
  }

  // Buttons
  root.addEventListener('click', (e)=>{
    const b = e.target.closest('[data-fin-url]');
    if(!b) return;
    e.preventDefault();
    loadComp(b.dataset.finUrl);
  });

  // Listen to custom event after submit to refresh and toast
  window.addEventListener('fin:updated', ()=>{
    refreshSummary();
  });
})();
</script>
@endpush
