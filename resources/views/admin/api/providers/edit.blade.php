{{-- Modal: Edit API --}}
<div class="modal-header bg-warning text-dark">
  <h5 class="modal-title">{{ $provider->name }} | Edit</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form method="POST" action="{{ route('admin.apis.update', $provider) }}">
  @csrf @method('PUT')

  <div class="modal-body">
    @if (session('ok'))
      <div class="alert alert-success">{{ session('ok') }}</div>
    @endif
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    @php $types=['dhru'=>'DHRU API','webx'=>'WebX API','gsmhub'=>'GSM Hub API','unlockbase'=>'Unlock Base API (v3.x)','simple_link'=>'Simple link']; @endphp

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required value="{{ old('name',$provider->name) }}">
      </div>

      <div class="col-md-6">
        <label class="form-label">Type</label>
        <select name="type" class="form-select" required>
          @foreach($types as $k=>$v)
            <option value="{{ $k }}" @selected(old('type',$provider->type)===$k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">URL</label>
        <input type="text" name="url" class="form-control" required value="{{ old('url',$provider->url) }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="{{ old('username',$provider->username) }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">Key</label>
        <input type="text" name="api_key" class="form-control" value="{{ old('api_key',$provider->api_key) }}">
      </div>
    </div>

    <hr class="my-3">

    {{-- hidden false --}}
    <input type="hidden" name="sync_imei" value="0">
    <input type="hidden" name="sync_server" value="0">
    <input type="hidden" name="sync_file" value="0">
    <input type="hidden" name="ignore_low_balance" value="0">
    <input type="hidden" name="auto_sync" value="0">
    <input type="hidden" name="active" value="0">

    <div class="row gy-3">
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="sync_imei" id="sync_imei" value="1" @checked(old('sync_imei',$provider->sync_imei))>
          <label class="form-check-label" for="sync_imei">Sync IMEI services</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="sync_server" id="sync_server" value="1" @checked(old('sync_server',$provider->sync_server))>
          <label class="form-check-label" for="sync_server">Sync server services</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="sync_file" id="sync_file" value="1" @checked(old('sync_file',$provider->sync_file))>
          <label class="form-check-label" for="sync_file">Sync file services</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="ignore_low_balance" id="ignore_low_balance" value="1" @checked(old('ignore_low_balance',$provider->ignore_low_balance))>
          <label class="form-check-label" for="ignore_low_balance">Ignore low balance</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="auto_sync" id="auto_sync" value="1" @checked(old('auto_sync',$provider->auto_sync))>
          <label class="form-check-label" for="auto_sync">Auto sync</label>
        </div>
      </div>
      <div class="col-md-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="active" id="active" value="1" @checked(old('active',$provider->active))>
          <label class="form-check-label" for="active">Active</label>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    <button class="btn btn-primary"><i class="fas fa-save me-1"></i> Save</button>
  </div>
</form>
