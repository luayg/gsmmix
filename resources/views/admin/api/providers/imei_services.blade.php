{{-- resources/views/admin/api/providers/imei_services.blade.php --}}
@extends('admin.layout')

@section('content')
<div class="card">

  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="mb-0">{{ $provider->name }} | IMEI services</h5>

    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectAll">Select All</button>
      <button type="button" class="btn btn-primary btn-sm" id="btnAddSelected">Add Selected</button>
      <button type="button" class="btn btn-success btn-sm" id="btnAddAll">Add All</button>
    </div>
  </div>

  <div class="card-body p-3 border-bottom">
    <div class="row g-2 align-items-center">
      <div class="col-md-4">
        <label class="form-label mb-1">Group Mode</label>
        <select class="form-select form-select-sm" id="groupMode">
          <option value="auto">Auto-create Group (by API group name)</option>
          <option value="pick">Pick Group Manually</option>
        </select>
      </div>

      <div class="col-md-4" id="groupPickerWrap" style="display:none;">
        <label class="form-label mb-1">Select Group</label>
        <select class="form-select form-select-sm" id="groupId">
          @foreach(\App\Models\ServiceGroup::where('type','imei')->orderBy('name')->get() as $g)
            <option value="{{ $g->id }}">{{ $g->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Pricing Mode</label>
        <select class="form-select form-select-sm" id="pricingMode">
          <option value="percent">+ %</option>
          <option value="fixed">+ Fixed</option>
          <option value="manual">Manual Price</option>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label mb-1">Value</label>
        <input type="number" step="0.01" class="form-control form-control-sm" id="pricingValue" value="0">
      </div>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th style="width:40px"><input type="checkbox" id="checkAll"></th>
            <th>Group</th>
            <th style="width:110px">Remote ID</th>
            <th>Name</th>
            <th style="width:90px">Credits</th>
            <th style="width:110px">Time</th>
            <th class="text-end" style="width:130px">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($groups as $groupName => $items)
          @foreach($items as $svc)
            @php
              $remoteId = $svc->remote_id;
              $name     = $svc->name ?? '';
              $credit   = $svc->credit ?? 0;
              $time     = $svc->time ?? '';
            @endphp
            <tr data-remote-id="{{ $remoteId }}">
              <td>
                <input type="checkbox" class="svc-check"
                       value="{{ $remoteId }}"
                       data-group="{{ $groupName }}"
                       data-name="{{ $name }}"
                       data-credit="{{ $credit }}"
                       data-time="{{ $time }}">
              </td>

              <td>{{ $groupName }}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td style="min-width:520px;">{{ $name }}</td>
              <td>{{ $credit }}</td>
              <td>{{ $time }}</td>

              <td class="text-end">
                <button type="button"
                        class="btn btn-success btn-sm clone-btn"
                        data-create-service
                        data-service-type="imei"
                        data-provider-id="{{ $provider->id }}"
                        data-remote-id="{{ $remoteId }}"
                        data-name="{{ $name }}"
                        data-credit="{{ $credit }}"
                        data-time="{{ $time }}">
                  Clone
                </button>
              </td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="7" class="text-center p-4">No data</td></tr>
        @endforelse
        </tbody>

      </table>
    </div>
  </div>
</div>

<template id="serviceCreateTpl">
  @include('admin.services.imei._modal_create')
</template>

<script>
(function(){
  const importUrl    = @json(route('admin.apis.services.import', $provider));
  const csrfToken    = @json(csrf_token());

  const checkAll     = document.getElementById('checkAll');
  const groupMode    = document.getElementById('groupMode');
  const groupPicker  = document.getElementById('groupPickerWrap');

  function getSelectedIds(){
    return Array.from(document.querySelectorAll('.svc-check:checked')).map(x => x.value);
  }

  function setAllChecks(state){
    document.querySelectorAll('.svc-check').forEach(cb => cb.checked = state);
  }

  function markAsAdded(ids = []) {
    ids.forEach(id => {
      const row = document.querySelector(`tr[data-remote-id="${CSS.escape(String(id))}"]`);
      if (!row) return;
      const cb = row.querySelector('.svc-check');
      if (cb) { cb.checked = false; cb.disabled = true; }
      const btn = row.querySelector('.clone-btn');
      if (btn) {
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        btn.innerText = 'Added ✅';
        btn.disabled = true;
        btn.removeAttribute('data-create-service');
      }
    });
  }

  function markAllAsAdded() {
    document.querySelectorAll('tr[data-remote-id]').forEach(row => {
      const cb = row.querySelector('.svc-check');
      if (cb) { cb.checked = false; cb.disabled = true; }
      const btn = row.querySelector('.clone-btn');
      if (btn) {
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        btn.innerText = 'Added ✅';
        btn.disabled = true;
        btn.removeAttribute('data-create-service');
      }
    });
  }

  groupMode.addEventListener('change', ()=>{
    groupPicker.style.display = groupMode.value === 'pick' ? '' : 'none';
  });

  checkAll?.addEventListener('change', ()=> setAllChecks(checkAll.checked));

  document.getElementById('btnSelectAll')?.addEventListener('click', ()=>{
    setAllChecks(true);
    checkAll.checked = true;
  });

  async function sendImport(payload){
    const res = await fetch(importUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json().catch(()=>null);

    if (!res.ok || !data?.ok) {
      alert(data?.msg || 'Import failed');
      return { ok:false };
    }

    return data;
  }

  document.getElementById('btnAddSelected')?.addEventListener('click', async ()=>{
    const ids = getSelectedIds();
    if (!ids.length) return alert("Select services first!");

    const data = await sendImport({
      kind: 'imei',
      apply_all: false,
      service_ids: ids,
      group_mode: groupMode.value,
      group_id: (groupMode.value === 'pick') ? document.getElementById('groupId').value : null,
      pricing_mode: document.getElementById('pricingMode').value,
      pricing_value: document.getElementById('pricingValue').value,
    });

    if (data?.ok) {
      markAsAdded(ids);
      alert(`✅ Imported ${data.count} services successfully`);
    }
  });

  document.getElementById('btnAddAll')?.addEventListener('click', async ()=>{
    if (!confirm("Import ALL services?")) return;

    const data = await sendImport({
      kind: 'imei',
      apply_all: true,
      service_ids: [],
      group_mode: groupMode.value,
      group_id: (groupMode.value === 'pick') ? document.getElementById('groupId').value : null,
      pricing_mode: document.getElementById('pricingMode').value,
      pricing_value: document.getElementById('pricingValue').value,
    });

    if (data?.ok) {
      markAllAsAdded();
      alert(`✅ Imported ALL services successfully`);
    }
  });
})();
</script>
@endsection
