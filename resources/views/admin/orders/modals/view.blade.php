@php
  $pickName = function ($v) {
    if (is_string($v)) {
      $s = trim($v);
      if ($s !== '' && $s[0] === '{') {
        $j = json_decode($s, true);
        if (is_array($j)) return $j['en'] ?? $j['fallback'] ?? reset($j) ?? $v;
      }
      return $v;
    }
    return (string)$v;
  };

  $badge = [
    'waiting'    => 'bg-secondary',
    'inprogress' => 'bg-info',
    'success'    => 'bg-success',
    'rejected'   => 'bg-danger',
    'cancelled'  => 'bg-dark',
  ][$row->status] ?? 'bg-secondary';

  $resp = is_array($row->response) ? $row->response : null;
  $items = $resp['result_items'] ?? null;
  $resultText = $resp['result_text'] ?? null;
@endphp

<div class="modal-header">
  <h5 class="modal-title">{{ $title ?? ('View Order #'.$row->id) }}</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
  <div class="row g-3">

    <div class="col-12">
      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <tbody>
            <tr>
              <th style="width:220px">Status</th>
              <td><span class="badge {{ $badge }}">{{ strtoupper($row->status) }}</span></td>
            </tr>
            <tr><th>Created at</th><td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td></tr>
            <tr><th>Service</th><td>{{ $row->service ? $pickName($row->service->name) : '—' }}</td></tr>
            <tr><th>Device</th><td>{{ $row->device ?? '—' }}</td></tr>
            <tr><th>User</th><td>{{ $row->email ?? '—' }}</td></tr>
            <tr><th>Provider</th><td>{{ $row->provider?->name ?? '—' }}</td></tr>
            <tr><th>Remote ID</th><td>{{ $row->remote_id ?? '—' }}</td></tr>
            <tr><th>Comments</th><td>{{ $row->comments ?: '—' }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">Provider reply</label>

      @if(is_array($items) && count($items))
        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered mb-0">
            <tbody>
            @foreach($items as $it)
              <tr>
                <th style="width:240px">{{ $it['label'] ?? '' }}</th>
                <td>{{ $it['value'] ?? '' }}</td>
              </tr>
            @endforeach
            </tbody>
          </table>
        </div>

      @elseif(is_string($resultText) && $resultText !== '')
        <pre class="border rounded p-3 bg-light mb-0" style="white-space:pre-wrap;">{{ $resultText }}</pre>

      @else
        <div class="border rounded p-3 bg-light mb-0">
          {{ is_array($resp) ? ($resp['message'] ?? '—') : '—' }}
        </div>
      @endif
    </div>

  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
