{{-- resources/views/admin/api/remote/server/page.blade.php --}}
@extends('admin.layouts.app')

@section('content')
  <div class="container-fluid py-3">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-0">{{ $provider->name }} — SERVER remote services</h4>
        <div class="text-muted small">Standalone page (with full admin layout & assets)</div>
      </div>

      <div class="d-flex gap-2">
        <a href="{{ route('admin.apis.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        <a href="{{ route('admin.apis.services.server.import_page', $provider) }}" class="btn btn-primary btn-sm">
          Import Services
        </a>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <input id="svcSearch"
               type="text"
               class="form-control form-control-sm"
               placeholder="Search (name / group / remote id)..."
               style="width:min(520px, 60vw);">

        <div class="ms-auto small text-muted">
          Total: {{ isset($groups) ? $groups->flatten(1)->count() : 0 }}
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0 align-middle" id="svcTable">
            <thead>
              <tr>
                <th style="width:220px">Group</th>
                <th style="width:110px">Remote ID</th>
                <th>Name</th>
                <th style="width:100px">Credits</th>
                <th style="width:140px">Time</th>
                <th class="text-end" style="width:140px">Action</th>
              </tr>
            </thead>

            <tbody>
            @forelse($groups as $groupName => $items)
              @foreach($items as $svc)
                @php
                  $rid   = (string)($svc->remote_id ?? $svc->REMOTEID ?? $svc->id ?? '');
                  $name  = (string)($svc->name ?? $svc->NAME ?? '');
                  $time  = (string)($svc->time ?? $svc->TIME ?? '');
                  $credit = (float)($svc->price ?? $svc->credit ?? $svc->CREDIT ?? 0);

                  $af = $svc->additional_fields ?? $svc->ADDITIONAL_FIELDS ?? $svc->fields ?? null;
                  $afJson = is_array($af) ? json_encode($af, JSON_UNESCAPED_UNICODE) : (string)($af ?? '[]');

                  $isAdded = isset($existing[$rid]);
                @endphp

                <tr data-row
                    data-group="{{ strtolower($groupName) }}"
                    data-name="{{ strtolower(strip_tags($name)) }}"
                    data-remote="{{ strtolower($rid) }}"
                    data-remote-id="{{ $rid }}">
                  <td>{{ $groupName }}</td>
                  <td><code>{{ $rid }}</code></td>
                  <td style="min-width:520px;">{!! $name !!}</td>
                  <td>{{ number_format($credit, 4) }}</td>
                  <td>{!! $time !!}</td>
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
                              data-credit="{{ number_format($credit, 4, '.', '') }}"
                              data-time="{{ e(strip_tags($time)) }}"
                              data-additional-fields="{{ e($afJson) }}">
                        Clone
                      </button>
                    @endif
                  </td>
                </tr>
              @endforeach
            @empty
              <tr><td colspan="6" class="text-center p-4 text-muted">No data</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Template required for Create Service Modal (Clone) --}}
    <template id="serviceCreateTpl">
      @include('admin.services.server._modal_create')
    </template>

  </div>
@endsection

@push('scripts')
<script>
(function(){
  const svcSearch = document.getElementById('svcSearch');
  svcSearch?.addEventListener('input', () => {
    const q = (svcSearch.value || '').trim().toLowerCase();
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