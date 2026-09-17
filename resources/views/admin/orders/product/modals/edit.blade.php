@php
  $productName = data_get($order->request, 'product_name') ?: $order->product?->name ?: 'Historical product';
  $sourceType = data_get($order->request, 'source_type', $order->product?->source_type ?: 'manual');
  $decoded = is_string($order->response) ? json_decode($order->response, true) : null;
  $responseData = is_array($decoded) ? $decoded : [];
  $replyHtml = (string) ($responseData['provider_reply_html'] ?? '');
  if ($replyHtml === '') $replyHtml = e((string) ($responseData['result_text'] ?? ($order->response ?: '')));
  $providerError = data_get($order->request, 'internal_dispatch_note') ?: data_get($order->request, 'internal_provider_note');
  $locked = $order->status === 'success';
@endphp
<div class="modal-header border-0 text-white" style="background:#f39c12">
  <div><strong>Product Order #{{ $order->id }}</strong><span class="opacity-75"> | Edit</span></div>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<form class="js-ajax-form" method="POST" action="{{ route('admin.orders.product.update', $order) }}">
  @csrf @method('PUT')
  <div class="modal-body" style="max-height:calc(100vh - 210px);overflow:auto">
    <div class="row g-3">
      <div class="col-lg-5"><div class="card h-100"><div class="card-header fw-bold">Order Info</div><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered mb-0"><tbody>
        <tr><th style="width:150px">Product</th><td>{{ $productName }}</td></tr>
        <tr><th>User</th><td>{{ $order->user?->email ?: $order->email }}</td></tr>
        <tr><th>Device / target</th><td class="text-break">{{ $order->device ?: '—' }}</td></tr>
        <tr><th>Source</th><td>{{ ucfirst(str_replace('_',' ',(string)$sourceType)) }}</td></tr>
        <tr><th>Order date</th><td>{{ optional($order->created_at)->format('d/m/Y H:i:s') ?: '—' }}</td></tr>
        <tr><th>Order price</th><td>{{ number_format((float)$order->order_price,2) }} credits</td></tr>
        <tr><th>Service order</th><td>{{ $order->service_order_type ? strtoupper($order->service_order_type).' #'.$order->service_order_id : '—' }}</td></tr>
        <tr><th>Reply date</th><td>{{ optional($order->replied_at)->format('d/m/Y H:i:s') ?: '—' }}</td></tr>
      </tbody></table></div></div></div></div>
      <div class="col-lg-7"><div class="card h-100"><div class="card-header fw-bold">Reply Preview</div><div class="card-body">
        <div class="border rounded p-3 mb-3 bg-white" style="min-height:100px">{!! $replyHtml ?: '<span class="text-muted">Awaiting result</span>' !!}</div>
        @if($providerError)<div class="alert alert-warning"><strong>Provider error:</strong> {{ $providerError }}</div>@endif
        <label class="form-label fw-semibold">Reply HTML</label>
        <textarea name="provider_reply_html" class="form-control" rows="10" data-editor="summernote" data-summernote-height="260">{!! $replyHtml !!}</textarea>
        @if($sourceType === 'manual' && !$locked)<div class="form-text">A delivery result is required when a manual product is marked Success.</div>@endif
        <label class="form-label fw-semibold mt-3">Status</label>
        <select name="status" class="form-select" required @disabled($locked)>
          @foreach(['waiting'=>'Waiting','inprogress'=>'In progress','success'=>'Success','rejected'=>'Rejected','cancelled'=>'Cancelled'] as $value=>$label)
            <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
          @endforeach
        </select>
        @if($locked)<input type="hidden" name="status" value="success"><div class="form-text">The status and financial history stay locked, but the reply can be corrected and saved again.</div>@endif
        <label class="form-label fw-semibold mt-3">Comments</label>
        <textarea name="comments" class="form-control" rows="3" maxlength="5000">{{ $order->comments }}</textarea>
      </div></div></div>
    </div>
  </div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-success">Save</button></div>
</form>
