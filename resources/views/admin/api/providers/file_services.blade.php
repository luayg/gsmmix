{{-- resources/views/admin/api/providers/file_services.blade.php --}}
@extends('layouts.admin')

@section('title', 'File Services')

@php
  $existing = \App\Models\FileService::where('supplier_id', $provider->id)
    ->pluck('remote_id')
    ->map(fn($v)=>(string)$v)
    ->flip()
    ->all();
@endphp

@section('content')
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">File Services - {{ $provider->name }}</h5>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped table-bordered align-middle">
        <thead>
          <tr>
            <th style="width:120px">Remote ID</th>
            <th>Name</th>
            <th style="width:120px">Credits</th>
            <th style="width:140px">Time</th>
            <th style="width:140px">Action</th>
          </tr>
        </thead>
        <tbody>
        @foreach ($services as $service)
          @php
            $remoteId = (string) data_get($service, 'SERVICEID', data_get($service,'REMOTEID',''));
            $name     = (string) data_get($service, 'SERVICENAME', data_get($service,'NAME',''));
            $credit   = (float) data_get($service, 'CREDIT', data_get($service,'PRICE',0));
            $time     = (string) data_get($service, 'TIME', '');

            $isAdded = $remoteId !== '' && isset($existing[$remoteId]);

            // ✅ IMPORTANT: additional fields لو متوفرة من API
            $af = data_get($service, 'ADDITIONAL_FIELDS', data_get($service,'additional_fields', null));
            $afJson = is_array($af)
              ? json_encode($af, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
              : (string)($af ?? '[]');

            $afJsonTrim = trim($afJson);
            if ($afJsonTrim === '' || $afJsonTrim === 'null') $afJsonTrim = '[]';
          @endphp

          <tr data-remote-id="{{ $remoteId }}">
            <td><code>{{ $remoteId }}</code></td>
            <td>{{ $name }}</td>
            <td>{{ number_format($credit, 4) }}</td>
            <td>{{ $time }}</td>
            <td>
              @if ($isAdded)
                <button type="button" class="btn btn-outline-primary btn-sm" disabled>
                  Added ✅
                </button>
              @else
                <button type="button"
                        class="btn btn-success btn-sm"
                        data-create-service
                        data-service-type="file"
                        data-provider-id="{{ $provider->id }}"
                        data-provider-name="{{ $provider->name }}"
                        data-remote-id="{{ $remoteId }}"
                        data-group-name=""
                        data-name="{{ e($name) }}"
                        data-credit="{{ number_format($credit, 4, '.', '') }}"
                        data-time="{{ e($time) }}"
                        data-additional-fields="{{ e($afJsonTrim) }}">
                  Clone
                </button>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<template id="serviceCreateTpl">
  @include('admin.services.file._modal_create')
</template>

@include('admin.partials.service-modal')
@endsection
