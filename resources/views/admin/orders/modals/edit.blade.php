{{-- resources/views/admin/orders/modals/edit.blade.php --}}
@php
  // يدعم المتغيرين row/order لتجنب أي تعارض قديم
  $row = $row ?? ($order ?? null);
  $routePrefix = $routePrefix ?? 'admin.orders.imei';

  // Normalize response إلى array
  $resp = $row?->response ?? null;
  if (is_string($resp)) {
      $decoded = json_decode($resp, true);
      $resp = is_array($decoded) ? $decoded : [];
  }
  if (!is_array($resp)) $resp = [];

  $items = isset($resp['result_items']) && is_array($resp['result_items']) ? $resp['result_items'] : [];
  $img   = $resp['result_image'] ?? null;

  $providerReplyHtml = $resp['provider_reply_html'] ?? '';

  $isSafeImg = function ($url) {
      if (!is_string($url)) return false;
      $u = trim($url);
      return str_starts_with($u, 'http://') || str_starts_with($u, 'https://') || str_starts_with($u, 'data:image/');
  };
@endphp

<div class="modal-header">
  <h5 class="modal-title">Edit Order #{{ $row?->id ?? '' }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route($routePrefix.'.update', $row?->id ?? 0) }}">
  @csrf

  <div class="modal-body">
    <div class="row g-3">

      {{-- LEFT --}}
      <div class="col-lg-5">
        <table class="table table-sm table-bordered align-middle mb-0">
          <tbody>
            <tr><th style="width:160px;">Service</th><td>{{ $row?->service?->name ?? '—' }}</td></tr>
            <tr><th>User</th><td>{{ $row?->email ?? '—' }}</td></tr>
            <tr><th>Device</th><td>{{ $row?->device ?? '—' }}</td></tr>
            <tr><th>Provider</th><td>{{ $row?->provider?->name ?? '—' }}</td></tr>
            <tr><th>Remote ID</th><td>{{ $row?->remote_id ?? '—' }}</td></tr>
            <tr><th>IP</th><td>{{ $row?->ip ?? '—' }}</td></tr>
          </tbody>
        </table>

        <div class="row g-2 mt-3">
          <div class="col-12">
            <label class="form-label">Status</label>
            @php($cur = $row?->status ?? 'waiting')
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
            <input type="text" name="comments" class="form-control" value="{{ $row?->comments ?? '' }}">
          </div>
        </div>

        {{-- Result Preview (بدون Blade foreach) --}}
        <div class="mt-3">
          <label class="form-label mb-1">Result Preview</label>
          <div class="border rounded p-2 bg-white" style="min-height:160px;">
            <?php if (!empty($img) && $isSafeImg($img)) { ?>
              <div class="mb-2 text-center">
                <img src="{{ $img }}" alt="Result image" style="max-width:240px;height:auto;" class="img-fluid rounded shadow-sm">
              </div>
            <?php } ?>

            <?php if (is_array($items) && count($items)) { ?>
              <table class="table table-sm table-striped table-bordered mb-0">
                <tbody>
                <?php foreach ($items as $it) { 
                    $label = is_array($it) ? ($it['label'] ?? '') : '';
                    $value = is_array($it) ? ($it['value'] ?? '') : '';
                ?>
                  <tr>
                    <th style="width:200px;"><?php echo htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8'); ?></th>
                    <td><?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php } ?>
                </tbody>
              </table>
            <?php } else { ?>
              <span class="text-muted">—</span>
            <?php } ?>
          </div>
        </div>
      </div>

      {{-- RIGHT --}}
      <div class="col-lg-7">
        <div class="border rounded">
          <div class="p-2 border-bottom d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Provider Reply (editable)</div>
            <small class="text-muted">Summernote</small>
          </div>

          <div class="p-2">
            <textarea
              name="provider_reply_html"
              class="form-control"
              rows="10"
              data-summernote="1"
              data-summernote-height="260"
            >{!! old('provider_reply_html', $providerReplyHtml) !!}</textarea>
          </div>

          <div class="p-2 border-top">
            <div class="fw-semibold mb-2">Reply Preview</div>
            <div class="border rounded p-2 bg-white" style="min-height:200px;">
              {!! $providerReplyHtml ?: '<span class="text-muted">—</span>' !!}
            </div>
          </div>
        </div>

        {{-- اختياري: تعديل response كله --}}
        <div class="mt-3">
          <label class="form-label">Response (optional)</label>
          <textarea name="response" class="form-control" rows="5" placeholder="اختياري"></textarea>
        </div>
      </div>

    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>
