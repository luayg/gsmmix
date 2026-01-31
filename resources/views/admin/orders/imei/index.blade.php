@extends('layouts.admin')

@section('title','IMEI Orders')

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">IMEI Orders</h4>

    <button class="btn btn-success js-open-modal"
            data-url="{{ route($routePrefix.'.modal.create') }}">
      New order
    </button>
  </div>

  <form class="row g-2 mb-3" method="GET">
    <div class="col-md-5">
      <input type="text" class="form-control" name="q" value="{{ request('q') }}"
             placeholder="Search: IMEI / remote id / email">
    </div>

    <div class="col-md-3">
      <select class="form-select" name="status">
        <option value="">All status</option>
        @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
          <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-3">
      <select class="form-select" name="provider">
        <option value="">All providers</option>
        @foreach($providers as $p)
          <option value="{{ $p->id }}" @selected((string)request('provider')===(string)$p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-1">
      <button class="btn btn-primary w-100">Go</button>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Date</th>
            <th>Device</th>
            <th>Service</th>
            <th>Provider</th>
            <th>Status</th>
            <th style="width:140px">Actions</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
          <tr>
            <td>#{{ $row->id }}</td>
            <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
            <td>{{ $row->device }}</td>
            <td>{{ $row->service?->display_name ?? '—' }}</td>
            <td>{{ $row->provider?->name ?? '—' }}</td>
            <td>
              @php
                $map = [
                  'waiting'=>'secondary',
                  'inprogress'=>'info',
                  'success'=>'success',
                  'rejected'=>'danger',
                  'cancelled'=>'dark',
                ];
                $bg = $map[$row->status] ?? 'secondary';
              @endphp
              <span class="badge bg-{{ $bg }}">{{ strtoupper($row->status) }}</span>
              @if($row->remote_id)
                <div class="small text-muted">Ref: {{ $row->remote_id }}</div>
              @endif
            </td>
            <td>
              <button class="btn btn-sm btn-primary js-open-modal"
                      data-url="{{ route($routePrefix.'.modal.view',$row) }}">View</button>
              <button class="btn btn-sm btn-warning js-open-modal"
                      data-url="{{ route($routePrefix.'.modal.edit',$row) }}">Edit</button>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No orders</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    {{ $rows->links() }}
  </div>
</div>
@endsection
