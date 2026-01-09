{{-- resources/views/admin/users/finances/forms/overdraft.blade.php --}}
@php
    // نقرأ القيمة مباشرة من القاعدة لضمان أحدث قيمة
    $odRaw = \App\Models\FinanceAccount::where('user_id', $user->id)->value('overdraft_limit');
    $odRaw = $odRaw === null ? 0 : $odRaw;
    // تنسيق برقم عشري ثابت وبنقطة كفاصل عشري (بدون فواصل آلاف)
    $currentOd = number_format((float)$odRaw, 2, '.', '');
@endphp

<div class="modal-header bg-danger text-white">
  <h5 class="modal-title">Overdraft limit — {{ $user->name ?? ('User '.$user->id) }}</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="finOverdraftForm" class="modal-body"
      data-current="{{ $currentOd }}">  {{-- نمرّر القيمة للمودال كـ data-attr --}}
  @csrf
  <input type="hidden" id="odPostUrl" value="{{ route('admin.users.finances.set_overdraft', $user) }}">

  <div class="mb-3">
    <label class="form-label">Overdraft</label>
    <input
      id="odAmount"
      type="number"
      step="0.01"
      min="0"
      class="form-control"
      required
      value="{{ $currentOd }}"  {{-- تعبئة مباشرة --}}
    >

    <small class="text-muted d-block mt-2">
      Current:
      <span id="odCurrent">{{ $currentOd }}</span> Credits
    </small>
  </div>
</form>

<div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <button id="btnReset" type="button" class="btn btn-warning">Reset</button>
  <button id="btnSave"  form="finOverdraftForm" class="btn btn-danger">Save</button>
</div>

<script>
(function () {
  // فك أي events قديمة
  $(document).off('click',  '#btnReset');
  $(document).off('submit', '#finOverdraftForm');

  // عند فتح المودال: اكتب القيمة المخزنة إن لم تكن موجودة لأي سبب
  (function primeInput(){
    const $form   = $('#finOverdraftForm');
    const current = $form.data('current');           // من الـ data-attr
    const $amount = $('#odAmount');
    if (!$amount.val() || isNaN(parseFloat($amount.val()))) {
      if (current !== undefined) {
        $amount.val(String(current));
      }
    }
  })();

  // Reset: فقط يكتب 0.00 (بدون أي حوارات)
  $(document).on('click', '#btnReset', function(){
    $('#odAmount').val('0.00').trigger('change');
  });

  // Save: إرسال + إغلاق + تحديث ملخّص + إشعار نجاح واحد
  $(document).on('submit', '#finOverdraftForm', async function(e){
    e.preventDefault();

    const $amount = $('#odAmount');
    const $save   = $('#btnSave');
    const $reset  = $('#btnReset');

    const postUrl = $('#odPostUrl').val();
    const csrf    = $('meta[name="csrf-token"]').attr('content');

    $amount.prop('disabled', true);
    $save.prop('disabled', true);
    $reset.prop('disabled', true);

    try{
      const fd = new FormData();
      fd.append('overdraft', $amount.val() || 0);

      const res = await fetch(postUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: fd
      });

      if(!res.ok){
        let msg = 'Failed';
        try{ const j = await res.json(); if(j?.message) msg = j.message; }catch(_){}
        window.showToast?.('danger', msg, { title:'Error', delay:5000 });
        return;
      }

      const normalized = (parseFloat($amount.val() || 0)).toFixed(2);
      $('#odCurrent').text(normalized);
      $amount.val(normalized);

      window.__refreshFinSummary?.();
      window.showToast?.('success','Overdraft updated');

      const sub = document.getElementById('finActionModal');
      bootstrap.Modal.getInstance(sub)?.hide();

    }catch(err){
      console.error(err);
      window.showToast?.('danger','Network error', { title:'Error', delay:5000 });
    }finally{
      $amount.prop('disabled', false);
      $save.prop('disabled', false);
      $reset.prop('disabled', false);
    }
  });
})();
</script>
