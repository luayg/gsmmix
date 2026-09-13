{{-- Modal: Edit API --}}
<div class="modal-header bg-warning text-dark">
  <h5 class="modal-title">{{ $provider->name }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

@php
  $types = [
    'dhru' => 'DHRU API', 'webx' => 'WebX API', 'gsmhub' => 'GSM Hub API',
    'unlockbase' => 'Unlock Base API (v3.x)', 'simple_link' => 'Simple link', 'smm' => 'SMM API',
  ];
  $p = is_array($provider->params) ? $provider->params : [];
  $simpleMain = old('main_field_name', $p['main_field'] ?? 'imei');
  $simpleMethod = strtoupper(old('method', $p['method'] ?? 'POST'));
@endphp

<form method="POST" action="{{ route('admin.apis.update', $provider) }}" id="apiProviderEditForm">
  @csrf
  @method('PUT')
  <div class="modal-body">
    @if (session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
    @if ($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required value="{{ old('name', $provider->name) }}"></div>
      <div class="col-md-6"><label class="form-label">Type</label><select name="type" id="api_type" class="form-select" required>@foreach($types as $k => $v)<option value="{{ $k }}" @selected(old('type', $provider->type) === $k)>{{ $v }}</option>@endforeach</select></div>
      <div class="col-md-12"><label class="form-label">Link</label><input type="text" name="url" class="form-control" required value="{{ old('url', $provider->url) }}"></div>
      <div class="col-md-6" id="username_wrap"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="{{ old('username', $provider->username) }}"></div>
      <div class="col-md-6" id="key_wrap">
        <label class="form-label">Key</label>
        <input type="password" id="api_key_replacement" class="form-control" value="" autocomplete="new-password" placeholder="Leave blank to keep the current key">
        <div class="form-text">The saved key is never displayed. Enter a new key only to replace it.</div>
      </div>
    </div>

    <div id="simple_link_box" class="mt-3 p-3 border rounded" style="display:none;">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Main field name</label><input type="text" name="main_field_name" id="main_field_name" class="form-control" value="{{ $simpleMain }}"><small class="text-muted">For example, if your link contains <code>&imei=123456789012345</code>, use <code>imei</code>. It is case sensitive.</small></div>
        <div class="col-md-6"><label class="form-label">Method</label><select name="method" id="simple_method" class="form-select"><option value="GET" @selected($simpleMethod === 'GET')>GET</option><option value="POST" @selected($simpleMethod === 'POST')>POST</option></select><div class="alert alert-warning mt-2 mb-0"><b>Warning!!!</b> Return a non-200 HTTP status for rejected requests.</div></div>
      </div>
    </div>

    <hr class="my-3">
    @foreach(['sync_imei','sync_server','sync_file','sync_smm','ignore_low_balance','auto_sync','active'] as $flag)<input type="hidden" name="{{ $flag }}" value="0">@endforeach
    <div class="row gy-3">
      @foreach(['sync_imei'=>'Sync IMEI services','sync_server'=>'Sync server services','sync_file'=>'Sync file services','sync_smm'=>'Sync SMM service','ignore_low_balance'=>'Ignore low balance','auto_sync'=>'Auto sync','active'=>'Active'] as $flag => $label)
        <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="{{ $flag }}" id="{{ $flag }}" value="1" @checked(old($flag, $provider->{$flag} ?? ($flag === 'sync_smm' ? 1 : 0)))><label class="form-check-label" for="{{ $flag }}">{{ $label }}</label></div></div>
      @endforeach
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary"><i class="fas fa-save me-1"></i> Save</button></div>
</form>

<script>
(function(){
  const form = document.getElementById('apiProviderEditForm');
  const keyInput = document.getElementById('api_key_replacement');

  form?.addEventListener('submit', function(){
    if (keyInput && keyInput.value.trim() !== '') keyInput.name = 'api_key';
  });

  function toggleSimpleLink(){
    var t = document.getElementById('api_type').value;
    var isSimple = (t === 'simple_link');
    var isSmm = (t === 'smm');
    var box = document.getElementById('simple_link_box');
    var usernameWrap = document.getElementById('username_wrap');
    var keyWrap = document.getElementById('key_wrap');
    if (box) box.style.display = isSimple ? 'block' : 'none';
    if (usernameWrap) usernameWrap.style.display = (isSimple || isSmm) ? 'none' : 'block';
    if (keyWrap) keyWrap.style.display = isSimple ? 'none' : 'block';
  }
  document.getElementById('api_type').addEventListener('change', toggleSimpleLink);
  toggleSimpleLink();
})();
</script>
