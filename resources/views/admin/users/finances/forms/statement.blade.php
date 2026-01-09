{{-- resources/views/admin/users/finances/forms/statement.blade.php --}}
<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">Statement — {{ $user->name ?? ('User '.$user->id) }}</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<style>
  /* عرض أوسع لمودال الـ Statement */
  #finActionModal .modal-dialog.modal-xl { max-width: 1100px; }
  @media (min-width: 1400px){
    #finActionModal .modal-dialog.modal-xl { max-width: 1200px; }
  }

  /* تحسين قابلية القراءة داخل الجدول */
  .fin-sta-table td, .fin-sta-table th { vertical-align: middle; }
  .fin-sta-badge { font-size: .75rem; }

  /* على الشاشات الصغيرة: اجعل الجدول قابلًا للتمرير الأفقي وتخفيف الحشو */
  @media (max-width: 576px){
    .fin-sta-wrap { padding: .5rem; }
    .fin-sta-table { font-size: .875rem; }
    .fin-sta-table th, .fin-sta-table td { white-space: nowrap; }
  }
</style>

<div class="modal-body fin-sta-wrap">
  <div class="table-responsive">
    <table class="table table-sm table-hover fin-sta-table">
      <thead class="table-light">
        <tr>
          <th style="width: 110px;">Date</th>
          <th>Type</th>
          <th>Direction</th>
          <th class="text-end">Amount</th>
          <th>Reference</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
      @php
        // $rows يُفترض أن يكون LengthAwarePaginator أو Collection
        $hasRows = isset($rows) && count($rows);
      @endphp

      @if(!$hasRows)
        <tr>
          <td colspan="6" class="text-center text-muted py-4">No transactions.</td>
        </tr>
      @else
        @foreach($rows as $r)
          <tr>
            <td>
              <div>{{ \Carbon\Carbon::parse($r->created_at)->format('Y-m-d') }}</div>
              <small class="text-muted">{{ \Carbon\Carbon::parse($r->created_at)->format('H:i:s') }}</small>
            </td>
            <td>
              @php
                // إظهار النوع كبادج
                $typeMap = [
                  'payment'       => ['label'=>'Payment',     'class'=>'bg-success'],
                  'credit_add'    => ['label'=>'Credit add',  'class'=>'bg-info'],
                  'credit_remove' => ['label'=>'Credit rmv',  'class'=>'bg-danger'],
                ];
                $t = $typeMap[$r->kind] ?? ['label'=>$r->kind, 'class'=>'bg-secondary'];
              @endphp
              <span class="badge fin-sta-badge {{ $t['class'] }}">{{ $t['label'] }}</span>

              @if(!empty($r->paid))
                <span class="badge fin-sta-badge bg-success-subtle text-success border border-success-subtle">paid</span>
              @endif
            </td>
            <td>
              @php
                $dir = $r->direction === 'income'
                  ? ['label'=>'income', 'class'=>'bg-success']
                  : ['label'=>'expense','class'=>'bg-danger'];
              @endphp
              <span class="badge fin-sta-badge {{ $dir['class'] }}">{{ $dir['label'] }}</span>
            </td>
            <td class="text-end">{{ number_format($r->amount, 2) }}</td>
            <td>{{ $r->reference ?? '—' }}</td>
            <td class="text-truncate" style="max-width: 280px;">{{ $r->note ?? '—' }}</td>
          </tr>
        @endforeach
      @endif
      </tbody>
    </table>
  </div>

  {{-- ترقيم الصفحات (AJAX) إن كان $rows paginator --}}
  @if($hasRows && method_exists($rows, 'hasPages') && $rows->hasPages())
    <nav class="d-flex justify-content-center mt-2">
      {!! $rows->onEachSide(1)->links('pagination::bootstrap-5') !!}
    </nav>
  @endif
</div>

<div class="modal-footer">
  <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>

<script>
(function () {
  // كبّر هذا المودال فقط
  const dlg = document.querySelector('#finActionModal .modal-dialog');
  if (dlg) { dlg.classList.remove('modal-sm','modal-lg'); dlg.classList.add('modal-xl'); }

  // اجلب صفحات الترقيم داخل نفس المودال (AJAX)
  const container = document.querySelector('#finActionModal .modal-content');
  document.querySelector('#finActionModal').addEventListener('click', async function(e){
    const a = e.target.closest('a.page-link');
    if (!a) return;

    const url = a.getAttribute('href');
    if (!url || url === '#') return;

    e.preventDefault();
    // لعرض لودينج خفيف
    container.classList.add('position-relative');
    const loader = document.createElement('div');
    loader.className = 'position-absolute top-50 start-50 translate-middle';
    loader.innerHTML = '<div class="spinner-border" role="status" style="width:2rem;height:2rem;"></div>';
    container.appendChild(loader);

    try{
      const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
      const html = await res.text();
      container.innerHTML = html;
    }catch(_){
      container.removeChild(loader);
    }
  }, { passive:true });
})();
</script>
