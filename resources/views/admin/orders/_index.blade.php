{{-- resources/views/admin/orders/_index.blade.php --}}
@php
  /**
   * Required variables (will fallback safely):
   * $title, $routePrefix
   */
  $title = $title ?? 'Orders';
  $routePrefix = $routePrefix ?? 'admin.orders.imei'; // ✅ fallback يمنع 500
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">{{ $title }}</h4>

  <button
    class="btn btn-success js-open-modal"
    data-url="{{ route($routePrefix . '.modal.create') }}">
    New order
  </button>
</div>

<div class="card">
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-bordered align-middle mb-0 dataTable">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:170px;">Date</th>
            <th>Device</th>
            <th>Service</th>
            <th style="width:140px;">Provider</th>
            <th style="width:120px;">Status</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse(($orders ?? []) as $o)
            <tr>
              <td>{{ $o->id }}</td>
              <td>{{ $o->created_at }}</td>
              <td>{{ $o->device ?? $o->imei ?? '-' }}</td>
              <td>{{ $o->service_name ?? '-' }}</td>
              <td>{{ $o->provider_name ?? $o->provider ?? '-' }}</td>
              <td>
                @php($st = strtolower($o->status ?? ''))
                @if($st === 'success')
                  <span class="badge bg-success">SUCCESS</span>
                @elseif($st === 'rejected' || $st === 'reject')
                  <span class="badge bg-danger">REJECTED</span>
                @elseif($st === 'waiting')
                  <span class="badge bg-secondary">WAITING</span>
                @else
                  <span class="badge bg-light text-dark">{{ $o->status ?? '-' }}</span>
                @endif
              </td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-primary js-open-modal"
                   data-url="{{ route($routePrefix . '.modal.view', $o->id) }}">View</a>

                <a class="btn btn-sm btn-warning js-open-modal"
                   data-url="{{ route($routePrefix . '.modal.edit', $o->id) }}">Edit</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">No orders</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

  </div>
</div>
