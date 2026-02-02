{{-- resources/views/admin/orders/modals/edit.blade.php --}}
@php
  /** @var \App\Models\Order $order */
  $replyHtml = (string)($order->reply ?? '');
  // حماية بسيطة لو في reply يحتوي </textarea>
  $replyHtmlSafe = str_ireplace('</textarea>', '&lt;/textarea&gt;', $replyHtml);

  // raw provider json/text (لو عندك عمود مختلف غير last_response عدّله)
  $raw = (string)($order->last_response ?? '');
@endphp

<div class="modal-header">
  <h5 class="modal-title">Edit Order #{{ $order->id }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.orders.imei.update', $order->id) }}">
  @csrf

  <div class="modal-body" style="max-height: 75vh; overflow:auto;">
    <div class="row g-3">
      {{-- LEFT: details + preview --}}
      <div class="col-lg-6">
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-3">
            <tbody>
              <tr><th style="width:180px">Service</th><td>{{ $order->service_name ?? '-' }}</td></tr>
              <tr><th>User</th><td>{{ $order->user_email ?? '-' }}</td></tr>
              <tr><th>Device</th><td>{{ $order->device ?? '-' }}</td></tr>
              <tr><th>Provider</th><td>{{ $order->provider ?? '-' }}</td></tr>
              <tr><th>API order ID</th><td>{{ $order->remote_id ?? '-' }}</td></tr>
              <tr><th>Order IP</th><td>{{ $order->order_ip ?? '-' }}</td></tr>
              <tr><th>Order price</th><td>{{ number_format((float)($order->price ?? 0), 2) }} USD</td></tr>
              <tr><th>API processing price</th><td>{{ number_format((float)($order->cost ?? 0), 2) }} USD</td></tr>
              <tr><th>Profit</th><td>{{ number_format((float)($order->profit ?? 0), 2) }} USD</td></tr>
            </tbody>
          </table>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              @php $st = (string)($order->status ?? ''); @endphp
              <option value="waiting"   @selected($st==='waiting')>Waiting</option>
              <option value="inprogress" @selected($st==='inprogress')>Inprogress</option>
              <option value="success"   @selected($st==='success')>Success</option>
              <option value="rejected"  @selected($st==='rejected')>Rejected</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Comments</label>
            <input name="comments" class="form-control" value="{{ $order->comments ?? '' }}">
          </div>
        </div>

        <div class="mb-2 fw-semibold">Result (Preview)</div>

        {{-- ✅ هذا القسم موجود عندك ويظهر صح (صورة + جدول) — اتركه كما هو عندك إن كان مختلف.
             هنا وضعته بشكل عام باستخدام reply (لأنك قلت تريد نفس شكل reply)
             إذا عندك متغير preview جاهز (مثل $resultPreviewHtml) استبدل هذا القسم به.
        --}}
        <div class="border rounded p-2 bg-white" style="min-height:180px">
          {!! $order->result_preview_html ?? '' !!}
        </div>
      </div>

      {{-- RIGHT: editable provider reply + raw json --}}
      <div class="col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-semibold">Reply</div>
        </div>

        <details open class="mb-3">
          <summary class="fw-semibold">Provider reply (editable)</summary>
          <div class="mt-2">
            <div class="small text-muted mb-2">هذا الحقل يدعم تنسيق + إدراج صور. بعد التعديل اضغط Save.</div>

            {{-- ✅ مهم: class js-summernote + data-summernote=1 --}}
            <textarea
              name="reply"
              class="form-control js-summernote"
              data-summernote="1"
              data-summernote-height="420"
            >{!! $replyHtmlSafe !!}</textarea>
          </div>
        </details>

        <details>
          <summary class="fw-semibold">Raw response JSON (advanced)</summary>
          <div class="mt-2">
            <textarea class="form-control" rows="10" readonly>{{ $raw }}</textarea>
          </div>
        </details>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>

{{-- ✅ ضمان تفعيل المحرر داخل المودال حتى لو admin.js ما التقطه --}}
<script>
(function () {
  const root = document.currentScript.closest('.modal-content');
  if (!root) return;

  // انتظر شوي للـ DOM داخل المودال
  setTimeout(() => {
    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.summernote !== 'function') {
      console.warn('Summernote not ready on this page.');
      return;
    }

    const $ = window.jQuery;
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

    $(root).find('textarea.js-summernote').each(function () {
      const $t = $(this);

      // امنع double init
      try { if ($t.next('.note-editor').length) $t.summernote('destroy'); } catch(e){}

      const h = Number($t.attr('data-summernote-height') || 420);

      $t.summernote({
        height: h,
        toolbar: [
          ['style', ['style']],
          ['font', ['fontname', 'fontsize', 'bold', 'italic', 'underline', 'strikethrough', 'clear']],
          ['color', ['color']],
          ['para', ['ul', 'ol', 'paragraph']],
          ['table', ['table']],
          ['insert', ['link', 'picture']],
          ['view', ['codeview']]
        ],
        fontNames: ['Arial', 'Lato', 'Tahoma', 'Times New Roman', 'Courier New', 'Verdana'],
        fontSizes: ['8','9','10','11','12','14','16','18','20','24','28','32'],
        callbacks: {
          onImageUpload: async function (files) {
            for (const f of files) {
              try {
                const fd = new FormData();
                fd.append('image', f);

                const res = await fetch('/admin/uploads/summernote-image', {
                  method: 'POST',
                  headers: {
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                  },
                  body: fd
                });

                if (!res.ok) throw new Error(await res.text());
                const j = await res.json();
                if (!j.url) throw new Error('Invalid upload response');

                $t.summernote('insertImage', j.url);
              } catch (err) {
                console.error(err);
                window.showToast?.('danger', 'Image upload failed', { title: 'Error', delay: 5000 });
              }
            }
          }
        }
      });
    });
  }, 50);
})();
</script>
