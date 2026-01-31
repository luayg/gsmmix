{{-- resources/views/admin/orders/_index.blade.php --}}

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="{{ route($routePrefix.'.modal.create') }}"
     class="btn btn-success js-open-modal"
     data-title="New order">
    New order
  </a>

  <form class="ms-auto d-flex gap-2" method="get">
    <input type="text" class="form-control" name="q" value="{{ request('q') }}"
           placeholder="Search: IMEI / remote id / email">

    <select class="form-select" name="status" style="min-width: 160px">
      <option value="">All status</option>
      @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
        <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
      @endforeach
    </select>

    <select class="form-select" name="supplier_id" style="min-width: 170px">
      <option value="">All providers</option>
      @foreach($providers as $p)
        <option value="{{ $p->id }}" @selected((string)request('supplier_id')===(string)$p->id)>
          {{ $p->name }}
        </option>
      @endforeach
    </select>

    <button class="btn btn-primary">Go</button>
  </form>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
        <tr>
          <th style="width: 70px;">ID</th>
          <th style="width: 170px;">Date</th>
          <th>Device</th>
          <th>Service</th>
          <th style="width: 160px;">Provider</th>
          <th style="width: 130px;">Status</th>
          <th style="width: 160px;">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
          <tr>
            <td>#{{ $row->id }}</td>
            <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
            <td>{{ $row->device }}</td>
            <td>{{ optional($row->service)->name_json['en'] ?? optional($row->service)->name ?? '—' }}</td>
            <td>{{ optional($row->provider)->name ?? '—' }}</td>
            <td>
              @php($st = (string)($row->status ?? 'waiting'))
              <span class="badge
                @if($st==='success') bg-success
                @elseif($st==='rejected' || $st==='cancelled') bg-danger
                @elseif($st==='inprogress') bg-primary
                @else bg-secondary
                @endif
              ">
                {{ strtoupper($st) }}
              </span>

              @if(!empty($row->remote_id))
                <div class="text-muted small">Ref: {{ $row->remote_id }}</div>
              @endif
            </td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-primary js-open-modal"
                 href="{{ route($routePrefix.'.modal.view', $row) }}">View</a>
              <a class="btn btn-sm btn-warning js-open-modal"
                 href="{{ route($routePrefix.'.modal.edit', $row) }}">Edit</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center py-4 text-muted">No orders</td>
          </tr>
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
