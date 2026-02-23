@extends('layouts.admin')

@section('title', 'Server Remote Services')

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <div>
      <h5 class="mb-0">{{ $provider->name }} | SERVER Remote Services</h5>
      <div class="small text-muted">Clone is handled here. Import is a separate page.</div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('admin.apis.remote.server.import_page', $provider) }}"
         class="btn btn-dark btn-sm">
        Import Services with Group
      </a>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0" id="remoteServerTable">
        <thead>
          <tr>
            <th>Group</th>
            <th>Remote ID</th>
            <th>Name</th>
            <th>Credits</th>
            <th>Time</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($groups as $groupName => $items)
          @foreach($items as $svc)
            @php
              $remoteId = (string)($svc->remote_id ?? '');
              $name     = (string)($svc->name ?? '');
              $credit   = (float)($svc->price ?? 0);
              $time     = (string)($svc->time ?? '');
              $info = (string)($svc->info ?? '');
              $infoB64 = base64_encode($info);
              $af       = $svc->additional_fields ?? null;
              $afJson   = is_array($af) ? json_encode($af, JSON_UNESCAPED_UNICODE) : (string)$af;
              $isAdded  = isset($existing[$remoteId]);
            @endphp

            <tr data-remote-id="{{ $remoteId }}">
              <td>{{ $groupName }}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td>{{ $name }}</td>
              <td>{{ number_format($credit, 4) }}</td>
              <td>{{ $time }}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>Added ✅</button>
                @else
                  <button type="button"
                          class="btn btn-success btn-sm js-clone-server"
                          data-create-service
                          data-service-type="server"
                          data-provider-id="{{ $provider->id }}"
                          data-provider-name="{{ $provider->name }}"
                          data-remote-id="{{ $remoteId }}"
                          data-group-name="{{ e($groupName) }}"
                          data-name="{{ e($name) }}"
                          data-credit="{{ number_format($credit, 4, '.', '') }}"
                          data-time="{{ e($time) }}"
                          data-info="{{ e($info) }}"
                          data-info-b64="{{ e($infoB64) }}"
                          data-additional-fields="{{ e($afJson) }}"
                          data-provider-base-url="{{ e(rtrim((string)$provider->url, "/")) }}">
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

{{-- Template required for Service Modal Clone --}}
<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>

{{-- ✅ JS خاص للـ Clone فقط --}}
@vite(['resources/js/admin/remote-server-clone.js'])
@endsection