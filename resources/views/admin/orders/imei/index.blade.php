@extends('layouts.admin')

@section('title','IMEI Orders')

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">IMEI Orders</h4>

    <button class="btn btn-success js-open-modal"
      data-url="{{ route($routePrefix.'.modal.create') }}">
      New order
    </button>
  </div>

  <form class="row g-2 mb-3" method="GET">
    <div class="col-md-5">
      <input type="text" class="form-control" name="q" value="{{ request('q') }}" placeholder="Search: IMEI / remote id / email">
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
      <select class="form-select" name="provider_id">
        <option value="0">All providers</option>
        @foreach($providers as $p)
          <option value="{{ $p->id }}" @selected((int)request('provider_id')===(int)$p->id)>{{ $p->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-1 d-grid">
      <button class="btn btn-primary">Go</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th style="width:80px">ID</th>
              <th style="width:180px">Date</th>
              <th>Device</th>
              <th>Service</th>
              <th style="width:160px">Provider</th>
              <th style="width:130px">Status</th>
              <th style="width:160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $row)
              <tr>
                <td>#{{ $row->id }}</td>
                <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
                <td>{{ $row->device }}</td>
                <td>{{ $row->service?->name ?? '—' }}</td>
                <td>{{ $row->provider?->name ?? '—' }}</td>
                <td>
                  @php
                    $badge = [
                      'waiting'=>'bg-secondary',
                      'inprogress'=>'bg-primary',
                      'success'=>'bg-success',
                      'rejected'=>'bg-danger',
                      'cancelled'=>'bg-dark',
                    ][$row->status] ?? 'bg-secondary';
                  @endphp
                  <span class="badge {{ $badge }}">{{ strtoupper($row->status) }}</span>
                  @if(!empty($row->remote_id))
                    <div class="small text-muted">Ref: {{ $row->remote_id }}</div>
                  @endif
                </td>
                <td class="d-flex gap-2">
                  <a class="btn btn-sm btn-primary js-open-modal"
                     href="{{ route($routePrefix.'.modal.view', $row->id) }}">
                    View
                  </a>
                  <a class="btn btn-sm btn-warning js-open-modal"
                     href="{{ route($routePrefix.'.modal.edit', $row->id) }}">
                    Edit
                  </a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No orders</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(method_exists($rows,'links'))
      <div class="card-footer">
        {{ $rows->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
