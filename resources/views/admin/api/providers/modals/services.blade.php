{{-- resources/views/admin/api/providers/modals/services.blade.php --}}
{{-- مودال عرض خدمات مزوّد الـ API + زر Clone يفتح المودال الموحّد مع تعبئة مسبقة --}}

@php
    // نحدّد نوع الخدمة المستهدَف للإنشاء من هذا المودال: imei | server | file
    // يأتي من الكنترولر أو من بارامتر الطلب، والافتراضي imei
    $kind = $kind ?? request('kind') ?? ($type ?? null);
    $kind = in_array($kind, ['imei','server','file']) ? $kind : 'imei';
@endphp

<style>
  .svc-scroll { max-height: 70vh; overflow: auto; }
  .svc-table thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
  .svc-id { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  .svc-name, .svc-group { max-width: 520px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .svc-group { max-width: 360px; color: #6c757d; }
  .svc-credit, .svc-time { white-space: nowrap; }
  .svc-toolbar { gap: .75rem; }
  .svc-pagination .page-link { min-width: 2.25rem; text-align: center; }
  .svc-details pre { max-height: 260px; overflow: auto; }
  .svc-wrap-on .svc-name, .svc-wrap-on .svc-group { white-space: normal; overflow: visible; text-overflow: clip; }
</style>

<div class="modal-header">
  <h5 class="modal-title">
    Services
    @isset($provider)
      <small class="text-muted">for Provider #{{ $provider->id }} — {{ $provider->name ?? $provider->url ?? 'API' }}</small>
    @endisset
  </h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

@php
  // نحاول توحيد شكل البيانات القادمة (مصفوفة صفوف)
  $__candidates = [
      $rows ?? null,
      $services ?? null,
      $items ?? null,
      $list ?? null,
      (isset($data) && is_array($data) && array_key_exists('data', $data)) ? $data['data'] : null,
      $data ?? null,
  ];
  $rows = [];
  foreach ($__candidates as $__cand) {
      if (empty($__cand)) continue;
      if ($__cand instanceof \Illuminate\Support\Collection) $__cand = $__cand->toArray();
      if (is_array($__cand)) {
          $rows = (array_key_exists('data', $__cand) && is_array($__cand['data'])) ? $__cand['data'] : $__cand;
          break;
      }
  }
@endphp

<div class="modal-body">
  @if(empty($rows))
    <div class="alert alert-warning mb-3">لا توجد خدمات للعرض.</div>
    <ul class="mb-0">
      <li>تأكد من مزامنة المزوّد أولًا.</li>
      <li>أو جرّب التبديل بين الأنواع (IMEI/Server/File) من الصفحة.</li>
    </ul>
  @else
    <div class="d-flex justify-content-between align-items-center flex-wrap svc-toolbar mb-3">
      <div class="input-group" style="max-width: 360px;">
        <span class="input-group-text">Search</span>
        <input id="svc-search" type="text" class="form-control" placeholder="Name / Group / ID">
      </div>

      <div class="d-flex align-items-center gap-2">
        <div class="form-check me-2">
          <input class="form-check-input" type="checkbox" id="svc-wrap">
          <label class="form-check-label" for="svc-wrap">Wrap</label>
        </div>

        <div class="text-muted small me-1">
          <span id="svc-range">0–0</span> of <span id="svc-total">0</span>
        </div>

        <select id="svc-page-size" class="form-select form-select-sm">
          <option value="10" selected>10 / page</option>
          <option value="25">25 / page</option>
          <option value="50">50 / page</option>
        </select>
      </div>
    </div>

    <div class="table-responsive svc-scroll">
      <table class="table table-sm table-striped align-middle svc-table mb-0">
        <thead>
          <tr>
            <th style="width:110px;">ID</th>
            <th>Name</th>
            <th style="width:360px;">Group</th>
            <th class="text-end" style="width:130px;">Credit</th>
            <th class="text-end" style="width:120px;">Time</th>
            <th style="width:110px;">Details</th>
            <th class="text-end" style="width:120px;">Action</th> {{-- زر الاستنساخ --}}
          </tr>
        </thead>
        <tbody id="svc-tbody"></tbody>
      </table>
    </div>

    <nav class="mt-3">
      <ul class="pagination justify-content-center flex-wrap svc-pagination" id="svc-pager"></ul>
    </nav>
  @endif
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>

@if(!empty($rows))
<script>
(function(){
  const RAW  = @json($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  const KIND = @json($kind); // imei | server | file
  const PROVIDER_ID = @json($provider->id ?? null);

  const mapRow = (r) => ({
    id:      r.SERVICEID ?? r.remote_id ?? r.id ?? '-',
    name:    r.SERVICENAME ?? r.name ?? '-',
    group:   r.GROUPNAME  ?? r.group ?? r.group_name ?? '-',
    credit:  r.CREDIT     ?? r.price ?? null,
    time:    r.TIME       ?? r.time ?? null,
    info:    r.INFO       ?? r.info ?? null,
    add:     r.ADDITIONAL_FIELDS ?? r.additional_fields ?? null,
    exts:    r.ALLOWED_EXTENSIONS ?? r.allowed_extensions ?? null,
    groups:  r.CREDIT_GROUPS ?? r.credit_groups ?? null,
    min_qty: r.MIN_QTY ?? r.min_qty ?? null,
    max_qty: r.MAX_QTY ?? r.max_qty ?? null,
  });

  const dataAll = RAW.map(mapRow);
  let pageSize = 10, page = 1, query = '';

  const $tbody = document.getElementById('svc-tbody');
  const $pager = document.getElementById('svc-pager');
  const $range = document.getElementById('svc-range');
  const $total = document.getElementById('svc-total');
  const $pageSize = document.getElementById('svc-page-size');
  const $search = document.getElementById('svc-search');
  const $wrap = document.getElementById('svc-wrap');

  const esc = (s) => (s==null?'':String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;'));
  const fmtCredit = (v) => {
    if (v===null || v===undefined || v==='') return '—';
    const num = Number(String(v).replace(/[^0-9.\-]/g,''));
    if (Number.isFinite(num)) return num.toFixed(3);
    return esc(v);
  };
  const hasDetails = (r) => !!(r.info || r.add || r.groups || r.exts || r.min_qty || r.max_qty);

  const filtered = () => {
    if (!query) return dataAll;
    const q = query.toLowerCase();
    return dataAll.filter(r =>
      (r.name && r.name.toLowerCase().includes(q)) ||
      (r.group && r.group.toLowerCase().includes(q)) ||
      (String(r.id).toLowerCase().includes(q))
    );
  };

  function render() {
    const rows = filtered();
    const total = rows.length;
    const lastPage = Math.max(1, Math.ceil(total / pageSize));
    if (page > lastPage) page = lastPage;

    const startIdx = (page - 1) * pageSize;
    const endIdx = Math.min(startIdx + pageSize, total);
    const slice = rows.slice(startIdx, endIdx);

    $tbody.innerHTML = slice.map((r, idx) => {
      const detailId = `svc-detail-${page}-${idx}-${r.id}`;
      const name = esc(r.name);
      const group = esc(r.group);
      const time = esc(r.time ?? '—');

      // زر الاستنساخ: يعتمد على المودال الموحّد (service-modal.blade) الذي يستمع لأي عنصر data-create-service
      const cloneBtn = `
        <button type="button" class="btn btn-success btn-sm"
          data-create-service
          data-service-type="${esc(KIND)}"
          data-provider-id="${esc(PROVIDER_ID ?? '')}"
          data-remote-id="${esc(r.id)}"
          data-name="${name}"
          data-credit="${esc(r.credit ?? '')}"
          data-time="${time}">
          Clone
        </button>`;

      return `
        <tr>
          <td class="svc-id"><code>${esc(r.id)}</code></td>
          <td class="svc-name" dir="auto" title="${name}">${name}</td>
          <td class="svc-group" dir="auto" title="${group}">${group}</td>
          <td class="svc-credit text-end">${fmtCredit(r.credit)}</td>
          <td class="svc-time text-end"><span class="badge bg-light text-dark">${time}</span></td>
          <td>
            ${hasDetails(r) ? `<button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#${detailId}" aria-expanded="false" aria-controls="${detailId}">View</button>` : '<span class="text-muted">—</span>'}
          </td>
          <td class="text-end">${cloneBtn}</td>
        </tr>
        ${hasDetails(r) ? `
          <tr class="table-active">
            <td colspan="7" class="p-0">
              <div id="${detailId}" class="collapse">
                <div class="p-3 svc-details">
                  <div class="row g-3">
                    ${r.info ? `<div class="col-12"><div><strong>Info</strong></div><pre class="mb-0 small bg-light p-2 rounded border">${esc(typeof r.info==='string'?r.info:JSON.stringify(r.info,null,2))}</pre></div>` : ''}
                    ${r.add ? `<div class="col-12"><div><strong>Additional Fields</strong></div><pre class="mb-0 small bg-light p-2 rounded border">${esc(typeof r.add==='string'?r.add:JSON.stringify(r.add,null,2))}</pre></div>` : ''}
                    ${r.groups ? `<div class="col-12 col-md-6"><div><strong>Credit Groups</strong></div><pre class="mb-0 small bg-light p-2 rounded border">${esc(typeof r.groups==='string'?r.groups:JSON.stringify(r.groups,null,2))}</pre></div>` : ''}
                    ${r.exts ? `<div class="col-12 col-md-6"><div><strong>Allowed Extensions</strong></div><pre class="mb-0 small bg-light p-2 rounded border">${esc(typeof r.exts==='string'?r.exts:JSON.stringify(r.exts,null,2))}</pre></div>` : ''}
                    ${(r.min_qty||r.max_qty) ? `<div class="col-12 col-md-6"><div><strong>Quantity Limits</strong></div><div class="small">Min: <code>${esc(r.min_qty??'—')}</code> | Max: <code>${esc(r.max_qty??'—')}</code></div></div>` : ''}
                  </div>
                </div>
              </div>
            </td>
          </tr>` : ''}`;
    }).join('');

    // range & total
    $range.textContent = total ? `${startIdx+1}–${endIdx}` : '0–0';
    $total.textContent = total;

    // pagination
    $pager.innerHTML = '';
    function pageItem(label, targetPage, disabled=false, active=false) {
      const li = document.createElement('li');
      li.className = `page-item ${disabled?'disabled':''} ${active?'active':''}`;
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = label;
      a.addEventListener('click', (e)=>{
        e.preventDefault();
        if (disabled || active) return;
        page = targetPage; render();
      });
      li.appendChild(a); return li;
    }
    const lastPageNum = Math.max(1, Math.ceil(total / pageSize));
    $pager.appendChild(pageItem('«', Math.max(1, page-1), page===1));
    const windowSize = 7;
    let start = Math.max(1, page - Math.floor(windowSize/2));
    let end = Math.min(lastPageNum, start + windowSize - 1);
    start = Math.max(1, end - windowSize + 1);
    for (let p = start; p <= end; p++) $pager.appendChild(pageItem(String(p), p, false, p===page));
    $pager.appendChild(pageItem('»', Math.min(lastPageNum, page+1), page===lastPageNum));
  }

  $pageSize.addEventListener('change', ()=>{ pageSize = parseInt($pageSize.value, 10) || 10; page = 1; render(); });
  $search.addEventListener('input', ()=>{ query = $search.value.trim(); page = 1; render(); });
  $wrap.addEventListener('change', (e)=>{ document.body.classList.toggle('svc-wrap-on', e.target.checked); });

  render();
})();
</script>
@endif

{{-- نضع قالب إنشاء الخدمة الذي سيقرأه السكربت في service-modal --}}
<template id="serviceCreateTpl">
  @if($kind==='server')
    @include('admin.services.server._modal_create')
  @elseif($kind==='file')
    @include('admin.services.file._modal_create')
  @else
    @include('admin.services.imei._modal_create')
  @endif
</template>
