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
              $remoteId = (string)($svc->remote_id ?? '');
              $name     = (string)($svc->name ?? '');
              $credit   = (float)($svc->price ?? 0); // ✅ price في remote_imei_services
              $time     = (string)($svc->time ?? '');
              $info = (string)($svc->info ?? '');

              // ✅ IMPORTANT: additional_fields
              $af = $svc->additional_fields ?? $svc->ADDITIONAL_FIELDS ?? null;
              $afJson = is_array($af)
                  ? json_encode($af, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                  : (string)($af ?? '[]');
              $afJsonTrim = trim($afJson);
              if ($afJsonTrim === '' || $afJsonTrim === 'null') $afJsonTrim = '[]';

              $isAdded  = array_key_exists($remoteId, $existing);
            @endphp

            <tr data-remote-id="{{ $remoteId }}"
                data-name="{{ e(strip_tags($name)) }}"
                data-group="{{ e(strip_tags($groupName)) }}">
              <td>{!! $groupName !!}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td style="min-width:520px;">{!! $name !!}</td>
              <td>{{ number_format($credit, 4) }}</td>
              <td>{!! $time !!}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>
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
                          data-credit="{{ number_format($credit, 4, '.', '') }}"
                          data-time="{{ e(strip_tags($time)) }}"
                          data-info="{{ e($info) }}"
                          data-group-name="{{ e(strip_tags($groupName)) }}"
                          {{-- ✅ هذا هو المفتاح --}}
                          data-additional-fields="{{ e($afJsonTrim) }}">
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

<template id="serviceCreateTpl">
  @include('admin.services.imei._modal_create')
</template>

@include('admin.partials.service-modal')

<script>
(function(){
  const svcSearch = document.getElementById('svcSearch');
  svcSearch?.addEventListener('input', ()=> {
    const q = (svcSearch.value||'').toLowerCase().trim();
    document.querySelectorAll('#servicesTable tbody tr[data-remote-id]').forEach(tr=>{
      const hay = (tr.dataset.group + ' ' + tr.dataset.remoteId + ' ' + tr.dataset.name).toLowerCase();
      tr.style.display = (!q || hay.includes(q)) ? '' : 'none';
    });
  });
})();
</script>
@endsection
