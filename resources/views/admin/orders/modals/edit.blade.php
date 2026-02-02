{{-- resources/views/admin/orders/modals/edit.blade.php --}}

@php
    // اجعل الصفحة “تتحمل” أي شكل بيانات حتى لا تكسر المودال وتسبب 500
    $orderId     = $order->id ?? null;
    $serviceName = $order->service_name ?? ($order->service->name ?? '—');
    $userEmail   = $order->user_email ?? ($order->user->email ?? '—');
    $device      = $order->device ?? $order->imei ?? '—';
    $provider    = $order->provider ?? ($order->api_provider ?? '—');

    $apiOrderId  = $order->remote_id ?? $order->api_order_id ?? '—';
    $orderIp     = $order->order_ip ?? $order->ip ?? '—';

    $orderPrice  = $order->price ?? $order->order_price ?? null;
    $apiCost     = $order->cost ?? $order->api_processing_price ?? null;
    $profit      = $order->profit ?? null;

    $statusVal   = $order->status ?? 'waiting';

    // reply html (المفروض هذا اللي تبي تعدله داخل الـ editor)
    $replyHtml   = $order->reply ?? $order->reply_html ?? '';

    // result preview items (قد تكون array أو json)
    $resultItems = $order->result_items ?? $order->result ?? $order->result_json ?? null;
    if (is_string($resultItems)) {
        $tmp = json_decode($resultItems, true);
        $resultItems = is_array($tmp) ? $tmp : [];
    }
    if (!is_array($resultItems)) $resultItems = [];

    // Image URL داخل النتيجة (ممكن تكون محفوظة ضمن resultItems أو حقل مستقل)
    $resultImage = $order->result_image ?? $order->image_url ?? null;
    if (!$resultImage) {
        foreach ($resultItems as $it) {
            if (!is_array($it)) continue;
            $lbl = strtolower((string)($it['label'] ?? ''));
            if (in_array($lbl, ['image','photo','picture','device image','device photo','result image'], true)) {
                $resultImage = $it['value'] ?? null;
                break;
            }
        }
    }

    // خيارات الحالة (عدّلها إذا عندك statuses جاهزة من الكنترولر)
    $statuses = $statuses ?? [
        'success'  => 'Success',
        'waiting'  => 'Waiting',
        'rejected' => 'Rejected',
        'failed'   => 'Failed',
    ];
@endphp

<div class="modal-header">
    <h5 class="modal-title">Edit Order #{{ $orderId ?? '' }}</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ $orderId ? route('admin.orders.imei.update', $orderId) : '#' }}">
    @csrf

    <div class="modal-body" style="max-height:70vh; overflow:auto;">

        {{-- TOP: order info + reply editor --}}
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <th style="width:180px;">Service</th>
                                <td>{{ $serviceName }}</td>
                            </tr>
                            <tr>
                                <th>User</th>
                                <td>{{ $userEmail }}</td>
                            </tr>
                            <tr>
                                <th>Device</th>
                                <td>{{ $device }}</td>
                            </tr>
                            <tr>
                                <th>Provider</th>
                                <td>{{ $provider }}</td>
                            </tr>
                            <tr>
                                <th>API order ID</th>
                                <td>{{ $apiOrderId }}</td>
                            </tr>
                            <tr>
                                <th>Order IP</th>
                                <td>{{ $orderIp }}</td>
                            </tr>
                            <tr>
                                <th>Order price</th>
                                <td>
                                    {{ is_null($orderPrice) ? '—' : number_format((float)$orderPrice, 2) . ' USD' }}
                                </td>
                            </tr>
                            <tr>
                                <th>API processing price</th>
                                <td>
                                    {{ is_null($apiCost) ? '—' : number_format((float)$apiCost, 2) . ' USD' }}
                                </td>
                            </tr>
                            <tr>
                                <th>Profit</th>
                                <td>
                                    {{ is_null($profit) ? '—' : number_format((float)$profit, 2) . ' USD' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row g-2 mt-3">
                    <div class="col-md-6">
                        <label class="form-label mb-1">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" {{ (string)$statusVal === (string)$k ? 'selected' : '' }}>
                                    {{ $v }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-1">Comments</label>
                        <input type="text" name="comments" class="form-control form-control-sm"
                               value="{{ old('comments', $order->comments ?? '') }}">
                    </div>
                </div>

            </div>

            <div class="col-lg-7">
                <div class="border rounded p-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-semibold">Provider reply (editable)</div>
                        <small class="text-muted">Save بعد التعديل</small>
                    </div>

                    {{-- IMPORTANT:
                         هذا textarea لازم يتفعل عليه Summernote من admin.js (data-summernote="1")
                         إذا ما تفعّل، سيظهر HTML كنص.
                    --}}
                    <textarea
                        name="reply"
                        class="form-control form-control-sm mt-2"
                        data-summernote="1"
                        data-summernote-height="260"
                    >{{ old('reply', $replyHtml) }}</textarea>

                    {{-- Preview نفس شكل Result Preview (يعرض HTML فعلياً) --}}
                    <div class="mt-3">
                        <div class="fw-semibold mb-1">Reply Preview</div>
                        <div class="border rounded bg-light p-2" style="min-height:120px;">
                            {!! $replyHtml ?: '<span class="text-muted">—</span>' !!}
                        </div>
                    </div>

                    {{-- Raw JSON (advanced) --}}
                    <details class="mt-2">
                        <summary class="text-muted">Raw response JSON (advanced)</summary>
                        <pre class="small mb-0" style="white-space:pre-wrap;">{{ $order->last_response ?? $order->raw_response ?? '—' }}</pre>
                    </details>
                </div>
            </div>
        </div>

        {{-- RESULT PREVIEW --}}
        <div class="mt-4">
            <div class="d-flex align-items-center justify-content-between">
                <div class="fw-semibold">Result (Preview)</div>
                <small class="text-muted">Auto-rendered</small>
            </div>

            <div class="border rounded p-3 mt-2">

                @if($resultImage)
                    <div class="text-center mb-3">
                        <img src="{{ $resultImage }}"
                             alt="Result image"
                             style="max-width:260px; width:100%; height:auto; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,.12);">
                    </div>
                @endif

                @if(count($resultItems))
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <tbody>
                            @foreach($resultItems as $it)
                                @php
                                    $label = is_array($it) ? ($it['label'] ?? '') : '';
                                    $value = is_array($it) ? ($it['value'] ?? '') : '';
                                    $badge = is_array($it) ? ($it['badge'] ?? null) : null; // اختياري
                                @endphp
                                <tr>
                                    <th style="width:220px;">{{ $label }}</th>
                                    <td>
                                        @if(is_array($badge))
                                            <span class="badge {{ $badge['class'] ?? 'bg-secondary' }}">
                                                {{ $badge['text'] ?? $value }}
                                            </span>
                                        @else
                                            {!! e($value) !!}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-muted">—</div>
                @endif

            </div>
        </div>

    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-success btn-sm">Save</button>
    </div>
</form>
