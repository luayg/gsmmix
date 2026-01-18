{{-- resources/views/admin/api/providers/imei_services.blade.php --}}
@extends('layouts.admin')

@php
  // ✅ الخدمات الموجودة فعلاً محلياً لهذا المزود (عشان Added ✅)
  $existing = \App\Models\ImeiService::query()
      ->where('supplier_id', $provider->id)
      ->pluck('id', 'remote_id')
      ->mapWithKeys(fn($id,$remote)=>[(string)$remote => (int)$id])
      ->toArray();
@endphp

@section('content')
<div class="card">

  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="mb-0">{{ $provider->name }} | IMEI services</h5>

    <div class="d-flex gap-2 align-items-center">
      <input type="text" class="form-control form-control-sm" id="svcSearch"
             placeholder="Search service (name / group / remote id)..." style="width:360px">

      <button type="button" class="btn btn-dark btn-sm" id="btnOpenImportWizard">
        Import Services with Group
      </button>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0 align-middle" id="servicesTable">
        <thead>
          <tr>
            <th>Group</th>
            <th style="width:110px">Remote ID</th>
            <th>Name</th>
            <th style="width:90px">Credits</th>
            <th style="width:110px">Time</th>
            <th class="text-end" style="width:140px">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($groups as $groupName => $items)
          @foreach($items as $svc)
            @php
              $remoteId = (string)$svc->remote_id;
              $name     = $svc->name ?? '';
              $credit   = $svc->price ?? 0; // ✅ price في remote_imei_services
              $time     = $svc->time ?? '';
              $isAdded  = array_key_exists($remoteId, $existing);
            @endphp

            <tr data-remote-id="{{ $remoteId }}"
                data-name="{{ e(strip_tags($name)) }}"
                data-group="{{ e(strip_tags($groupName)) }}">
              <td>{!! $groupName !!}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td style="min-width:520px;">{!! $name !!}</td>
              <td>{{ $credit }}</td>
              <td>{!! $time !!}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-secondary btn-sm" disabled>
                    Added ✅
                  </button>
                @else
                  <button type="button"
                          class="btn btn-success btn-sm clone-btn"
                          data-create-service
                          data-service-type="imei"
                          data-provider-id="{{ $provider->id }}"
                          data-provider-name="{{ $provider->name }}"
                          data-remote-id="{{ $remoteId }}"
                          data-name="{{ e(strip_tags($name)) }}"
                          data-credit="{{ $credit }}"
                          data-time="{{ e(strip_tags($time)) }}"
                          data-group-name="{{ e(strip_tags($groupName)) }}">
                    Clone
                  </button>
                @endif
              </td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="6" class="text-center p-4">No data</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ✅ Import Wizard Modal --}}
<div class="modal fade" id="importWizardModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Import Services — {{ $provider->name }} (IMEI)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-6">
            <label class="form-label mb-1">Search</label>
            <input type="text" class="form-control form-control-sm" id="wizSearch" placeholder="Search...">
          </div>

          <div class="col-md-3">
            <label class="form-label mb-1">Profit Mode</label>
            <select class="form-select form-select-sm" id="wizProfitMode">
              <option value="percent">Percent %</option>
              <option value="fixed">Fixed Credits</option>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label mb-1">Value</label>
            <input type="number" step="0.01" class="form-control form-control-sm" id="wizProfitValue" value="0">
          </div>
        </div>

        <div class="d-flex gap-2 mb-2">
          <button class="btn btn-outline-secondary btn-sm" id="wizSelectAll" type="button">Select all</button>
          <button class="btn btn-outline-secondary btn-sm" id="wizUnselectAll" type="button">Unselect all</button>
        </div>

        <div class="table-responsive" style="max-height:55vh; overflow:auto;">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="position-sticky top-0 bg-white" style="z-index:1">
              <tr>
                <th style="width:40px"></th>
                <th style="min-width:260px">Group</th>
                <th style="width:110px">Remote ID</th>
                <th style="min-width:520px">Name</th>
                <th style="width:110px">Credit</th>
                <th style="width:140px">Time</th>
              </tr>
            </thead>
            <tbody id="wizBody"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" id="wizFinish">Import Selected</button>
      </div>

    </div>
  </div>
</div>

{{-- ✅ Create Service Modal Template (المودال الموجود عندك) --}}
<template id="serviceCreateTpl">
  @include('admin.services.imei._modal_create')
</template>

{{-- ✅ لازم يكون partial service-modal موجود بالـ layout --}}
@include('admin.partials.service-modal')

<script>
(function(){
  // ✅ فلترة داخل الصفحة الرئيسية
  const svcSearch = document.getElementById('svcSearch');
  svcSearch?.addEventListener('input', ()=>{
    const q = (svcSearch.value||'').toLowerCase().trim();
    document.querySelectorAll('#servicesTable tbody tr[data-remote-id]').forEach(tr=>{
      const hay = (tr.dataset.group + ' ' + tr.dataset.remoteId + ' ' + tr.dataset.name).toLowerCase();
      tr.style.display = hay.includes(q) ? '' : 'none';
    });
  });

  // ✅ فتح الـ Wizard
  const btnOpen = document.getElementById('btnOpenImportWizard');
  const wizModalEl = document.getElementById('importWizardModal');
  const wizBody = document.getElementById('wizBody');

  // ✅ بناء بيانات الخدمات من الجدول الحالي (Standard لكل المزودين)
  function getAllServicesFromTable(){
    const rows = [];
    document.querySelectorAll('#servicesTable tbody tr[data-remote-id]').forEach(tr=>{
      const remoteId = tr.getAttribute('data-remote-id');
      const group = tr.getAttribute('data-group') || '';
      const name = tr.getAttribute('data-name') || '';
      const credit = tr.querySelectorAll('td')[3]?.innerText?.trim() || '0';
      const time = tr.querySelectorAll('td')[4]?.innerText?.trim() || '';
      const isAdded = !!tr.querySelector('button[disabled]')?.innerText?.includes('Added');
      rows.push({ remote_id: remoteId, group_name: group, name, credit, time, is_added: isAdded });
    });
    return rows;
  }

  function renderWizardRows(list){
    wizBody.innerHTML = '';
    list.forEach(s=>{
      const tr = document.createElement('tr');
      tr.dataset.remoteId = s.remote_id;
      tr.dataset.group = s.group_name;
      tr.dataset.name = s.name;

      tr.innerHTML = `
        <td>
          ${s.is_added ? '<span class="text-muted">—</span>' :
            `<input type="checkbox" class="wiz-check" value="${s.remote_id}">`}
        </td>
        <td>${s.group_name}</td>
        <td><code>${s.remote_id}</code></td>
        <td style="min-width:520px">${s.name}</td>
        <td>${s.credit}</td>
        <td>${s.time}</td>
      `;
      if (s.is_added) tr.classList.add('opacity-50');
      wizBody.appendChild(tr);
    });
  }

  btnOpen?.addEventListener('click', ()=>{
    const list = getAllServicesFromTable();
    renderWizardRows(list);
    new bootstrap.Modal(wizModalEl).show();
  });

  // ✅ بحث داخل الـ Wizard
  document.getElementById('wizSearch')?.addEventListener('input', (e)=>{
    const q = (e.target.value||'').toLowerCase().trim();
    wizBody.querySelectorAll('tr').forEach(tr=>{
      const hay = (tr.dataset.group + ' ' + tr.dataset.remoteId + ' ' + tr.dataset.name).toLowerCase();
      tr.style.display = hay.includes(q) ? '' : 'none';
    });
  });

  document.getElementById('wizSelectAll')?.addEventListener('click', ()=>{
    wizBody.querySelectorAll('input.wiz-check').forEach(cb=> cb.checked = true);
  });
  document.getElementById('wizUnselectAll')?.addEventListener('click', ()=>{
    wizBody.querySelectorAll('input.wiz-check').forEach(cb=> cb.checked = false);
  });

  // ✅ Finish => POST bulk import
  const importUrl = @json(route('admin.apis.services.import', $provider));
  const csrfToken = @json(csrf_token());

  document.getElementById('wizFinish')?.addEventListener('click', async ()=>{
    const ids = Array.from(wizBody.querySelectorAll('input.wiz-check:checked')).map(x=>x.value);
    if (!ids.length) return alert('Select services first');

    const profitMode = document.getElementById('wizProfitMode').value;
    const profitValue = document.getElementById('wizProfitValue').value;

    const res = await fetch(importUrl, {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest'},
      body: JSON.stringify({
        kind: 'imei',
        service_ids: ids,
        profit_mode: profitMode,
        profit_value: profitValue
      })
    });

    const data = await res.json().catch(()=>null);
    if (!res.ok || !data?.ok) return alert(data?.msg || 'Import failed');

    // ✅ علّم الصفوف Added ✅
    ids.forEach(id=>{
      const row = document.querySelector(`#servicesTable tr[data-remote-id="${CSS.escape(String(id))}"]`);
      if (!row) return;
      const btn = row.querySelector('.clone-btn');
      if (btn){
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        btn.innerText = 'Added ✅';
        btn.disabled = true;
        btn.removeAttribute('data-create-service');
      }
    });

    bootstrap.Modal.getInstance(wizModalEl)?.hide();
    alert(`✅ Imported ${data.count} services`);
  });

})();
</script>
@endsection
