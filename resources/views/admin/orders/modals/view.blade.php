{{-- resources/views/admin/orders/modals/view.blade.php --}}

@php
  $pretty = function ($v) {
    if (is_array($v)) return json_encode($v, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (is_string($v)) {
      $decoded = json_decode($v, true);
      if (is_array($decoded)) return json_encode($decoded, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      return $v;
    }
    return (string)$v;
  };

  $badge = [
    'waiting'    => 'bg-secondary',
    'inprogress' => 'bg-info',
    'success'    => 'bg-success',
    'rejected'   => 'bg-danger',
    'cancelled'  => 'bg-dark',
  ][$row->status] ?? 'bg-secondary';
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? ('View Order #'.$row->id) }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <div class="row g-3">

    <div class="col-12">
      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <tbody>
            <tr>
              <th style="width:220px">Status</th>
              <td><span class="badge {{ $badge }}">{{ strtoupper($row->status) }}</span></td>
            </tr>
            <tr><th>Created at</th><td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td></tr>
            <tr><th>Service</th><td>{{ $row->service?->name ?? '—' }}</td></tr>
            <tr><th>Device</th><td>{{ $row->device ?? '—' }}</td></tr>
            <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
            <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
            <tr><th>Remote ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
            <tr><th>Price</th><td>{{ $row->price ?? 0 }}</td></tr>
            <tr><th>Order price</th><td>{{ $row->order_price ?? 0 }}</td></tr>
            <tr><th>Profit</th><td>{{ $row->profit ?? 0 }}</td></tr>
            <tr><th>Comments</th><td>{{ $row->comments ?: '—' }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">Request</label>
      <pre class="border rounded p-3 bg-light" style="max-height:260px; overflow:auto;">{{ $pretty($row->request) }}</pre>
    </div>

    <div class="col-12">
      <label class="form-label">Response</label>
      <pre class="border rounded p-3 bg-light" style="max-height:260px; overflow:auto;">{{ $pretty($row->response) }}</pre>
    </div>

  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
  <a href="#"
     class="btn btn-warning js-open-modal"
     data-url="{{ route($routePrefix.'.modal.edit', $row->id) }}">
    Edit
  </a>
</div>
