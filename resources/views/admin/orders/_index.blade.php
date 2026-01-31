<div class="d-flex align-items-center gap-2 mb-3">
  <button class="btn btn-success js-open-modal"
          data-url="{{ route($routePrefix.'.modal.create') }}"
          data-title="New order">
    New order
  </button>

  <form class="ms-auto d-flex gap-2" method="get">
    <input type="text" class="form-control" name="q" value="{{ request('q') }}"
           placeholder="Search: IMEI / remote id / email">

    <select class="form-select" name="status">
      <option value="">All status</option>
      @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
        <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
      @endforeach
    </select>

    <select class="form-select" name="provider">
      <option value="">All providers</option>
      @foreach($providers as $p)
        <option value="{{ $p->id }}" @selected((string)request('provider')===(string)$p->id)>{{ $p->name }}</option>
      @endforeach
    </select>

    <button class="btn btn-primary">Go</button>
  </form>
</div>

<div class="table-responsive">
<table class="table table-striped align-middle">
  <thead>
    <tr>
      <th style="width:80px;">ID</th>
      <th style="width:160px;">Date</th>
      <th>Device</th>
      <th>Service</th>
      <th style="width:140px;">Provider</th>
      <th style="width:140px;">Status</th>
      <th style="width:180px;">Actions</th>
    </tr>
  </thead>
  <tbody>
  @foreach($rows as $row)
    <tr>
      <td>{{ $row->id }}</td>
      <td>{{ $row->created_at }}</td>
      <td>{{ $row->device }}</td>
      <td>{{ $row->service?->name ?? '—' }}</td>
      <td>{{ $row->provider?->name ?? '—' }}</td>
      <td>
        @php $st = (string)$row->status; @endphp
        <span class="badge
          @if($st==='success') bg-success
          @elseif($st==='inprogress') bg-info
          @elseif($st==='waiting') bg-secondary
          @elseif($st==='rejected') bg-danger
          @elseif($st==='cancelled') bg-dark
          @else bg-secondary
          @endif
        ">
          {{ strtoupper($st) }}
        </span>

        @if($row->remote_id)
          <div class="small text-muted">Ref: {{ $row->remote_id }}</div>
        @endif
      </td>
      <td>
        <button class="btn btn-sm btn-primary js-open-modal"
                data-url="{{ route($routePrefix.'.modal.view', $row->id) }}"
                data-title="Order #{{ $row->id }} | View">
          View
        </button>

        <button class="btn btn-sm btn-warning js-open-modal"
                data-url="{{ route($routePrefix.'.modal.edit', $row->id) }}"
                data-title="Order #{{ $row->id }} | Edit">
          Edit
        </button>
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>

{{ $rows->links() }}
