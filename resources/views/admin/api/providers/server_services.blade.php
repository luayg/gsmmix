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
  <div class="card-header">
    <h5 class="mb-0">{{ $provider->name }} | SERVER services</h5>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-striped mb-0">
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
              // ✅ الصحيح: السعر مخزن في price (ليس credit)
              $credit   = (float)($svc->price ?? 0);
              $time     = (string)($svc->time ?? '');

              // ✅ IMPORTANT: additional_fields من الريموت (قد تكون JSON string)
              $af = $svc->additional_fields ?? $svc->ADDITIONAL_FIELDS ?? null;
              $afJson = is_array($af)
                  ? json_encode($af, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                  : (string)($af ?? '[]');

              // ✅ تأكد أنها دائماً JSON array string
              $afJsonTrim = trim($afJson);
              if ($afJsonTrim === '' || $afJsonTrim === 'null') $afJsonTrim = '[]';

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
                  <button type="button" class="btn btn-outline-primary btn-sm" disabled>
                    Added ✅
                  </button>
                @else
                  <button type="button" class="btn btn-success btn-sm"
                    data-create-service
                    data-service-type="server"
                    data-provider-id="{{ $provider->id }}"
                    data-provider-name="{{ $provider->name }}"
                    data-remote-id="{{ $remoteId }}"
                    data-group-name="{{ e($groupName) }}"
                    data-name="{{ e($name) }}"
                    data-credit="{{ number_format($credit, 4, '.', '') }}"
                    data-time="{{ e($time) }}"
                    {{-- ✅ هذا هو المفتاح --}}
                    data-additional-fields="{{ e($afJsonTrim) }}"
                  >
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
  @include('admin.services.server._modal_create')
</template>

{{-- ✅ لازم يكون partial service-modal موجود (إن لم يكن موجود في layout) --}}
@include('admin.partials.service-modal')

@endsection
