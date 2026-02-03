{{-- resources/views/admin/orders/modals/edit.blade.php --}}

@php
    // order يأتي من ImeiOrdersController@modalEdit
    $order = $order ?? null;

    $serviceName  = $order->service_name  ?? '—';
    $providerName = $order->provider_name ?? ($order->provider ?? '—');

    $apiOrderId   = $order->remote_id ?? '—';
    $orderIp      = $order->order_ip ?? '—';

    // Customer price
    $orderPrice   = isset($order->order_price) ? number_format((float)$order->order_price, 2) . ' USD' : '—';

    // API processing price (from imei_services.cost join)
    $apiCost      = isset($order->api_processing_price) ? number_format((float)$order->api_processing_price, 2) . ' USD' : '—';

    // Profit (إن كان عندك من جدول الطلبات)
    $profit       = isset($order->profit) ? number_format((float)$order->profit, 2) . ' USD' : '—';

    // Provider reply editable (we store in imei_orders.reply_text)
    // مهم: لا تستخدم {{ }} هنا لأنه سيُحوّل HTML لكود ظاهر
    $providerReplyHtml = old('provider_reply', $order->reply_text ?? '');

    // decode raw response json from imei_orders.response (longtext)
    $raw = $order->response ?? '';
    $decoded = null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
    }

    // ---- Build Result Preview HTML (similar to view.blade.php behavior) ----
    $imgUrl = null;
    $items = [];

    // بعض مزودين يرجعون:
    // { result_items: [{label,value,type}], image: "http..." }
    // أو { result: "HTML..." }
    // سنحاول استخراج الصورة + عناصر
    if (is_array($decoded)) {
        // Image keys
        foreach (['image','image_url','img','photo','device_image'] as $k) {
            if (!empty($decoded[$k]) && is_string($decoded[$k])) { $imgUrl = $decoded[$k]; break; }
        }

        // Items array
        if (!empty($decoded['result_items']) && is_array($decoded['result_items'])) {
            $items = $decoded['result_items'];
        } elseif (!empty($decoded['items']) && is_array($decoded['items'])) {
            $items = $decoded['items'];
        }
    }

    // fallback: if providerReplyHtml contains an <img ...> take it as preview
    if (!$imgUrl && is_string($providerReplyHtml)) {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $providerReplyHtml, $m)) {
            $imgUrl = $m[1] ?? null;
        }
    }

    // badge helper
    $badge = function($val) {
        $v = trim((string)$val);
        $u = strtoupper($v);

        $good = ['CLEAN','UNLOCK','UNLOCKED','ACTIVATED','YES','OK'];
        $bad  = ['LOST MODE','LOST','BLACKLIST','BLOCKED','EXPIRED','NO','ON'];

        if (in_array($u, $good, true)) return '<span class="badge bg-success">'.$v.'</span>';
        if (in_array($u, $bad, true))  return '<span class="badge bg-danger">'.$v.'</span>';
        if ($u === 'OFF')              return '<span class="badge bg-success">'.$v.'</span>';

        return e($v);
    };

@endphp

<div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
        <form class="js-ajax-form" method="POST" action="{{ route('admin.orders.imei.update', $order->id ?? 0) }}">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">Edit Order #{{ $order->id ?? '' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <div class="row g-3">

                    {{-- LEFT: info table --}}
                    <div class="col-lg-6">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0">
                                <tbody>
                                <tr><th style="width:180px;">Service</th><td>{{ $serviceName }}</td></tr>
                                <tr><th>User</th><td>{{ $order->user_email ?? $order->email ?? '—' }}</td></tr>
                                <tr><th>Device</th><td>{{ $order->device ?? $order->imei ?? '—' }}</td></tr>
                                <tr><th>Provider</th><td>{{ $providerName }}</td></tr>

                                <tr><th>API order ID</th><td>{{ $apiOrderId }}</td></tr>
                                <tr><th>Order IP</th><td>{{ $orderIp }}</td></tr>

                                <tr><th>Order price</th><td>{{ $orderPrice }}</td></tr>
                                <tr><th>API processing price</th><td>{{ $apiCost }}</td></tr>
                                <tr><th>Profit</th><td>{{ $profit }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-2 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    @php $st = old('status', $order->status ?? 'Waiting'); @endphp
                                    <option value="Waiting"  {{ $st=='Waiting'?'selected':'' }}>Waiting</option>
                                    <option value="Success"  {{ $st=='Success'?'selected':'' }}>Success</option>
                                    <option value="Rejected" {{ $st=='Rejected'?'selected':'' }}>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Comments</label>
                                <input name="comments" class="form-control" value="{{ old('comments', $order->comments ?? '') }}">
                            </div>
                        </div>

                        {{-- Result Preview (Auto-rendered) --}}
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Result (Preview)</label>
                                <small class="text-muted">Auto-rendered</small>
                            </div>

                            <div class="border rounded p-2 bg-light">
                                @if($imgUrl)
                                    <div class="text-center mb-3">
                                        <img src="{{ $imgUrl }}"
                                             alt="device"
                                             style="max-width:260px;height:auto;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,.12);">
                                    </div>
                                @endif

                                @if(!empty($items))
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm mb-0">
                                            <tbody>
                                            @foreach($items as $it)
                                                @php
                                                    $label = $it['label'] ?? '';
                                                    $val   = $it['value'] ?? '';
                                                @endphp
                                                <tr>
                                                    <th style="width:220px;">{{ $label }}</th>
                                                    <td>{!! $badge($val) !!}</td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @elseif(is_array($decoded) && !empty($decoded['result']) && is_string($decoded['result']))
                                    {{-- fallback to provider result html --}}
                                    <div class="small">
                                        {!! $decoded['result'] !!}
                                    </div>
                                @else
                                    <div class="text-muted">—</div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT: Provider reply --}}
                    <div class="col-lg-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Provider reply (editable)</label>
                            <small class="text-muted">بعد التعديل اضغط Save</small>
                        </div>

                        {{-- الخطوة الأولى المطلوبة: يظهر "كنص/شكل" مثل Result Preview --}}
                        <div class="border rounded p-2 bg-white" style="min-height:210px; max-height:260px; overflow:auto;">
                            @if(trim($providerReplyHtml) !== '')
                                {!! $providerReplyHtml !!}
                            @else
                                <div class="text-muted">—</div>
                            @endif
                        </div>

                        {{-- نفس المحتوى لكن قابل للتعديل عبر Summernote (لو اشتغل) --}}
                        <textarea
                            name="provider_reply"
                            class="form-control mt-2"
                            data-summernote="1"
                            data-summernote-height="260"
                        >{!! $providerReplyHtml !!}</textarea>

                        <details class="mt-2">
                            <summary class="text-muted">Raw response JSON (advanced)</summary>
                            <textarea class="form-control mt-2" rows="8" readonly>@php
                                echo is_string($raw) ? $raw : '';
                            @endphp</textarea>
                        </details>
                    </div>

                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-success" type="submit">Save</button>
            </div>

        </form>
    </div>
</div>
