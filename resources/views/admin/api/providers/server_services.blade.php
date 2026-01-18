{{-- resources/views/admin/api/providers/server_services.blade.php --}}
@extends('admin.layout')

@section('content')
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">{{ $provider->name }} | SERVER services</h5>
  </div>

  <div class="card-body p-0">
    <table class="table table-sm table-striped mb-0">
      <thead>
        <tr>
          <th>Group</th>
          <th style="width:110px">Remote ID</th>
          <th>Name</th>
          <th style="width:90px">Credits</th>
          <th style="width:110px">Time</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>

      <tbody>
      @forelse($groups as $groupName => $items)
        @foreach($items as $svc)
          @php
            $remoteId = $svc->remote_id;
            $name     = $svc->name ?? '';
            $credit   = $svc->credit ?? 0;
            $time     = $svc->time ?? '';
          @endphp
          <tr>
            <td>{{ $groupName }}</td>
            <td>{{ $remoteId }}</td>
            <td>{{ $name }}</td>
            <td>{{ $credit }}</td>
            <td>{{ $time }}</td>
            <td class="text-end">
              <button type="button" class="btn btn-success btn-sm"
                data-create-service
                data-service-type="server"
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
  @include('admin.services.server._modal_create')
</template>
@endsection
