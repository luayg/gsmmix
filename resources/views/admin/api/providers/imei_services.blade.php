{{-- resources/views/admin/api/providers/imei_services.blade.php --}}
@extends('admin.layout')

@section('content')
<div class="card">
  <div class="card-header"><h5 class="mb-0">{{ $provider->name }} | IMEI services</h5></div>
  <div class="card-body p-0">
    <table class="table table-sm table-striped mb-0">
      <thead><tr><th>Group</th><th>Remote ID</th><th>Name</th><th>Credits</th><th>Time</th><th class="text-end">Action</th></tr></thead>
      <tbody>
      @forelse($groups as $g)
        @foreach(($g['SERVICES'] ?? []) as $rid => $svc)
          @php
            $remoteId = $svc['SERVICEID'] ?? $rid;
            $name     = $svc['SERVICENAME'] ?? '';
            $credit   = $svc['CREDIT'] ?? 0;
            $time     = $svc['TIME'] ?? '';
          @endphp
          <tr>
            <td>{{ $g['GROUPNAME'] ?? '' }}</td>
            <td>{{ $remoteId }}</td>
            <td>{{ $name }}</td>
            <td>{{ $credit }}</td>
            <td>{{ $time }}</td>
            <td class="text-end">
              <button type="button" class="btn btn-success btn-sm"
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
        <tr><td colspan="6" class="text-center p-4">No data</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<template id="serviceCreateTpl">
  @include('admin.services.imei._modal_create')
</template>
@endsection
