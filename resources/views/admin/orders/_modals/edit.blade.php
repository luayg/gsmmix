<div class="modal-header">
  <h5 class="modal-title">Order #{{ $row->id }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="orderEditForm">
  <div class="modal-body">

    <div class="row g-3">
      <div class="col-md-6">
        <div class="mb-2"><b>Service:</b> {{ optional($row->service)->name ?? '—' }}</div>
        <div class="mb-2"><b>Provider:</b> {{ optional($row->provider)->name ?? '—' }}</div>
        <div class="mb-2"><b>{{ $deviceLabel ?? 'Device' }}:</b> {{ $row->device }}</div>
        <div class="mb-2"><b>Remote ID:</b> {{ $row->remote_id ?: '—' }}</div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Status</label>
        <select class="form-select" name="status" required>
          @foreach(['waiting','inprogress','success','rejected','cancelled'] as $st)
            <option value="{{ $st }}" @selected(($row->status ?? 'waiting')===$st)>{{ ucfirst($st) }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <hr>

    <div class="mb-3">
      <label class="form-label">Comments</label>
      <textarea class="form-control" name="comments" rows="3">{{ $row->comments }}</textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Reply / Result (will be saved into response)</label>
      <textarea class="form-control" name="reply" rows="6">{{ is_string($row->response) ? $row->response : '' }}</textarea>
      <div class="form-text">هنا نضع تفاصيل الرد (مثل not enough credit أو تفاصيل Check كاملة).</div>
    </div>

  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>

@push('scripts')
<script>
(function(){
  const form = document.getElementById('orderEditForm');
  if(!form) return;

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);

    try{
      const r = await fetch(@json(route($routePrefix.'.update', $row->id)), {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': window.CSRF_TOKEN
        },
        body: (function(){
          fd.append('_method','PUT');
          return fd;
        })()
      });

      const j = await r.json();
      if(!r.ok || !j.ok){
        window.showToast('danger', (j.message || 'Failed'));
        return;
      }

      window.showToast('success', 'Saved');

      const modalEl = document.getElementById('ajaxModal');
      window.bootstrap?.Modal.getInstance(modalEl)?.hide();

      window.location.reload();
    }catch(err){
      console.error(err);
      window.showToast('danger', 'Failed');
    }
  });
})();
</script>
@endpush
