{{-- resources/views/admin/api/providers/modals/services.blade.php --}}

@php
    $localModel = match($kind){
        'server' => \App\Models\ServerService::class,
        'file'   => \App\Models\FileService::class,
        default  => \App\Models\ImeiService::class,
    };

    $existing = $localModel::query()
        ->where('supplier_id', $provider->id)
        ->pluck('id', 'remote_id')
        ->mapWithKeys(fn($id,$remote)=>[(string)$remote => (int)$id])
        ->toArray();
@endphp

<div class="modal-header">
  <h5 class="modal-title">
    Services for Provider #{{ $provider->id }} — {{ $provider->name }}
  </h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body p-0">

  {{-- ✅ Toolbar --}}
  <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-dark btn-sm" id="btnOpenImportWizard">
      Import Services With Categories
    </button>

    <span class="text-muted small">
      Select services → Profit → Finish
    </span>
  </div>

  {{-- ✅ Alert Result (Hidden by default) --}}
  <div class="px-3 pt-3" id="importResultWrap" style="display:none;">
    <div class="alert alert-success d-flex align-items-center justify-content-between mb-0">
      <div>
        ✅ <strong id="importResultText"></strong>
      </div>
      <button type="button" class="btn-close" onclick="document.getElementById('importResultWrap').style.display='none'"></button>
    </div>
  </div>

  {{-- ✅ Services Table --}}
  <div class="table-responsive" style="max-height:70vh; overflow:auto;">
    <table class="table table-sm table-striped mb-0 align-middle">
      <thead class="bg-white position-sticky top-0" style="z-index:1;">
        <tr>
          <th style="width:110px">ID</th>
          <th style="min-width:420px;">Name</th>
          <th style="min-width:260px;">Group</th>
          <th style="width:110px">Credit</th>
          <th style="width:140px">Time</th>
          <th class="text-end" style="width:140px">Action</th>
        </tr>
      </thead>
      <tbody>

        @foreach($services as $s)

          @php
            $rid   = (string)($s['SERVICEID'] ?? '');
            $name  = $s['SERVICENAME'] ?? '';
            $group = $s['GROUPNAME'] ?? '';
            $credit= $s['CREDIT'] ?? 0;
            $time  = $s['TIME'] ?? '';

            $isAdded = array_key_exists($rid, $existing);
          @endphp

          <tr data-remote-id="{{ $rid }}">
            <td><code>{{ $rid }}</code></td>
            <td style="min-width:520px">{!! $name !!}</td>
            <td>{!! $group !!}</td>
            <td>{{ $credit }}</td>
            <td>{!! $time !!}</td>

            <td class="text-end">

              {{-- ✅ If already added => Add ✅ --}}
              @if($isAdded)
                <button type="button"
                        class="btn btn-secondary btn-sm"
                        disabled>
                  Add ✅
                </button>
              @else
                {{-- ✅ If not added => Clone (opens modal create) --}}
                <button type="button"
                        class="btn btn-success btn-sm clone-btn"
                        data-remote-id="{{ $rid }}"
                        data-name="{{ e(strip_tags($name)) }}"
                        data-credit="{{ $credit }}"
                        data-time="{{ e(strip_tags($time)) }}"
                        data-group="{{ e(strip_tags($group)) }}">
                  Clone
                </button>
              @endif

            </td>
          </tr>

        @endforeach
      </tbody>
    </table>
  </div>

</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>


{{-- ✅ مودال Create الوحيد (مهم: سنستخدمه مرة واحدة فقط) --}}
<div class="modal fade" id="serviceCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:95vw;">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Create service</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="serviceCreateModalBody">
        {{-- يتم ملؤه ديناميكياً --}}
      </div>

    </div>
  </div>
</div>


{{-- ✅ Wizard Modal --}}
<div class="modal fade" id="importWizardModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width:95vw;">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Import Services With Categories</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        {{-- ✅ STEP 1 --}}
        <div id="wizStep1">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Step 1 — Select Services</h6>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="wizSelectAll">Select All</button>
              <button type="button" class="btn btn-outline-danger btn-sm" id="wizUnselectAll">Unselect</button>
            </div>
          </div>

          <div class="table-responsive border rounded" style="max-height:55vh; overflow:auto;">
            <table class="table table-sm table-striped mb-0 align-middle">
              <thead class="bg-white position-sticky top-0" style="z-index:1;">
                <tr>
                  <th style="width:40px"></th>
                  <th style="width:120px">ID</th>
                  <th>Name</th>
                  <th style="width:280px">Group</th>
                  <th style="width:120px">Credit</th>
                  <th style="width:140px">Time</th>
                </tr>
              </thead>
              <tbody id="wizBody"></tbody>
            </table>
          </div>

          <div class="small text-muted mt-2">
            Selected: <strong id="wizSelectedCount">0</strong>
          </div>
        </div>

        {{-- ✅ STEP 2 --}}
        <div id="wizStep2" style="display:none;">
          <h6 class="mb-3">Step 2 — Pricing & Finish</h6>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Profit Mode</label>
              <select class="form-select" id="wizPricingMode">
                <option value="percent">Add Profit %</option>
                <option value="fixed">Add Fixed Credit</option>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Profit Value</label>
              <input type="number" step="0.01" class="form-control" id="wizPricingValue" value="0">
            </div>

            <div class="col-md-4">
              <label class="form-label">Summary</label>
              <div class="border rounded p-2 bg-light">
                <div>Selected: <strong id="wizSummaryCount">0</strong></div>
                <div>Type: <strong class="text-uppercase">{{ $kind }}</strong></div>
                <div>Group: <strong>Auto (by category)</strong></div>
              </div>
            </div>
          </div>

          <div class="alert alert-info mt-3 mb-0">
            ✅ سيتم إنشاء الجروبات تلقائياً حسب اسم Category القادمة من الـ API  
            وإذا كانت الجروب موجودة مسبقاً سيتم وضع الخدمات داخلها بدون تكرار.
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="wizBackBtn" style="display:none;">Back</button>
        <button type="button" class="btn btn-primary" id="wizNextBtn">Next</button>
        <button type="button" class="btn btn-success" id="wizFinishBtn" style="display:none;">Finish</button>
      </div>

    </div>
  </div>
</div>


<script>
(function(){

  const kind       = @json($kind);
  const csrfToken  = @json(csrf_token());
  const importUrl  = @json(route('admin.apis.services.import_wizard', $provider));
  const servicesData = @json($services);
  const providerId = @json($provider->id);
  const providerName = @json($provider->name);

  const btnOpenWizard = document.getElementById('btnOpenImportWizard');
  const wizardModalEl = document.getElementById('importWizardModal');

  const step1 = document.getElementById('wizStep1');
  const step2 = document.getElementById('wizStep2');

  const wizBody = document.getElementById('wizBody');
  const btnSelectAll   = document.getElementById('wizSelectAll');
  const btnUnselectAll = document.getElementById('wizUnselectAll');

  const btnBack   = document.getElementById('wizBackBtn');
  const btnNext   = document.getElementById('wizNextBtn');
  const btnFinish = document.getElementById('wizFinishBtn');

  const selectedCount = document.getElementById('wizSelectedCount');
  const summaryCount  = document.getElementById('wizSummaryCount');

  const createModalEl = document.getElementById('serviceCreateModal');
  const createModalBody = document.getElementById('serviceCreateModalBody');

  let selected = new Set();

  function showStep(n){
    if(n === 1){
      step1.style.display = '';
      step2.style.display = 'none';
      btnBack.style.display = 'none';
      btnNext.style.display = '';
      btnFinish.style.display = 'none';
    }else{
      step1.style.display = 'none';
      step2.style.display = '';
      btnBack.style.display = '';
      btnNext.style.display = 'none';
      btnFinish.style.display = '';
    }
  }

  function updateCount(){
    selectedCount.innerText = selected.size;
    summaryCount.innerText  = selected.size;
  }

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, function(m){
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
    });
  }

  function decodeEntities(text){
    const txt = document.createElement("textarea");
    txt.innerHTML = text;
    return txt.value;
  }

  function renderTable(){
    wizBody.innerHTML = '';
    selected.clear();

    servicesData.forEach(s=>{
      const id    = String(s.SERVICEID ?? '');
      const name  = decodeEntities(String(s.SERVICENAME ?? ''));
      const group = decodeEntities(String(s.GROUPNAME ?? ''));
      const credit= String(s.CREDIT ?? '');
      const time  = decodeEntities(String(s.TIME ?? ''));

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="checkbox" class="wiz-check" value="${id}"></td>
        <td><code>${id}</code></td>
        <td>${escapeHtml(name)}</td>
        <td>${escapeHtml(group)}</td>
        <td>${escapeHtml(credit)}</td>
        <td>${escapeHtml(time)}</td>
      `;
      wizBody.appendChild(tr);
    });

    wizBody.querySelectorAll('.wiz-check').forEach(cb=>{
      cb.addEventListener('change', ()=>{
        if(cb.checked) selected.add(cb.value);
        else selected.delete(cb.value);
        updateCount();
      });
    });

    updateCount();
  }

  btnSelectAll.addEventListener('click', ()=>{
    wizBody.querySelectorAll('.wiz-check').forEach(cb=>{
      cb.checked = true;
      selected.add(cb.value);
    });
    updateCount();
  });

  btnUnselectAll.addEventListener('click', ()=>{
    selected.clear();
    wizBody.querySelectorAll('.wiz-check').forEach(cb=> cb.checked = false);
    updateCount();
  });


  // ✅ ============ فتح مودال Create الصحيح (نسخة واحدة فقط) ============
  document.addEventListener('click', async function(e){

    const btn = e.target.closest('.clone-btn');
    if(!btn) return;

    e.preventDefault();
    e.stopPropagation();

    // ✅ تحميل الـ Create Form الصحيح من السيرفر (الكود القديم الكامل)
    const url = (kind === 'imei')
      ? "{{ route('admin.services.imei.modal.create') }}"
      : (kind === 'server')
        ? "{{ route('admin.services.server.modal.create') }}"
        : "{{ route('admin.services.file.modal.create') }}";

    createModalBody.innerHTML = `<div class="p-4 text-center text-muted">Loading...</div>`;
    const modal = new bootstrap.Modal(createModalEl);
    modal.show();

    const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
    const html = await res.text();
    createModalBody.innerHTML = html;

    // ✅ ملء البيانات تلقائياً
    const rid   = btn.getAttribute('data-remote-id');
    const name  = btn.getAttribute('data-name');
    const credit= btn.getAttribute('data-credit');
    const time  = btn.getAttribute('data-time');

    const form = createModalBody.querySelector('#serviceCreateForm');
    if(form){
      // 🔥 حقول أساسية
      form.querySelector('[name="name"]')?.value = name || '';
      form.querySelector('[name="alias"]')?.value = rid || '';
      form.querySelector('[name="time"]')?.value = time || '';

      // 🔥 التكلفة
      form.querySelector('[name="cost"]')?.value = credit || 0;

      // ✅ supplier_id مهم جداً حتى لا يفشل الحفظ
      if(!form.querySelector('[name="supplier_id"]')){
        const hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'supplier_id';
        hid.value = providerId;
        form.appendChild(hid);
      }else{
        form.querySelector('[name="supplier_id"]').value = providerId;
      }

      // ✅ source API ثابت
      if(form.querySelector('[name="source"]')){
        form.querySelector('[name="source"]').value = 'api';
      }

      // ✅ تفعيل Summernote إذا موجود
      if(typeof window.initModalCreateSummernote === 'function'){
        window.initModalCreateSummernote(createModalBody);
      }

      // ✅ حفظ AJAX
      form.addEventListener('submit', async function(ev){
        ev.preventDefault();

        const fd = new FormData(form);

        try{
          const resp = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With':'XMLHttpRequest' },
            body: fd
          });

          const data = await resp.json();

          if(!resp.ok || !data.ok){
            alert(data.message || 'Failed to save');
            return;
          }

          // ✅ غلق مودال Create
          bootstrap.Modal.getInstance(createModalEl).hide();

          // ✅ تحويل زر Clone إلى Add ✅ Disabled
          const row = document.querySelector(`tr[data-remote-id="${CSS.escape(rid)}"]`);
          if(row){
            const b = row.querySelector('.clone-btn');
            if(b){
              b.classList.remove('btn-success');
              b.classList.add('btn-secondary');
              b.innerText = 'Add ✅';
              b.disabled = true;
            }
          }

        }catch(err){
          alert("Error: " + err.message);
        }

      }, { once:true });

    }

  }, true);



  // ✅ ============ Import Wizard ============
  async function finishImport(applyAll=false){

    const pricing_mode  = document.getElementById('wizPricingMode').value;
    const pricing_value = document.getElementById('wizPricingValue').value;

    const payload = {
      kind,
      apply_all: applyAll,
      service_ids: applyAll ? [] : Array.from(selected),
      pricing_mode,
      pricing_value
    };

    btnFinish.disabled = true;
    btnFinish.innerText = 'Importing...';

    try{
      const res = await fetch(importUrl, {
        method: 'POST',
        headers: {
          'Content-Type':'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With':'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      });

      const data = await res.json();

      if(!res.ok || !data?.ok){
        alert(data?.msg || 'Import failed');
        return;
      }

      // ✅ Show alert in services modal
      document.getElementById('importResultWrap').style.display = '';
      document.getElementById('importResultText').innerText =
        `Imported: ${data.count ?? 0}, Updated: ${data.updated ?? 0}`;

      // ✅ Turn Clone -> Add ✅
      const idsToMark = applyAll ? servicesData.map(x=>String(x.SERVICEID)) : Array.from(selected);

      idsToMark.forEach(id=>{
        const row = document.querySelector(`tr[data-remote-id="${CSS.escape(id)}"]`);
        if(!row) return;
        const btn = row.querySelector('.clone-btn');
        if(btn){
          btn.classList.remove('btn-success');
          btn.classList.add('btn-secondary');
          btn.innerText = 'Add ✅';
          btn.disabled = true;
        }
      });

      bootstrap.Modal.getInstance(wizardModalEl).hide();

    }finally{
      btnFinish.disabled = false;
      btnFinish.innerText = 'Finish';
    }
  }

  btnOpenWizard.addEventListener('click', ()=>{
    renderTable();
    showStep(1);
    new bootstrap.Modal(wizardModalEl).show();
  });

  btnNext.addEventListener('click', ()=>{
    if(selected.size === 0){
      alert("اختر خدمات أولاً أو استخدم Select All ✅");
      return;
    }
    showStep(2);
  });

  btnBack.addEventListener('click', ()=> showStep(1));

  btnFinish.addEventListener('click', ()=>{
    const applyAll = selected.size === servicesData.length;
    finishImport(applyAll);
  });

})();
</script>
