<div class="modal-header bg-success text-white">
  <h5 class="modal-title">Create reply</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.replies.store') }}">
  @csrf

  <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">Source</label>
      <select name="local_source_id" class="form-select">
        <option value="">None</option>
        @foreach($sources as $source)
          <option value="{{ $source->id }}">{{ $source->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" value="1" name="device_based" id="deviceBasedCreate">
      <label class="form-check-label" for="deviceBasedCreate">Device based replies</label>
    </div>

    <div class="alert alert-secondary border-start border-4 border-secondary small d-none" id="deviceBasedHelpCreate">
      <p class="mb-2">
        To add device based replies, please add replies by separating device identifier and reply with space, like in example below:
      </p>
      <div>1234XXXXXXXXXXXX <span class="text-success">Some reply</span></div>
      <div>1234XXXXXXXXXXXX <span class="text-success">Another reply</span></div>
      <p class="fw-semibold mb-0 mt-2">
        Warning!!! Device based replies will work only with services which are "Device based" enabled under "Local source" of service editing form
      </p>
    </div>

    <div class="mb-3">
      <label class="form-label">Replies</label>
      <div class="input-group">
        <input type="text" class="form-control" name="reply" id="singleReplyCreate">
        <button class="btn btn-outline-secondary" type="button" id="addReplyToBulkCreate">Add to bulk</button>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Bulk</label>
      <textarea class="form-control" name="bulk" id="bulkCreate" rows="5"></textarea>
    </div>

    <div class="mb-0">
      <label class="form-label">Expiration</label>
      <input type="datetime-local" name="expires_at" class="form-control">
      <div class="form-text">Leave empty for None.</div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>

<script>
(function(){
  const toggle = document.getElementById('deviceBasedCreate');
  const help = document.getElementById('deviceBasedHelpCreate');
  const reply = document.getElementById('singleReplyCreate');
  const bulk = document.getElementById('bulkCreate');
  const add = document.getElementById('addReplyToBulkCreate');

  function syncHelp(){
    help?.classList.toggle('d-none', !toggle?.checked);
  }

  toggle?.addEventListener('change', syncHelp);
  syncHelp();

  add?.addEventListener('click', function(){
    const value = (reply?.value || '').trim();
    if (!value) return;
    bulk.value = (bulk.value ? bulk.value.replace(/\s+$/, '') + "\n" : '') + value;
    reply.value = '';
    reply.focus();
  });
})();
</script>
