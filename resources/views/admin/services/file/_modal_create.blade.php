{{-- resources/views/admin/services/file/_modal_create.blade.php --}}
<form id="serviceCreateForm" action="{{ route('admin.services.file.store') }}" method="POST" data-ajax="1">
  @csrf
  <div class="row g-3">
    <div class="col-xl-7">
      <div class="row g-3">
        <div class="col-12"><label class="form-label mb-1">Name</label><input name="name" type="text" class="form-control" required></div>
        <div class="col-12"><label class="form-label mb-1">Alias</label><input name="alias" type="text" class="form-control" placeholder="lowercase-and-dashes"></div>
        <div class="col-md-6"><label class="form-label mb-1">Delivery time</label><input name="time" type="text" class="form-control" placeholder="e.g. 1-24h"></div>
        <div class="col-md-6"><label class="form-label mb-1">Group</label>
          <select name="group_id" class="form-select"><option value="">Group</option>@foreach(($groups ?? []) as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select>
        </div>

        <div class="col-md-6"><label class="form-label mb-1">Main field type</label>
          {{-- ✅ غير معطّل --}}
          <select name="main_field_type" class="form-select"><option value="IMEI">IMEI</option><option value="Serial" selected>Serial</option><option value="Number">Number</option><option value="Email">Email</option><option value="Text">Text</option></select>
        </div>
        <div class="col-md-6"><label class="form-label mb-1">Type</label>
          <select name="type" class="form-select"><option value="file" selected>File</option><option value="imei">IMEI</option><option value="server">Server</option></select>
        </div>

        <div class="col-md-6"><label class="form-label mb-1">Main field label</label><input name="main_field_label" type="text" class="form-control" value="Serial"></div>
        <div class="col-md-6"><label class="form-label mb-1">Allowed characters</label>
          <select name="allowed_characters" class="form-select"><option value="any" selected>Any</option><option value="numbers">Numbers</option><option value="hex">HEX</option></select>
        </div>

        <div class="col-md-6"><label class="form-label mb-1">Minimum</label><input name="min" type="number" class="form-control" value="1"></div>
        <div class="col-md-6"><label class="form-label mb-1">Maximum</label><input name="max" type="number" class="form-control" value="50"></div>

        <div class="col-md-6"><label class="form-label mb-1">Price</label>
          <div class="input-group"><input type="text" class="form-control" data-role="price-preview" value="0.00" disabled><span class="input-group-text">Credits</span></div>
          <small class="text-muted d-block mt-1">السعر = Cost + Profit</small>
        </div>
        <div class="col-md-6"><label class="form-label mb-1">Converted price</label><div class="input-group"><input type="text" class="form-control" data-role="converted-price" value="0.00" disabled><span class="input-group-text">USD</span></div></div>
        <div class="col-md-6"><label class="form-label mb-1">Cost</label><div class="input-group"><input name="cost" type="number" step="0.01" class="form-control" value="0.00"><span class="input-group-text">Credits</span></div></div>
        <div class="col-md-6"><label class="form-label mb-1">Profit</label><div class="input-group"><input name="profit" type="number" step="0.01" class="form-control" value="0.00">
          <select name="profit_type" class="form-select" style="max-width:130px"><option value="credits" selected>Credits</option><option value="percent">Percent</option></select></div>
        </div>

        <div class="col-12"><label class="form-label mb-1">Source</label>
          <select name="source" class="form-select"><option value="manual">Manual</option><option value="api" selected>API</option><option value="supplier">Supplier</option><option value="local">Local source</option></select>
        </div>

        {{-- ✅ API block مباشرة بعد Source --}}
        <div class="col-12 js-api-block">
          <div class="p-3 border rounded">
            <div class="fw-semibold mb-2">API</div>
            <div class="mb-2"><label class="form-label mb-1">API connection</label><select name="api_provider_id" class="form-select js-api-provider"></select></div>
            <div class="mb-2">
              <label class="form-label mb-1">API service</label>
              <select name="api_service_remote_id" class="form-select js-api-service"></select>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="f1"><label class="form-check-label" for="f1">Active</label></div>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_bulk" value="1" id="f2"><label class="form-check-label" for="f2">Allow bulk orders</label></div>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_duplicates" value="1" id="f3"><label class="form-check-label" for="f3">Allow duplicates</label></div>
        </div>

        <div class="col-md-6"><label class="form-label mb-1">Reply expiration (minutes)</label><input name="reply_expiration" type="number" class="form-control" value="0"></div>
      </div>
    </div>

    <div class="col-xl-5">
      <label class="form-label mb-1">Info</label>
      <textarea id="infoEditor" class="form-control d-none"></textarea>
      <input type="hidden" name="info" id="infoHidden"><small class="text-muted">Description, notes, terms…</small>
    </div>
  </div>

  <div class="mt-4 d-flex justify-content-end gap-2"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-success">Create</button></div>
</form>
