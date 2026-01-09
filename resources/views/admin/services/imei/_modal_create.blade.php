{{-- resources/views/admin/services/imei/_modal_create.blade.php --}}
<form id="serviceCreateForm"
      class="service-create-form"
      action="{{ route('admin.services.imei.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label mb-1">Name</label>
          <input name="name" type="text" class="form-control" required>
        </div>

        <div class="col-12">
          <label class="form-label mb-1">Alias (lowercase, a-z, 0-9, dashes)</label>
          <input name="alias" type="text" class="form-control" placeholder="lowercase-and-dashes">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Delivery time</label>
          <input name="time" type="text" class="form-control" placeholder="e.g. 1-24h">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Group</label>
          <select name="group_id" class="form-select">
            <option value="">Group</option>
            @foreach(($groups ?? []) as $g)
              <option value="{{ $g->id }}">{{ $g->name }}</option>
            @endforeach
          </select>
        </div>

        {{-- Main field / type للاستخدام الواجهة فقط --}}
        <div class="col-md-6">
          <label class="form-label mb-1">Main field type</label>
          <select name="main_field_type" class="form-select">
            <option value="IMEI" selected>IMEI</option>
            <option value="Serial">Serial</option>
            <option value="Number">Number</option>
            <option value="Email">Email</option>
            <option value="Text">Text</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Type</label>
          <select name="type" class="form-select">
            <option value="imei" selected>IMEI</option>
            <option value="server">Server</option>
            <option value="file">File</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Main field label</label>
          <input name="main_field_label" type="text" class="form-control" value="IMEI">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Allowed characters</label>
          <select name="allowed_characters" class="form-select">
            <option value="numbers" selected>Numbers</option>
            <option value="any">Any</option>
            <option value="hex">HEX</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Minimum</label>
          <input name="min" type="number" class="form-control" value="15">
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Maximum</label>
          <input name="max" type="number" class="form-control" value="15">
        </div>

        {{-- Price/Converted previews --}}
        <div class="col-md-6">
          <label class="form-label mb-1">Price</label>
          <div class="input-group">
            <input id="pricePreview" type="text" class="form-control" value="0.00" disabled>
            <span class="input-group-text">Credits</span>
          </div>
          <small class="text-muted d-block mt-1">السعر = Cost + Profit</small>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Converted price</label>
          <div class="input-group">
            <input id="convertedPricePreview" type="text" class="form-control" value="0.00" disabled>
            <span class="input-group-text">USD</span>
          </div>
        </div>

        {{-- Cost/Profit --}}
        <div class="col-md-6">
          <label class="form-label mb-1">Cost</label>
          <div class="input-group">
            <input name="cost" type="number" step="0.0001" class="form-control" value="0.0000">
            <span class="input-group-text">Credits</span>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label mb-1">Profit</label>
          <div class="input-group">
            <input name="profit" type="number" step="0.0001" class="form-control" value="0.0000">
            <span class="input-group-text">Credits</span>
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">&nbsp;</label>
          <select name="profit_type" class="form-select">
            <option value="1" selected>Credits</option>
            <option value="2">Percent</option>
          </select>
        </div>

        {{-- Source --}}
        <div class="col-12">
          <label class="form-label mb-1">Source</label>
          <select name="source" class="form-select">
            <option value="manual">Manual</option>
            <option value="api" selected>API</option>
            <option value="supplier">Supplier</option>
            <option value="local">Local source</option>
          </select>
        </div>

        {{-- ✅ API block بعد Source مباشرة + مخفي افتراضياً --}}
        <div class="col-12 js-api-block d-none">
          <div class="border rounded p-3">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label mb-1">API connection</label>
                <select class="form-select js-api-provider" name="api_provider_id"></select>
              </div>
              <div class="col-12">
                <label class="form-label mb-1">API service</label>
                <select class="form-select js-api-service" name="api_service_remote_id"></select>
                <small class="text-muted">ابحث داخل القائمة مباشرة.</small>
              </div>
            </div>
          </div>
        </div>
        {{-- /API block --}}

        {{-- Toggles --}}
        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="s1" checked>
            <label class="form-check-label" for="s1">Active</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="allow_bulk" value="1" id="s2">
            <label class="form-check-label" for="s2">Allow bulk orders</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="allow_duplicates" value="1" id="s3">
            <label class="form-check-label" for="s3">Allow duplicates</label>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label mb-1">Reply expiration (minutes)</label>
          <input name="reply_expiration" type="number" class="form-control" value="0">
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <label class="form-label mb-1">Info</label>
      <textarea id="infoEditor" class="form-control d-none"></textarea>
      <input type="hidden" name="info" id="infoHidden">
      <small class="text-muted">Description, notes, terms…</small>
    </div>
  </div>

  <div class="service-actions d-flex justify-content-end gap-2">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>

@push('scripts')
<script>
  // تُستدعى بعد تحميل Summernote ديناميكيًا
  window.initModalCreateSummernote = function(scope){
    const $ = window.jQuery;
    const $ed = $(scope).find('#infoEditor');
    $ed.summernote({ placeholder:'Description, notes, terms...', height:320 });
    scope.querySelector('#serviceCreateForm')?.addEventListener('submit', ()=> {
      scope.querySelector('#infoHidden').value = $ed.summernote('code');
    });
  }
</script>
@endpush
