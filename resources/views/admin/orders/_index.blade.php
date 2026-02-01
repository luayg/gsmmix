<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">{{ $title ?? 'Orders' }}</h4>

  <button class="btn btn-success js-open-modal"
          data-url="{{ route($routePrefix.'.modal.create') }}">
    New order
  </button>
</div>

<form class="row g-2 mb-3" method="get">
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
      @foreach(($providers ?? collect([])) as $p)
        <option value="{{ $p->id }}" @selected((string)request('provider')===(string)$p->id)>
          {{ $p->name }}
        </option>
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
          <th style="width:80px">ID</th>
          <th style="width:170px">Date</th>
          <th>Device</th>
          <th>Service</th>
          <th style="width:160px">Provider</th>
          <th style="width:120px">Status</th>
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
                'waiting' => 'bg-secondary',
                'inprogress' => 'bg-info',
                'success' => 'bg-success',
                'rejected' => 'bg-danger',
                'cancelled' => 'bg-dark',
              ][$row->status] ?? 'bg-secondary';
            @endphp
            <span class="badge {{ $badge }}">{{ strtoupper($row->status) }}</span>
          </td>
          <td class="d-flex gap-2">
            <a href="#"
               class="btn btn-sm btn-primary js-open-modal"
               data-url="{{ route($routePrefix.'.modal.view', $row->id) }}">
              View
            </a>
            <a href="#"
               class="btn btn-sm btn-warning js-open-modal"
               data-url="{{ route($routePrefix.'.modal.edit', $row->id) }}">
              Edit
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center text-muted p-4">No orders</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($rows,'links'))
    <div class="card-body">
      {{ $rows->links() }}
    </div>
  @endif
</div>
