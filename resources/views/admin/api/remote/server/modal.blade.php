{{-- resources/views/admin/api/remote/server/modal.blade.php --}}
{{-- Modal content loaded inside API Management modal (Ajax) --}}
{{-- Requires: resources/js/apis.js (remoteServerOpen / remoteImportOpen) --}}

@php
  $providerName = $provider->name ?? 'Provider';
@endphp

<div class="modal-header align-items-center" style="background:#2b2f36;color:#fff;">
  <div>
    <div class="h6 mb-0">{{ $providerName }} | SERVER remote services</div>
    <div class="small opacity-75">Clone services (with additional fields)</div>
  </div>

  <div class="d-flex gap-2 align-items-center ms-auto">
    <input id="svcSearch"
           type="text"
           class="form-control form-control-sm"
           placeholder="Search (name / group / remote id)..."
           style="width:min(420px, 46vw);">

    <button type="button"
            class="btn btn-dark btn-sm"
            id="btnOpenImportWizard"
            data-import-url="{{ route('admin.apis.remote.server.import_page', $provider) }}">
      Import Services with Category
    </button>

    <button type="button" class="btn-close btn-close-white ms-1" data-bs-dismiss="modal"></button>
  </div>
</div>

<div class="modal-body p-0">

  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle" id="svcTable">
      <thead>
        <tr>
          <th style="width:190px">Group</th>
          <th style="width:110px">Remote ID</th>
          <th>Name</th>
          <th style="width:90px">Credits</th>
          <th style="width:120px">Time</th>
          <th class="text-end" style="width:130px">Action</th>
        </tr>
      </thead>

      <tbody>
        @forelse($groups as $groupName => $items)
          @foreach($items as $svc)
            @php
              $remoteId = (string)($svc->remote_id ?? '');
              $name     = (string)($svc->name ?? '');
              $credit   = (float)($svc->price ?? 0); // ✅ price هو السعر الصحيح
              $time     = (string)($svc->time ?? '');
              $isAdded  = isset($existing[$remoteId]);

              // additional fields (json) موجودة في remote table
              $af = $svc->additional_fields ?? $svc->ADDITIONAL_FIELDS ?? null;
              $afJson = is_array($af) ? json_encode($af, JSON_UNESCAPED_UNICODE) : (string)$af;
            @endphp

            <tr data-row
                data-remote-id="{{ $remoteId }}"
                data-group="{{ strtolower($groupName) }}"
                data-name="{{ strtolower($name) }}"
                data-remote="{{ strtolower($remoteId) }}">
              <td>{{ $groupName }}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td style="min-width:520px;">{{ $name }}</td>
              <td>{{ number_format($credit, 4) }}</td>
              <td>{{ $time }}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>
                    Added ✅
                  </button>
                @else
                  <button type="button"
                          class="btn btn-success btn-sm clone-btn"
                          data-create-service
                          data-service-type="server"
                          data-provider-id="{{ $provider->id }}"
                          data-provider-name="{{ $providerName }}"
                          data-remote-id="{{ $remoteId }}"
                          data-group-name="{{ e($groupName) }}"
                          data-name="{{ e($name) }}"
                          data-credit="{{ number_format($credit, 4, '.', '') }}"
                          data-time="{{ e($time) }}"
                          data-additional-fields="{{ e($afJson) }}">
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

{{-- Required: Create Service Modal Template --}}
<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>
