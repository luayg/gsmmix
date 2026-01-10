{{-- resources/views/admin/services/imei/_modal_create.blade.php --}}
<form id="serviceCreateForm"
      action="{{ route('admin.services.imei.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  <input type="hidden" name="supplier_id" id="cloneSupplierId">
  <input type="hidden" name="remote_id" id="cloneRemoteId">

  {{-- ✅ TAB PANES (NO EXTRA BUTTONS!) --}}
  <div class="service-tab-pane" data-tab="general">

    <div class="row g-3">

      <div class="col-xl-6">
        <div class="row g-3">

          <div class="col-12">
            <label class="form-label">Name</label>
            <input name="name" type="text" class="form-control" required>
          </div>

          <div class="col-12">
            <label class="form-label">Alias (Unique name containing only latin lowercase characters and dashes)</label>
            <input name="alias" type="text" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Delivery time</label>
            <input name="time" type="text" class="form-control">
          </div>

          <div class="col-md-6">
            <label class="form-label">Group</label>
            <select name="group_id" class="form-select">
              <option value="">Group</option>
              @foreach(($groups ?? []) as $g)
                <option value="{{ $g->id }}">{{ $g->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Main field type</label>
            <select name="main_field_type" class="form-select">
              <option value="IMEI" selected>IMEI</option>
              <option value="Serial">Serial</option>
              <option value="Number">Number</option>
              <option value="Email">Email</option>
              <option value="Text">Text</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
              <option value="imei" selected>IMEI</option>
              <option value="server">Server</option>
              <option value="file">File</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Price</label>
            <div class="input-group">
              <input id="pricePreview" type="text" class="form-control" disabled>
              <span class="input-group-text">Credits</span>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Converted price</label>
            <div class="input-group">
              <input id="convertedPricePreview" type="text" class="form-control" disabled>
              <span class="input-group-text">USD</span>
            </div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Cost</label>
            <div class="input-group">
              <input name="cost" type="number" step="0.0001" class="form-control" value="0.0000">
              <span class="input-group-text">Credits</span>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label">Profit</label>
            <div class="input-group">
              <input name="profit" type="number" step="0.0001" class="form-control" value="0.0000">
              <span class="input-group-text">Credits</span>
            </div>
          </div>

          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <select name="profit_type" class="form-select">
              <option value="1" selected>Credits</option>
              <option value="2">Percent</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Source</label>
            <select name="source" class="form-select">
              <option value="manual">Manual</option>
              <option value="api" selected>API</option>
              <option value="supplier">Supplier</option>
              <option value="local">Local source</option>
            </select>
          </div>

          <div class="col-12 js-api-block d-none">
            <div class="border rounded p-3">
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label">API connection</label>
                  <select class="form-select js-api-provider" name="api_provider_id"></select>
                </div>
                <div class="col-12">
                  <label class="form-label">API service</label>
                  <select class="form-select js-api-service" name="api_service_remote_id"></select>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="active" value="1" checked>
              <label class="form-check-label">Active</label>
            </div>

            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="allow_bulk" value="1">
              <label class="form-check-label">Allow bulk orders</label>
            </div>

            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="allow_duplicates" value="1">
              <label class="form-check-label">Allow duplicates</label>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label">Reply expiration (minutes)</label>
            <input name="reply_expiration" type="number" class="form-control" value="0">
          </div>

        </div>
      </div>

      <div class="col-xl-6">
        <label class="form-label">Info</label>
        <textarea id="infoEditor" class="form-control d-none"></textarea>
        <input type="hidden" name="info" id="infoHidden">
      </div>

    </div>
  </div>


  {{-- ✅ Additional --}}
  <div class="service-tab-pane d-none" data-tab="additional">
    <div class="p-3">
      <h6 class="mb-3">Groups Prices</h6>
      <div class="text-muted">⚠️ سيتم إضافة نظام Groups Prices لاحقاً حسب مشروعك.</div>
    </div>
  </div>


  {{-- ✅ Meta --}}
  <div class="service-tab-pane d-none" data-tab="meta">
    <div class="row g-3 p-3">
      <div class="col-md-6">
        <label class="form-label">Meta keywords</label>
        <input name="meta_keywords" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">After "head" tag opening</label>
        <textarea name="after_head_open" class="form-control" rows="2"></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">Meta description</label>
        <textarea name="meta_description" class="form-control" rows="3"></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Before "head" tag closing</label>
        <textarea name="before_head_close" class="form-control" rows="2"></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">After "body" tag opening</label>
        <textarea name="after_body_open" class="form-control" rows="2"></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Before "body" tag closing</label>
        <textarea name="before_body_close" class="form-control" rows="2"></textarea>
      </div>
    </div>
  </div>


  <div class="d-flex justify-content-end gap-2 mt-3">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>

@push('scripts')
<script>
  window.initModalCreateSummernote = function(scope){
    const $ = window.jQuery;
    const $ed = $(scope).find('#infoEditor');
    $ed.summernote({ placeholder:'Write service info, rules, terms...', height:340 });

    scope.querySelector('#serviceCreateForm')?.addEventListener('submit', ()=> {
      scope.querySelector('#infoHidden').value = $ed.summernote('code');
    });
  }
</script>
@endpush
