@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">{{ $title }}</h4>

    <button class="btn btn-success js-open-modal"
            data-url="{{ route($routePrefix.'.modal.create') }}">
      New order
    </button>
  </div>

  <form class="row g-2 mb-3" method="get" action="{{ route($routePrefix.'.index') }}">
    <div class="col-md-5">
      <input type="text" class="form-control" name="q" value="{{ request('q') }}"
             placeholder="Search: IMEI / remote id / email / id">
    </div>

    <div class="col-md-3">
      <select class="form-select" name="status">
        <option value="all">All status</option>
        @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
          <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-3">
      <select class="form-select" name="provider_id">
        <option value="all">All providers</option>
        @foreach($providers as $p)
          <option value="{{ $p->id }}" @selected((string)request('provider_id')===(string)$p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-1 d-grid">
      <button class="btn btn-primary">Go</button>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th style="width:90px">ID</th>
            <th style="width:160px">Date</th>
            <th>Device</th>
            <th>Service</th>
            <th style="width:160px">Provider</th>
            <th style="width:140px">Status</th>
            <th style="width:140px">Actions</th>
          </tr>
        </thead>

        <tbody>
          @forelse($rows as $row)
            @php
              $svcName = \App\Http\Controllers\Admin\Orders\BaseOrdersController::serviceNameText($row->service?->name);
            @endphp
            <tr>
              <td>#{{ $row->id }}</td>
              <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
              <td>{{ $row->device }}</td>
              <td>{{ $svcName }}</td>
              <td>{{ $row->provider?->name ?? '—' }}</td>
              <td>
                @php
                  $badge = [
                    'waiting'=>'secondary',
                    'inprogress'=>'info',
                    'success'=>'success',
                    'rejected'=>'danger',
                    'cancelled'=>'dark',
                  ][$row->status] ?? 'secondary';
                @endphp
                <span class="badge bg-{{ $badge }}">{{ strtoupper($row->status) }}</span>
              </td>
              <td class="d-flex gap-2">
                <a class="btn btn-sm btn-primary js-open-modal"
                   data-url="{{ route($routePrefix.'.modal.view', $row->id) }}">View</a>

                <a class="btn btn-sm btn-warning js-open-modal"
                   data-url="{{ route($routePrefix.'.modal.edit', $row->id) }}">Edit</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted p-4">No orders</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-body">
      {{ $rows->links() }}
    </div>
  </div>

</div>
@endsection
