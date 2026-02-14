{{-- resources/views/admin/api/remote/server/page.blade.php --}}
@extends('layouts.admin-standalone')

@section('title', 'Remote Server Services')

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <div class="fw-semibold">{{ $provider->name }} — Remote SERVER Services</div>
      <div class="small text-muted">Standalone page (loads Vite + editors) — فتحها في نافذة ثانية سيُظهر Toolbar.</div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.apis.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="p-2 border-bottom">
      <input type="text" id="svcSearch" class="form-control form-control-sm" placeholder="Search name / group / remote id...">
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0" id="svcTable">
        <thead>
          <tr>
            <th style="width:260px">Group</th>
            <th style="width:110px">Remote ID</th>
            <th>Name</th>
            <th style="width:110px">Credit</th>
            <th style="width:140px">Time</th>
            <th class="text-end" style="width:140px">Action</th>
          </tr>
        </thead>
        <tbody>
        @foreach($groups as $groupName => $items)
          @foreach($items as $svc)
            @php
              $rid   = (string)($svc->remote_id ?? '');
              $name  = (string)($svc->name ?? '');
              $time  = (string)($svc->time ?? '');
              $credit= (float)($svc->price ?? $svc->credit ?? 0);

              $af = $svc->additional_fields ?? $svc->additional_data ?? null;
              if (is_string($af)) {
                $afJson = $af;
              } elseif (is_array($af)) {
                $afJson = json_encode($af, JSON_UNESCAPED_UNICODE);
              } else {
                $afJson = '[]';
              }

              $isAdded = isset($existing[$rid]);
            @endphp

            <tr data-row
                data-group="{{ strtolower($groupName) }}"
                data-name="{{ strtolower(strip_tags($name)) }}"
                data-remote="{{ strtolower($rid) }}"
                data-remote-id="{{ $rid }}">
              <td>{{ $groupName }}</td>
              <td><code>{{ $rid }}</code></td>
              <td style="min-width:520px;">{{ strip_tags($name) }}</td>
              <td>{{ number_format($credit, 4) }}</td>
              <td>{{ strip_tags($time) }}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>Added ✅</button>
                @else
                  <button type="button"
                          class="btn btn-success btn-sm clone-btn"
                          data-create-service
                          data-service-type="server"
                          data-provider-id="{{ $provider->id }}"
                          data-provider-name="{{ e($provider->name) }}"
                          data-remote-id="{{ e($rid) }}"
                          data-group-name="{{ e($groupName) }}"
                          data-name="{{ e(strip_tags($name)) }}"
                          data-credit="{{ number_format($credit,4,'.','') }}"
                          data-time="{{ e(strip_tags($time)) }}"
                          data-additional-fields="{{ e($afJson) }}">
                    Clone
                  </button>
                @endif
              </td>
            </tr>
          @endforeach
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- Template required by service-modal --}}
<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>

@push('scripts')
<script>
(function(){
  const input = document.getElementById('svcSearch');
  input?.addEventListener('input', () => {
    const q = (input.value || '').trim().toLowerCase();
    document.querySelectorAll('#svcTable tr[data-row]').forEach(tr => {
      const hit =
        (tr.dataset.group || '').includes(q) ||
        (tr.dataset.name  || '').includes(q) ||
        (tr.dataset.remote|| '').includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });
})();
</script>
@endpush
@endsection