<div class="modal-header">
  <h5 class="modal-title">Edit Order #{{ $order->id ?? '' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.orders.imei.update', $order->id) }}">
  @csrf

  <div class="modal-body" style="max-height:70vh; overflow:auto;">
    <div class="container-fluid">
      <div class="row g-3">

        {{-- LEFT --}}
        <div class="col-lg-6">
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-3">
              <tbody>
                <tr>
                  <th style="width:180px;">Service</th>
                  <td>{{ $order->service_name ?? $order->service ?? '—' }}</td>
                </tr>
                <tr>
                  <th>User</th>
                  <td>{{ $order->user_email ?? $order->user ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Device</th>
                  <td>{{ $order->device ?? $order->imei ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Provider</th>
                  <td>{{ $order->provider_name ?? $order->provider ?? '—' }}</td>
                </tr>
                <tr>
                  <th>API order ID</th>
                  <td>{{ $order->remote_id ?? $order->api_order_id ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Order IP</th>
                  <td>{{ $order->order_ip ?? '—' }}</td>
                </tr>
                <tr>
                  <th>Order price</th>
                  <td>{{ isset($order->price) ? number_format((float)$order->price, 2) . ' USD' : ($order->order_price ?? '—') }}</td>
                </tr>
                <tr>
                  <th>API processing price</th>
                  <td>{{ isset($order->cost) ? number_format((float)$order->cost, 2) . ' USD' : ($order->api_processing_price ?? '—') }}</td>
                </tr>
                <tr>
                  <th>Profit</th>
                  <td>{{ isset($order->profit) ? number_format((float)$order->profit, 2) . ' USD' : '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                @php
                  $cur = $order->status ?? '';
                @endphp
                <option value="waiting"  {{ $cur === 'waiting'  ? 'selected' : '' }}>Waiting</option>
                <option value="success"  {{ $cur === 'success'  ? 'selected' : '' }}>Success</option>
                <option value="rejected" {{ $cur === 'rejected' ? 'selected' : '' }}>Rejected</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Comments</label>
              <input type="text" name="comments" class="form-control" value="{{ $order->comments ?? '' }}">
            </div>
          </div>

          <div class="mb-2 d-flex justify-content-between align-items-center">
            <label class="form-label mb-0">Result (Preview)</label>
            <small class="text-muted">Auto-rendered</small>
          </div>

          {{-- النتيجة المعروضة (غير قابلة للتعديل هنا) --}}
          <div class="border rounded p-2 bg-white">
            {!! $order->reply ?? $order->result_html ?? '—' !!}
          </div>
        </div>

        {{-- RIGHT --}}
        <div class="col-lg-6">
          <div class="mb-2">
            <label class="form-label">Reply</label>

            <details open class="border rounded p-2 bg-white">
              <summary class="mb-2">Provider reply (editable)</summary>

              <div class="mb-2 text-muted" style="font-size:12px;">
                سيتم حفظ هذا الحقل عند الضغط على Save. يدعم تنسيق + إدراج صور.
              </div>

              {{-- ✅ هذا هو الجزء المطلوب: نفس شكل Result Preview ولكن قابل للتعديل مع Toolbar --}}
              <textarea
                name="reply"
                class="form-control"
                data-summernote="1"
                data-summernote-height="320"
              >{!! $order->reply ?? '' !!}</textarea>
            </details>

            <details class="border rounded p-2 mt-2 bg-white">
              <summary class="mb-2">Raw response JSON (advanced)</summary>
              <textarea class="form-control" rows="10" readonly>@php
echo is_string($order->raw_response ?? null) ? ($order->raw_response ?? '') : json_encode($order->raw_response ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
@endphp</textarea>
            </details>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
