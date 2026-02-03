{{-- resources/views/admin/orders/modals/edit.blade.php --}}
@php
  /** @var \Illuminate\Database\Eloquent\Model|mixed $order */
  $order = $order ?? null;

  // نص خام محفوظ (HTML) - للـ textarea (editable)
  $providerReplyHtml = $providerReplyHtml ?? ($order->provider_reply ?? $order->reply ?? '');

  // HTML جاهز للعرض (Preview) - يُفضل يكون نفس الذي تبنيه في Result Preview
  // إذا عندك بالفعل متغير جاهز من الكنترولر مثل $replyPreviewHtml استعمله
  $replyPreviewHtml = $replyPreviewHtml ?? $providerReplyHtml;

  // Result Preview HTML (الموجود عندك بالفعل في view)
  $resultPreviewHtml = $resultPreviewHtml ?? ($order->result_html ?? $order->result ?? '');

  $routePrefix = $routePrefix ?? 'admin.orders.imei';
@endphp

<div class="modal-dialog modal-xl modal-dialog-scrollable">
  <div class="modal-content">

    <div class="modal-header">
      <h5 class="modal-title">
        Edit Order #{{ $order->id ?? '' }}
      </h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <form class="js-ajax-form" method="POST" action="{{ route($routePrefix.'.update', $order->id ?? 0) }}">
      @csrf

      <div class="modal-body">

        <div class="row g-3">

          {{-- LEFT: order info --}}
          <div class="col-lg-5">
            <table class="table table-sm table-bordered align-middle mb-0">
              <tbody>
                <tr><th style="width:160px;">Service</th><td>{{ $order->service_name ?? $order->service ?? '—' }}</td></tr>
                <tr><th>User</th><td>{{ $order->user_email ?? $order->user ?? '—' }}</td></tr>
                <tr><th>Device</th><td>{{ $order->device ?? $order->imei ?? '—' }}</td></tr>
                <tr><th>Provider</th><td>{{ $order->provider_name ?? $order->provider ?? '—' }}</td></tr>
                <tr><th>API order ID</th><td>{{ $order->remote_id ?? $order->api_order_id ?? '—' }}</td></tr>
                <tr><th>Order IP</th><td>{{ $order->order_ip ?? '—' }}</td></tr>
                <tr><th>Order price</th><td>{{ $order->price ?? '—' }}</td></tr>
                <tr><th>API processing price</th><td>{{ $order->cost ?? $order->api_processing_price ?? '—' }}</td></tr>
                <tr><th>Profit</th><td>{{ $order->profit ?? '—' }}</td></tr>
              </tbody>
            </table>

            <div class="row g-2 mt-3">
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  @php($cur = $order->status ?? 'waiting')
                  <option value="waiting"  @selected($cur==='waiting')>Waiting</option>
                  <option value="success"  @selected($cur==='success')>Success</option>
                  <option value="rejected" @selected($cur==='rejected' || $cur==='reject')>Rejected</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Comments</label>
                <input type="text" name="comments" class="form-control" value="{{ $order->comments ?? '' }}">
              </div>
            </div>

            {{-- Result Preview --}}
            <div class="mt-3">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-1">Result (Preview)</label>
                <small class="text-muted">Auto-rendered</small>
              </div>

              <div class="border rounded p-2 bg-white" style="min-height: 140px;">
                {!! $resultPreviewHtml ?: '<span class="text-muted">—</span>' !!}
              </div>
            </div>
          </div>

          {{-- RIGHT: Provider reply --}}
          <div class="col-lg-7">

            <div class="border rounded">
              <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
                <div class="fw-semibold">Provider reply (editable)</div>
                <small class="text-muted">بعد التعديل اضغط Save</small>
              </div>

              <div class="p-2">
                <div class="text-muted small mb-2">
                  سيتم حفظ HTML. إذا فعلنا Summernote سيظهر Toolbar وتحرير بصري.
                </div>

                {{-- ✅ هذا هو الذي سيصبح Summernote --}}
                <textarea
                  name="provider_reply"
                  class="form-control"
                  rows="10"
                  data-summernote="1"
                  data-summernote-height="260"
                >{!! old('provider_reply', $providerReplyHtml) !!}</textarea>
              </div>

              <div class="p-2 border-top">
                <div class="fw-semibold mb-2">Reply Preview</div>

                {{-- ✅ هذا المطلوب: عرض مثل Result Preview (صورة + معلومات) --}}
                <div class="border rounded p-2 bg-white" style="min-height: 200px;">
                  {!! $replyPreviewHtml ?: '<span class="text-muted">—</span>' !!}
                </div>
              </div>
            </div>

            {{-- Raw JSON (اختياري) --}}
            <div class="mt-2">
              <details>
                <summary class="text-muted">Raw response JSON (advanced)</summary>
                <pre class="small bg-light p-2 rounded mt-2 mb-0" style="max-height:220px; overflow:auto;">{{ $order->last_response ?? '' }}</pre>
              </details>
            </div>

          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-success">Save</button>
      </div>

    </form>

  </div>
</div>
