{{-- Modal: Add API --}}
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
        <select name="type" id="api_type" class="form-select" required>
          @php $types=['dhru'=>'DHRU API','webx'=>'WebX API','gsmhub'=>'GSM Hub API','unlockbase'=>'Unlock Base API (v3.x)','simple_link'=>'Simple link']; @endphp
          @foreach($types as $k=>$v)
            <option value="{{ $k }}" @selected(old('type')===$k)>{{ $v }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">URL</label>
        <input type="text" name="url" class="form-control" required value="{{ old('url') }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="{{ old('username') }}">
      </div>

      <div class="col-md-3">
        <label class="form-label">Key</label>
        <input type="text" name="api_key" class="form-control" value="{{ old('api_key') }}">
      </div>
    </div>

    {{-- ✅ Simple Link Options --}}
    <div id="simple_link_box" class="mt-3 p-3 border rounded" style="display:none;">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Main field name</label>
          <input type="text" name="main_field_name" id="main_field_name" class="form-control"
                 value="{{ old('main_field_name','imei') }}">
          <small class="text-muted">
            مثال: إذا رابطك يحتوي <code>?imei=123...</code> إذًا main field = <code>imei</code> (حساس لحالة الأحرف)
          </small>
        </div>

        <div class="col-md-6">
          <label class="form-label">Method</label>
          <select name="method" id="simple_method" class="form-select">
            <option value="GET"  @selected(old('method')==='GET')>GET</option>
            <option value="POST" @selected(old('method','POST')==='POST')>POST</option>
          </select>
          <div class="alert alert-warning mt-2 mb-0">
            <b>Warning!!!</b>
            إذا المزود يرجّع HTTP 200 دائمًا، النظام سيعتبر الطلب <b>Success</b> ويستخدم محتوى الرد كـ Reply.
            لتعامل الرفض بشكل صحيح، المزود لازم يرجّع HTTP غير 200 عند الأخطاء.
          </div>
        </div>
      </div>
    </div>

    <hr class="my-3">

    {{-- toggles --}}
    <div class="row gy-3">
      {{-- hidden=0 لضمان وصول false عند الإطفاء --}}
      <input type="hidden" name="sync_imei" value="0">
      <input type="hidden" name="sync_server" value="0">
      <input type="hidden" name="sync_file" value="0">
      <input type="hidden" name="ignore_low_balance" value="0">
      <input type="hidden" name="auto_sync" value="0">
      <input type="hidden" name="active" value="0">

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
    <button class="btn btn-success">
      <i class="fas fa-save me-1"></i> Add
    </button>
  </div>
</form>

<script>
(function(){
  function toggleSimpleLink(){
    var t = document.getElementById('api_type').value;
    var box = document.getElementById('simple_link_box');
    box.style.display = (t === 'simple_link') ? 'block' : 'none';
  }
  document.getElementById('api_type').addEventListener('change', toggleSimpleLink);
  toggleSimpleLink();
})();
</script>
