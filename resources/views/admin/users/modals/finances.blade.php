{{-- resources/views/admin/users/modals/finances.blade.php --}}
<div class="modal-header bg-info text-white">
  <h5 class="modal-title">{{ $user->name ?? ('User '.$user->id) }} | Finances</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body p-0">
  <table class="table mb-0">
    <tbody>
      <tr><th class="w-50">Balance</th>      <td class="text-end" data-fin="balance">0.00</td></tr>
      <tr><th>Available balance (Overdraft limit included)</th><td class="text-end" data-fin="available">0.00</td></tr>
      <tr><th>Locked amount</th>             <td class="text-end" data-fin="locked">0.00</td></tr>
      <tr><th>Total credit receipts</th>     <td class="text-end" data-fin="total_receipts">0.00</td></tr>
      <tr><th>Paid credits</th>              <td class="text-end" data-fin="paid">0.00</td></tr>
      <tr><th>Unpaid credits</th>            <td class="text-end" data-fin="unpaid">0.00</td></tr>
      <tr><th>Duty</th>                      <td class="text-end" data-fin="duty">0.00</td></tr>
      <tr><th>Overdraft limit</th>           <td class="text-end" data-fin="overdraft">0.00</td></tr>
    </tbody>
  </table>
</div>

<div class="modal-footer flex-wrap gap-2">
  <button class="btn btn-outline-secondary js-fin-open"
          data-url="{{ route('admin.users.finances.form.gateways', $user) }}">Manage payment gateways</button>

  <button class="btn btn-danger js-fin-open"
          data-url="{{ route('admin.users.finances.form.overdraft', $user) }}">Overdraft limit</button>

  <button class="btn btn-warning js-fin-open"
          data-url="{{ route('admin.users.finances.form.add_remove', $user) }}">Add / remove credits</button>

  <button class="btn btn-success js-fin-open"
          data-url="{{ route('admin.users.finances.form.add_payment', $user) }}">Add payment</button>

  <button class="btn btn-primary js-fin-open"
          data-url="{{ route('admin.users.finances.statement', $user) }}">Statement</button>

  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>

{{-- Sub-modal that hosts forms --}}
<div class="modal fade" id="finActionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content shadow-lg rounded-4 overflow-hidden"></div>
  </div>
</div>

<style>
  #ajaxModal .modal-dialog { max-width: 900px; }
  @media (max-width: 768px) {
    #ajaxModal .modal-dialog { max-width: 95vw; }
    .modal-footer .btn { width: 100%; }
  }
  #finActionModal .modal-dialog { max-width: 560px; }
  .modal-backdrop.show { opacity: .25; }
  #finActionModal .modal-content { border-radius: 18px; }
  #finActionModal .modal-header { padding: .8rem 1rem; }
  #finActionModal .modal-body   { padding: 1rem; }
  #finActionModal .modal-footer { padding: .75rem 1rem; }
</style>

<script>
(function(){
  const FIN_ROUTES = {
    summary     : "{{ route('admin.users.finances.summary',  $user) }}",
    setOverdraft: "{{ route('admin.users.finances.set_overdraft', $user) }}",
    addRemove   : "{{ route('admin.users.finances.add_remove',    $user) }}",
    addPayment  : "{{ route('admin.users.finances.add_payment',   $user) }}",
  };

  // مُساعد: منع كاش
  const noCache = (u) => u + (u.includes('?') ? '&' : '?') + 't=' + Date.now();

  // تحديث الملخص في المودال الرئيسي
  async function refreshFinSummary(){
    try{
      const res = await fetch(noCache(FIN_ROUTES.summary), {
        headers: { 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json' },
        cache: 'no-store'
      });
      if(!res.ok) return;
      const j = await res.json();
      for (const k in j){
        const el = document.querySelector('[data-fin="'+k+'"]');
        if (el) el.textContent = j[k];
      }
    }catch(_){}
  }
  // متاحة عالميًا
  window.__refreshFinSummary = refreshFinSummary;

  // إن كانت شاشة الفايننس ظاهرة الآن، حدّث الملخص فورًا
  if (document.querySelector('[data-fin]')) refreshFinSummary();

  // فتح المودال الفرعي وتحميل المحتوى
  $(document).off('click.finances', '.js-fin-open').on('click.finances', '.js-fin-open', async function(e){
    e.preventDefault();
    const url   = this.dataset.url;
    const $sub  = $('#finActionModal');
    const $cont = $sub.find('.modal-content');

    $cont.html(`
      <div class="modal-body py-5 text-center text-muted">
        <div class="spinner-border" role="status"></div>
        <div class="mt-3">Loading…</div>
      </div>
    `);
    bootstrap.Modal.getOrCreateInstance($sub[0]).show();

    try{
      const res  = await fetch(noCache(url), {
        headers:{'X-Requested-With':'XMLHttpRequest'},
        cache: 'no-store'
      });
      const html = await res.text();
      $cont.html(html);
    }catch(_){
      $cont.html('<div class="modal-body text-danger">Failed to load modal content.</div>');
    }
  });

  // Overdraft
  $(document).off('submit.finances', '#finOverdraftForm').on('submit.finances', '#finOverdraftForm', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    try{
      const res = await fetch(noCache(FIN_ROUTES.setOverdraft), {
        method : 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'X-Requested-With':'XMLHttpRequest',
          'Accept':'application/json'
        },
        body: fd,
        cache: 'no-store'
      });
      if(!res.ok) throw new Error(await res.text());
      bootstrap.Modal.getInstance(document.getElementById('finActionModal'))?.hide();
      window.showToast?.('success','Overdraft updated');
      refreshFinSummary();
    }catch(err){
      window.showToast?.('danger','Failed',{title:'Error',delay:5000});
    }
  });

  // Add / remove credits
  $(document).off('submit.finances', '#finAddRemoveForm').on('submit.finances', '#finAddRemoveForm', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    try{
      const res = await fetch(noCache(FIN_ROUTES.addRemove), {
        method : 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'X-Requested-With':'XMLHttpRequest',
          'Accept':'application/json'
        },
        body: fd,
        cache: 'no-store'
      });
      if(!res.ok) throw new Error(await res.text());
      bootstrap.Modal.getInstance(document.getElementById('finActionModal'))?.hide();
      window.showToast?.('success','Credits updated');
      refreshFinSummary();
    }catch(err){
      window.showToast?.('danger','Failed',{title:'Error',delay:5000});
    }
  });

  // Add payment
  $(document).off('submit.finances', '#finAddPaymentForm').on('submit.finances', '#finAddPaymentForm', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    try{
      const res = await fetch(noCache(FIN_ROUTES.addPayment), {
        method : 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'X-Requested-With':'XMLHttpRequest',
          'Accept':'application/json'
        },
        body: fd,
        cache: 'no-store'
      });
      if(!res.ok) throw new Error(await res.text());
      bootstrap.Modal.getInstance(document.getElementById('finActionModal'))?.hide();
      window.showToast?.('success','Payment added');
      refreshFinSummary();
    }catch(err){
      window.showToast?.('danger','Failed',{title:'Error',delay:5000});
    }
  });
})();
</script>
