<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">{{ $reply->id }} | View</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <table class="table table-bordered mb-0">
    <tr>
      <th style="width:170px">ID</th>
      <td>{{ $reply->id }}</td>
    </tr>
    <tr>
      <th>Source</th>
      <td>{{ $reply->source?->name ?? 'None' }}</td>
    </tr>
    <tr>
      <th>Device</th>
      <td>{{ $reply->device_identifier ?: 'None' }}</td>
    </tr>
    <tr>
      <th>Reply</th>
      <td style="white-space:pre-wrap">{{ $reply->reply }}</td>
    </tr>
    <tr>
      <th>Usage</th>
      <td>
        @if($reply->used_by_product_order_id)
          <a href="{{ route('admin.orders.product.index') }}" class="btn btn-primary btn-sm">
            Product order: {{ $reply->used_by_product_order_id }}
          </a>
        @else
          None
        @endif
      </td>
    </tr>
    <tr>
      <th>Expiration</th>
      <td>{{ $reply->expires_at ? $reply->expires_at->format('d/m/Y H:i:s') : 'None' }}</td>
    </tr>
  </table>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" disabled>Activity logs</button>
  <button type="button" class="btn btn-warning js-open-modal" data-url="{{ route('admin.replies.modal.edit', $reply) }}">Edit</button>
  <button type="button" class="btn btn-danger js-open-modal" data-url="{{ route('admin.replies.modal.delete', $reply) }}">Delete</button>
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
</div>
