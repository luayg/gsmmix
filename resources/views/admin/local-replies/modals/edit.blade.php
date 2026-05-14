<div class="modal-header bg-warning text-white">
  <h5 class="modal-title">Reply #{{ $reply->id }} | Edit</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.replies.update', $reply) }}">
  @csrf
  @method('PUT')

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">Source</label>
      <select name="local_source_id" class="form-select">
        <option value="">None</option>
        @foreach($sources as $source)
          <option value="{{ $source->id }}" @selected((int)$reply->local_source_id === (int)$source->id)>{{ $source->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" value="1" name="device_based" id="deviceBasedEdit" @checked($reply->device_based)>
      <label class="form-check-label" for="deviceBasedEdit">Device based replies</label>
    </div>

    <div class="mb-3" id="deviceIdentifierWrap">
      <label class="form-label">Device</label>
      <input type="text" name="device_identifier" class="form-control" value="{{ $reply->device_identifier }}">
    </div>

    <div class="mb-3">
      <label class="form-label">Reply</label>
      <textarea name="reply" class="form-control" rows="5" required>{{ $reply->reply }}</textarea>
    </div>

    <div class="mb-0">
      <label class="form-label">Expiration</label>
      <input type="datetime-local"
             name="expires_at"
             class="form-control"
             value="{{ $reply->expires_at ? $reply->expires_at->format('Y-m-d\TH:i') : '' }}">
      <div class="form-text">Leave empty for None.</div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>

<script>
(function(){
  const toggle = document.getElementById('deviceBasedEdit');
  const wrap = document.getElementById('deviceIdentifierWrap');
  function sync(){
    wrap?.classList.toggle('d-none', !toggle?.checked);
  }
  toggle?.addEventListener('change', sync);
  sync();
})();
</script>
