{{-- resources/views/admin/orders/modals/edit.blade.php --}}
@php
  /** @var \Illuminate\Database\Eloquent\Model|mixed $row */
  $row = $row ?? null;
  $routePrefix = $routePrefix ?? 'admin.orders.imei';

  // Decode response
  $resp = $row?->response ?? null;
  if (is_string($resp)) {
    $decoded = json_decode($resp, true);
    if (is_array($decoded)) $resp = $decoded;
  }
  if (!is_array($resp)) $resp = [];

  // Result fields (same pattern used in view modal)
  $items = $resp['result_items'] ?? [];
  $img   = $resp['result_image'] ?? null;

  // Provider reply html stored in response
  $providerReplyHtml = $resp['provider_reply_html'] ?? '';

  $isSafeImg = function ($url) {
    if (!is_string($url)) return false;
    $u = trim($url);
    return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };

  $clean = function ($v) {
    $v = (string)$v;
    $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($v);
  };

  $renderValue = function ($label, $value) use ($clean) {
    $labelL = mb_strtolower($clean($label));
    $val    = $clean($value);
    $valL   = mb_strtolower($val);

    if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
    if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';

    if (str_contains($labelL, 'find my') || str_contains($labelL, 'fmi')) {
      if ($valL === 'on')  return '<span class="badge bg-danger">ON</span>';
      if ($valL === 'off') return '<span class="badge bg-success">OFF</span>';
    }

    if (str_contains($labelL, 'icloud')) {
      if (str_contains($valL, 'lost'))  return '<span class="badge bg-danger">'.e($val).'</span>';
      if (str_contains($valL, 'clean')) return '<span class="badge bg-success">'.e($val).'</span>';
      return '<span class="badge bg-secondary">'.e($val).'</span>';
    }

    if ($valL === 'activated') return '<span class="badge bg-success">Activated</span>';
    if ($valL === 'expired')   return '<span class="badge bg-danger">Expired</span>';
    if ($valL === 'unlocked')  return '<span class="badge bg-success">Unlocked</span>';
    if ($valL === 'clean')     return '<span class="badge bg-success">Clean</span>';
    if (str_contains($valL, 'lost')) return '<span class="badge bg-danger">'.e($val).'</span>';

    return e($val);
  };
@endphp

<div class="modal-header">
  <h5 class="modal-title">Edit Order #{{ $row->id ?? '' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="POST" action="{{ route($routePrefix.'.update', $row->id ?? 0) }}">
  @csrf

  <div class="modal-body">
    <div class="row g-3">

      {{-- LEFT --}}
      <div class="col-lg-5">
        <table class="table table-sm table-bordered align-middle mb-0">
          <tbody>
            <tr><th style="width:160px;">Service</th><td>{{ $row->service?->name ?? '—' }}</td></tr>
            <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
            <tr><th>Device</th><td>{{ $row->device ?? '—' }}</td></tr>
            <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
            <tr><th>Remote ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
            <tr><th>IP</th><td>{{ $row->ip ?? '—' }}</td></tr>
            <tr><th>Order price</th><td>{{ $row->price ?? '—' }}</td></tr>
            <tr><th>API cost</th><td>{{ $row->order_price ?? '—' }}</td></tr>
            <tr><th>Profit</th><td>{{ $row->profit ?? '—' }}</td></tr>
          </tbody>
        </table>

        <div class="row g-2 mt-3">
          <div class="col-12">
            <label class="form-label">Status</label>
            @php($cur = $row->status ?? 'waiting')
            <select name="status" class="form-select" required>
              <option value="waiting"    @selected($cur==='waiting')>Waiting</option>
              <option value="inprogress" @selected($cur==='inprogress')>In progress</option>
              <option value="success"    @selected($cur==='success')>Success</option>
              <option value="rejected"   @selected($cur==='rejected')>Rejected</option>
              <option value="cancelled"  @selected($cur==='cancelled')>Cancelled</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Comments</label>
            <input type="text" name="comments" class="form-control" value="{{ $row->comments ?? '' }}">
          </div>
        </div>

        {{-- Result Preview --}}
        <div class="mt-3">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label mb-1">Result (Preview)</label>
            <small class="text-muted">from response.result_items</small>
          </div>

          <div class="border rounded p-2 bg-white" style="min-height: 160px;">
            @if($img && $isSafeImg($img))
              <div class="mb-2 text-center">
                <img src="{{ $img }}" alt="Result image" style="max-width:240px; height:auto;" class="img-fluid rounded shadow-sm">
              </div>
            @endif

            @if(is_array($items) && count($items))
              <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered mb-0">
                  <tbody>
                    @foreach($items as $it)
                      @php
                        $label = $it['label'] ?? '';
                        $value = $it['value'] ?? '';
                      @endphp
                      @if(trim($label) === '')
                        <tr><td colspan="2">{!! $renderValue('', $value) !!}</td></tr>
                      @else
                        <tr>
                          <th style="width:200px">{{ $label }}</th>
                          <td>{!! $renderValue($label, $value) !!}</td>
                        </tr>
                      @endif
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <span class="text-muted">—</span>
            @endif
          </div>
        </div>

      </div>

      {{-- RIGHT --}}
      <div class="col-lg-7">
        <div class="border rounded">
          <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Provider Reply (editable)</div>
            <small class="text-muted">HTML محفوظ داخل response.provider_reply_html</small>
          </div>

          <div class="p-2">
            <textarea
              id="provider-reply-editor"
              name="provider_reply_html"
              class="form-control"
              rows="10"
              data-summernote="1"
              data-summernote-height="260"
            >{!! old('provider_reply_html', $providerReplyHtml) !!}</textarea>
          </div>

          <div class="p-2 border-top">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="fw-semibold">Reply Preview</div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-update-reply-preview">
                Update preview
              </button>
            </div>

            <div id="provider-reply-preview" class="border rounded p-2 bg-white" style="min-height: 200px;">
              {!! $providerReplyHtml ?: '<span class="text-muted">—</span>' !!}
            </div>
          </div>
        </div>

        {{-- Optional: edit raw response json/text --}}
        <div class="mt-3">
          <label class="form-label">Response (optional)</label>
          <textarea name="response" class="form-control" rows="5"
            placeholder="إذا تريد تعديل JSON/نص response كله (اختياري)">{{ old('response') }}</textarea>
          <div class="form-text">اتركها فارغة إذا ما تبي تغيّر response العام.</div>
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
  function getEditorHtml() {
    var el = document.getElementById('provider-reply-editor');
    if (!el) return '';

    // إذا Summernote شغّال
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote) {
      try {
        return window.jQuery(el).summernote('code') || '';
      } catch (e) {}
    }

    // fallback
    return el.value || '';
  }

  function updatePreview() {
    var html = getEditorHtml().trim();
    var box = document.getElementById('provider-reply-preview');
    if (!box) return;
    box.innerHTML = html !== '' ? html : '<span class="text-muted">—</span>';
  }

  var btn = document.getElementById('btn-update-reply-preview');
  if (btn) btn.addEventListener('click', updatePreview);

  // تحديث تلقائي (خفيف)
  var ed = document.getElementById('provider-reply-editor');
  if (ed) {
    ed.addEventListener('input', function () {
      // لا نحدث كل حرف إذا summernote؛ نتركه للزر أو events
    });

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.summernote) {
      try {
        window.jQuery(ed).on('summernote.change', function () {
          updatePreview();
        });
      } catch (e) {}
    }
  }
})();
</script>
