<div class="modal-header" style="background:#3bb37a;color:#fff;">
  <div>
    <div class="h6 mb-0">{{ $provider->name }} | SERVER remote services</div>
    <div class="small opacity-75">Clone services (with additional fields)</div>
  </div>

  <div class="d-flex gap-2 align-items-center ms-auto">
    <input id="svcSearch"
           type="text"
           class="form-control form-control-sm"
           placeholder="Search (name / group / remote id)..."
           style="width:min(420px, 46vw);">

    <a class="btn btn-dark btn-sm"
       href="#"
       class="js-api-modal"
       data-url="{{ route('admin.apis.remote.server.import_page', $provider) }}">
      Import Services with Category
    </a>

    <button type="button" class="btn-close btn-close-white ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
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
              $rid   = (string)($svc->remote_id ?? '');
              $name  = (string)($svc->name ?? '');
              $credit= (float)($svc->price ?? $svc->credit ?? 0);
              $time  = (string)($svc->time ?? '');
              $isAdded = isset($existing[$rid]);

              $af = $svc->additional_fields ?? $svc->fields ?? null;
              $afJson = is_string($af) ? $af : json_encode($af ?? [], JSON_UNESCAPED_UNICODE);
            @endphp

            <tr data-row
                data-group="{{ strtolower($groupName) }}"
                data-name="{{ strtolower($name) }}"
                data-remote="{{ strtolower($rid) }}"
                data-remote-id="{{ $rid }}">
              <td>{{ $groupName }}</td>
              <td><code>{{ $rid }}</code></td>
              <td style="min-width:520px;">{{ $name }}</td>
              <td>{{ number_format($credit, 4) }}</td>
              <td>{{ $time }}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>Added ✅</button>
                @else
                  <button type="button"
                          class="btn btn-success btn-sm clone-btn"
                          data-create-service
                          data-service-type="server"
                          data-provider-id="{{ $provider->id }}"
                          data-provider-name="{{ $provider->name }}"
                          data-remote-id="{{ $rid }}"
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

{{-- Template required for Service Modal Clone --}}
<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>

@include('admin.partials.service-modal')

<script>
(function(){
  const svcSearch = document.getElementById('svcSearch');
  svcSearch?.addEventListener('input', () => {
    const q = (svcSearch.value || '').trim().toLowerCase();
    document.querySelectorAll('#svcTable tr[data-row]').forEach(tr => {
      const hit =
        tr.dataset.group.includes(q) ||
        tr.dataset.name.includes(q) ||
        tr.dataset.remote.includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });
})();
</script>
