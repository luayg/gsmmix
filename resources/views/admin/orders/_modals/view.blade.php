@php
  $svcName = \App\Http\Controllers\Admin\Orders\BaseOrdersController::serviceNameText($order->service?->name);
@endphp

<div class="modal-header">
  <h5 class="modal-title">Order #{{ $order->id }} | View</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <tr><th style="width:220px">Service</th><td>{{ $svcName }}</td></tr>
      <tr><th>User</th><td>{{ $order->email ?? '—' }}</td></tr>
      <tr><th>Device</th><td>{{ $order->device }}</td></tr>
      <tr><th>Comments</th><td>{{ $order->comments ?: '—' }}</td></tr>
      <tr><th>Order date</th><td>{{ optional($order->created_at)->format('Y-m-d H:i:s') }}</td></tr>
      <tr><th>Order IP</th><td>{{ $order->ip ?? '—' }}</td></tr>
      <tr><th>Ordered via API</th><td>{{ $order->api_order ? 'Yes' : 'No' }}</td></tr>
      <tr><th>Status</th><td><strong>{{ strtoupper($order->status) }}</strong></td></tr>
      <tr><th>Order price</th><td>{{ number_format((float)($order->order_price ?? 0), 2) }}</td></tr>
      <tr><th>Provider</th><td>{{ $order->provider?->name ?? '—' }}</td></tr>
      <tr><th>Provider order id</th><td>{{ $order->remote_id ?? '—' }}</td></tr>
      <tr>
        <th>Response</th>
        <td>
          <pre class="mb-0" style="white-space:pre-wrap;max-height:260px;overflow:auto">{{ $order->response ?? '' }}</pre>
        </td>
      </tr>
    </table>
  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
