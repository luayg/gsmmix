<div class="modal-header bg-success text-white">
  <h5 class="modal-title">Add API</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="POST" action="{{ route('admin.apis.store') }}">
  @csrf

  <div class="modal-body">
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
      </div>

      <div class="col-md-6">
        <label class="form-label">Type</label>
        @php
          $types = [
            'dhru' => 'DHRU API',
            'webx' => 'WebX API',
            'gsmhub' => 'GSM Hub API',
            'unlockbase' => 'Unlock Base API (v3.x)',
            'simple_link' => 'Simple link',
            'smm' => 'SMM API',
          ];
        @endphp
        <select name="type" id="api_type" class="form-select" required>
          @foreach($types as $k=>$v)
            <option value="{{ $k }}" @selected(old('type')===$k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-12">
        <label class="form-label">Link</label>
        <input type="text" name="url" class="form-control" required value="{{ old('url') }}">
      </div>

      <div class="col-md-6" id="username_wrap">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="{{ old('username') }}">
      </div>

      <div class="col-md-6" id="key_wrap">
        <label class="form-label">Key</label>
        <input type="text" name="api_key" class="form-control" value="{{ old('api_key') }}">
      </div>
    </div>

    <div id="simple_link_box" class="mt-3 p-3 border rounded" style="display:none;">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Main field name</label>
          <input type="text" name="main_field_name" id="main_field_name" class="form-control"
                 value="{{ old('main_field_name','imei') }}">
          <small class="text-muted">
            For example if your link looks like this:
            <code>https://example.com?key=XXXXXXXX&imei=123456789012345</code>,
            then your Main field name is <code>imei</code>.
            Please note that it is case sensitive.
          </small>
        </div>

        <div class="col-md-6">
          <label class="form-label">Method</label>
          <select name="method" id="simple_method" class="form-select">
            <option value="GET" @selected(old('method')==='GET')>GET</option>
            <option value="POST" @selected(old('method','POST')==='POST')>POST</option>
          </select>
          <div class="alert alert-warning mt-2 mb-0">
            <b>Warning!!!</b>
            If your link returns HTTP status 200 OK, orders will be replied as success,
            and the content will be the reply. To handle rejects, your link provider has to
            return HTTP status other than 200 OK on bad responses.
          </div>
        </div>
      </div>
    </div>

    <hr class="my-3">

    <input type="hidden" name="sync_imei" value="0">
    <input type="hidden" name="sync_server" value="0">
    <input type="hidden" name="sync_file" value="0">
    <input type="hidden" name="ignore_low_balance" value="0">
    <input type="hidden" name="auto_sync" value="0">
    <input type="hidden" name="active" value="0">

    <div class="row gy-3">
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="sync_imei" name="sync_imei" value="1" @checked(old('sync_imei'))>
          <label class="form-check-label" for="sync_imei">Sync IMEI services</label>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="sync_server" name="sync_server" value="1" @checked(old('sync_server'))>
          <label class="form-check-label" for="sync_server">Sync server services</label>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="sync_file" name="sync_file" value="1" @checked(old('sync_file'))>
          <label class="form-check-label" for="sync_file">Sync file services</label>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="ignore_low_balance" name="ignore_low_balance" value="1" @checked(old('ignore_low_balance'))>
          <label class="form-check-label" for="ignore_low_balance">Ignore low balance</label>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="auto_sync" name="auto_sync" value="1" @checked(old('auto_sync',1))>
          <label class="form-check-label" for="auto_sync">Auto sync</label>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="active" name="active" value="1" @checked(old('active',1))>
          <label class="form-check-label" for="active">Active</label>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button class="btn btn-success">Add</button>
  </div>
</form>

<script>
(function(){
  function toggleSimpleLink(){
    var t = document.getElementById('api_type').value;
    var isSimple = (t === 'simple_link');

    var box = document.getElementById('simple_link_box');
    var usernameWrap = document.getElementById('username_wrap');
    var keyWrap = document.getElementById('key_wrap');

    if (box) box.style.display = isSimple ? 'block' : 'none';
    if (usernameWrap) usernameWrap.style.display = isSimple ? 'none' : 'block';
    if (keyWrap) keyWrap.style.display = isSimple ? 'none' : 'block';
  }

  document.getElementById('api_type').addEventListener('change', toggleSimpleLink);
  toggleSimpleLink();
})();
</script>