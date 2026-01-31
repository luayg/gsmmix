<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">{{ $pageTitle ?? 'Orders' }}</h4>

  <button class="btn btn-success js-open-modal"
          data-url="{{ route($routePrefix.'.modal.create') }}">
    New order
  </button>
</div>

<form class="d-flex gap-2 mb-3" method="get">
  <input type="text" class="form-control" name="q" value="{{ request('q') }}"
         placeholder="Search: IMEI / remote id / email">

  <select class="form-select" name="status" style="max-width: 220px;">
    <option value="">All status</option>
    @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
      <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
    @endforeach
  </select>

  <select class="form-select" name="supplier_id" style="max-width: 220px;">
    <option value="">All providers</option>
    @foreach($providers as $p)
      <option value="{{ $p->id }}" @selected((string)request('supplier_id')===(string)$p->id)>
        {{ $p->name }}
      </option>
    @endforeach
  </select>

  <button class="btn btn-primary">Go</button>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
      <tr>
        <th style="width:90px;">ID</th>
        <th style="width:170px;">Date</th>
        <th style="width:220px;">{{ $deviceLabel ?? 'Device' }}</th>
        <th>Service</th>
        <th style="width:160px;">Provider</th>
        <th style="width:130px;">Status</th>
        <th style="width:160px;">Actions</th>
      </tr>
      </thead>
      <tbody>
      @forelse($rows as $row)
        <tr>
          <td>#{{ $row->id }}</td>
          <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
          <td>{{ $row->device }}</td>
          <td>{{ optional($row->service)->name ?? '—' }}</td>
          <td>{{ optional($row->provider)->name ?? '—' }}</td>
          <td>
            @php
              $st = $row->status ?? 'waiting';
              $badge = match($st){
                'success' => 'bg-success',
                'rejected' => 'bg-danger',
                'cancelled' => 'bg-secondary',
                'inprogress' => 'bg-primary',
                default => 'bg-warning'
              };
            @endphp
            <span class="badge {{ $badge }}">{{ strtoupper($st) }}</span>
            @if(!empty($row->remote_id))
              <div class="small text-muted">Ref: {{ $row->remote_id }}</div>
            @endif
          </td>
          <td>
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
        <tr><td colspan="7" class="text-center text-muted p-4">No orders</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  {{ $rows->links() }}
</div>
