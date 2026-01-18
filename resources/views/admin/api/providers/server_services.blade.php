{{-- resources/views/admin/api/providers/server_services.blade.php --}}
@extends('layouts.admin')

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
              $remoteId = $svc->remote_id;
              $name     = $svc->name ?? '';
              // ✅ الصحيح: السعر مخزن في price (ليس credit)
              $credit   = $svc->price ?? 0;
              $time     = $svc->time ?? '';
            @endphp
            <tr>
              <td>{{ $groupName }}</td>
              <td><code>{{ $remoteId }}</code></td>
              <td>{{ $name }}</td>
              <td>{{ $credit }}</td>
              <td>{{ $time }}</td>
              <td class="text-end">
                <button type="button" class="btn btn-success btn-sm"
                  data-create-service
                  data-service-type="server"
                  data-provider-id="{{ $provider->id }}"
                  data-remote-id="{{ $remoteId }}"
                  data-group-name="{{ $groupName }}"
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
</div>

<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>
@endsection
