{{-- resources/views/admin/api/providers/server_services.blade.php --}}
@extends('layouts.admin')

@php
  // ✅ خدمات السيرفر الموجودة محلياً (لمنع إعادة الإضافة بعد refresh)
  $existing = \App\Models\ServerService::where('supplier_id', $provider->id)
    ->pluck('remote_id')
    ->map(fn($v) => (string)$v)
    ->flip()
    ->all();
@endphp

@section('content')
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h5 class="mb-0">{{ $provider->name }} | SERVER Remote services</h5>

    <div class="d-flex gap-2">
      {{-- ✅ Import page (standalone) --}}
      <a class="btn btn-dark btn-sm"
         href="{{ route('admin.apis.remote.server.import_page', $provider) }}">
        Import Services with Group
      </a>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0 align-middle">
        <thead>
          <tr>
            <th>Group</th>
            <th style="width:110px">Remote ID</th>
            <th>Name</th>
            <th style="width:90px">Credits</th>
            <th style="width:120px">Time</th>
            <th class="text-end" style="width:140px">Action</th>
          </tr>
        </thead>

        <tbody>
        @forelse($groups as $groupName => $items)
          @foreach($items as $svc)
            @php
              $remoteId = (string)($svc->remote_id ?? '');
              $name     = (string)($svc->name ?? '');
              $credit   = (float)($svc->price ?? 0);   // ✅ السعر مخزن في price
              $time     = (string)($svc->time ?? '');
              $info = (string)($svc->info ?? '');
              $isAdded  = isset($existing[$remoteId]);

              // ✅ additional fields (قد تكون array أو json string)
              $af = $svc->additional_fields ?? $svc->fields ?? null;
              $afJson = is_array($af) ? json_encode($af, JSON_UNESCAPED_UNICODE) : (string)($af ?? '[]');
            @endphp

            <tr data-remote-id="{{ $remoteId }}">
              <td>{{ $groupName }}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td>{{ $name }}</td>
              <td>{{ number_format($credit, 4) }}</td>
              <td>{{ $time }}</td>
              <td class="text-end">
                @if($isAdded)
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>
                    Added ✅
                  </button>
                @else
                  <button type="button" class="btn btn-success btn-sm clone-btn"
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
                    data-info-b64="{{ e(base64_encode($info)) }}"
                    data-provider-base-url="{{ e(rtrim((string)$provider->url, '/')) }}"
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
</div>

{{-- ✅ Template required for Service Modal Clone --}}
<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>

{{-- ✅ Service modal (لازم موجود) --}}
@include('admin.partials.service-modal')
@endsection
