{{-- resources/views/admin/orders/_index.blade.php --}}
@php
  /**
   * Expected variables from BaseOrdersController@index:
   * $title, $kind, $routePrefix, $rows (Paginator), $providers (Collection)
   */
  $title       = $title ?? 'Orders';
  $routePrefix = $routePrefix ?? 'admin.orders.imei';
  $kind        = $kind ?? 'imei';

  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) return $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
      return $v;
    }
    return (string)$v;
  };

  $q      = request('q', '');
  $status = request('status', '');
  $prov   = request('provider', '');
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">{{ $title }}</h4>

  <button
    class="btn btn-success js-open-modal"
    data-url="{{ route($routePrefix . '.modal.create') }}">
    New order
  </button>
</div>

{{-- Filters --}}
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" action="{{ route($routePrefix.'.index') }}" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="device / email / remote id">
      </div>

      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
            <option value="{{ $st }}" @selected($status===$st)>{{ ucfirst($st) }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Provider</label>
        <select name="provider" class="form-select">
          <option value="">All</option>
          @foreach(($providers ?? collect()) as $p)
            <option value="{{ $p->id }}" @selected((string)$prov === (string)$p->id)>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Apply</button>
        <a class="btn btn-light w-100" href="{{ route($routePrefix.'.index') }}">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-striped table-bordered align-middle mb-0">
        <thead>
          <tr>
            <th style="width:90px;">ID</th>
            <th style="width:170px;">Date</th>
            <th>Device</th>
            <th>Service</th>
            <th style="width:160px;">Provider</th>
            <th style="width:130px;">Status</th>
            <th style="width:160px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse(($rows ?? []) as $o)
            @php($st = strtolower($o->status ?? 'waiting'))
            <tr>
              <td>{{ $o->id }}</td>
              <td>{{ optional($o->created_at)->format('Y-m-d H:i') }}</td>
              <td>{{ $o->device ?? '-' }}</td>

              <td>
                @if($o->service)
                  {{ $pickName($o->service->name ?? '—') }}
                @else
                  —
                @endif
              </td>

              <td>{{ $o->provider?->name ?? '—' }}</td>

              <td>
                @if($st === 'success')
                  <span class="badge bg-success">SUCCESS</span>
                @elseif($st === 'rejected')
                  <span class="badge bg-danger">REJECTED</span>
                @elseif($st === 'inprogress')
                  <span class="badge bg-info">IN PROGRESS</span>
                @elseif($st === 'cancelled')
                  <span class="badge bg-dark">CANCELLED</span>
                @else
                  <span class="badge bg-secondary">WAITING</span>
                @endif
              </td>

              <td class="text-nowrap">
                <a class="btn btn-sm btn-primary js-open-modal"
                   data-url="{{ route($routePrefix . '.modal.view', $o->id) }}">View</a>

                {{-- ✅ isolated edit --}}
                <a class="btn btn-sm btn-warning js-open-order-edit"
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

    {{-- Pagination --}}
    @if(isset($rows) && method_exists($rows, 'links'))
      <div class="mt-3">
        {!! $rows->links() !!}
      </div>
    @endif

  </div>
</div>

{{-- ✅ IMPORTANT: put the isolated modal in the modals stack (like service-modal) --}}
@push('modals')
  <div class="modal fade" id="orderEditModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content"></div>
    </div>
  </div>
@endpush
