<div class="modal-header">
    <h5 class="modal-title">Edit Order #{{ $order->id ?? '' }}</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<style>
  /* يخلي المودال قابل للتمرير حتى تشوف Result Preview كامل */
  #ajaxModal .modal-dialog { max-width: 1200px; }
  #ajaxModal .modal-body{
    max-height: calc(100vh - 180px);
    overflow-y: auto;
  }
  .reply-preview-box{
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
  }
  .result-preview-card{
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
  }
  .result-preview-image{
    max-height: 320px; /* تكبير الصورة */
    width: auto;
    max-width: 100%;
    border-radius: 12px;
    box-shadow: 0 8px 18px rgba(0,0,0,.12);
  }
  /* تحسين جدول النتائج */
  .mini-table td, .mini-table th { padding: 6px 10px; }
  .mini-table th { width: 260px; background: #f8f9fa; }
</style>

<form class="js-ajax-form" method="POST" action="{{ route('admin.orders.imei.update', $order->id) }}">
    @csrf

    <div class="modal-body">
        <div class="row g-3">

            {{-- LEFT: Order details --}}
            <div class="col-md-6">
                <table class="table table-bordered align-middle mb-2">
                    <tbody>
                        <tr><th style="width:180px">Service</th><td>{{ $order->service ?? '—' }}</td></tr>
                        <tr><th>User</th><td>{{ $order->user ?? $order->email ?? '—' }}</td></tr>
                        <tr><th>Device</th><td>{{ $order->device ?? $order->imei ?? '—' }}</td></tr>
                        <tr><th>Provider</th><td>{{ $order->provider ?? '—' }}</td></tr>
                        <tr><th>API order ID</th><td>{{ $order->remote_id ?? $order->api_order_id ?? '—' }}</td></tr>
                        <tr><th>Order IP</th><td>{{ $order->order_ip ?? '—' }}</td></tr>
                        <tr><th>Order price</th><td>{{ $order->price ?? $order->order_price ?? '—' }}</td></tr>
                        <tr><th>API processing price</th><td>{{ $order->cost ?? $order->api_processing_price ?? '—' }}</td></tr>
                        <tr><th>Profit</th><td>{{ $order->profit ?? '—' }}</td></tr>
                    </tbody>
                </table>

                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select">
                            @php $st = $order->status ?? 'waiting'; @endphp
                            <option value="success"  {{ $st=='success' ? 'selected' : '' }}>Success</option>
                            <option value="rejected" {{ $st=='rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="waiting"  {{ $st=='waiting' ? 'selected' : '' }}>Waiting</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Comments</label>
                        <input type="text" name="comments" class="form-control" value="{{ $order->comments ?? '' }}">
                    </div>
                </div>

                {{-- Result Preview (غير قابل للتعديل) --}}
                <div class="result-preview-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Result (Preview)</strong>
                        <small class="text-muted">Auto-rendered</small>
                    </div>

                    @if(!empty($imageUrl))
                        <div class="text-center mb-3">
                            <img class="result-preview-image" src="{{ $imageUrl }}" alt="Result Image">
                        </div>
                    @endif

                    <table class="table table-bordered mini-table mb-0">
                        <tbody>
                        @if(!empty($resultItems))
                            @foreach($resultItems as $it)
                                @if(is_array($it))
                                    @php
                                        $label = $it['label'] ?? $it['key'] ?? '';
                                        $value = $it['value'] ?? $it['val'] ?? '';
                                        $type  = $it['type'] ?? '';
                                        if ($type === 'image') continue;
                                    @endphp
                                    @if($label !== '')
                                        <tr>
                                            <th>{{ $label }}</th>
                                            <td>{!! e($value) !!}</td>
                                        </tr>
                                    @endif
                                @endif
                            @endforeach
                        @else
                            <tr><td class="text-muted">—</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- RIGHT: Provider reply editable --}}
            <div class="col-md-6">
                <div class="reply-preview-box">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Provider reply (editable)</strong>
                        <small class="text-muted">بعد التعديل اضغط Save</small>
                    </div>

                    {{-- ✅ هذا هو المهم: textarea تتحول لـ Summernote --}}
                    <textarea
                        id="replyEditor"
                        name="reply"
                        data-summernote="1"
                        data-summernote-height="420"
                        class="form-control"
                    >{!! $replyHtml ?? '' !!}</textarea>

                    <details class="mt-3">
                        <summary class="text-muted">Raw response JSON (advanced)</summary>
                        <pre class="mt-2 mb-0" style="white-space:pre-wrap;max-height:260px;overflow:auto;border:1px solid #eee;padding:10px;border-radius:8px;">
{{ $order->last_response ?? $order->raw_response ?? $order->provider_reply ?? '—' }}
                        </pre>
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

<script>
(function () {
    // ✅ ضمان تفعيل Summernote داخل المودال حتى لو init العام تعثر
    try {
        if (!window.jQuery || !jQuery.fn || typeof jQuery.fn.summernote !== 'function') {
            console.warn('Summernote is not available on this page.');
            return;
        }

        var $t = jQuery('#ajaxModal .modal-content').find('#replyEditor');

        if (!$t.length) return;

        // destroy لو كان متفعل قبل
        try { if ($t.next('.note-editor').length) $t.summernote('destroy'); } catch(e){}

        var token = document.querySelector('meta[name="csrf-token"]')?.content || '';

        $t.summernote({
            height: 420,
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
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32'],
            callbacks: {
                onImageUpload: function(files) {
                    for (var i=0; i<files.length; i++) {
                        (function(file){
                            var fd = new FormData();
                            fd.append('image', file);

                            fetch('/admin/uploads/summernote-image', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': token,
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json'
                                },
                                body: fd
                            }).then(function(res){
                                if(!res.ok) return res.text().then(function(t){ throw new Error(t); });
                                return res.json();
                            }).then(function(j){
                                if (j && j.url) $t.summernote('insertImage', j.url);
                            }).catch(function(err){
                                console.error(err);
                                if (window.showToast) window.showToast('danger', 'Image upload failed', { title: 'Error', delay: 5000 });
                            });
                        })(files[i]);
                    }
                }
            }
        });

    } catch (e) {
        console.error(e);
    }
})();
</script>
