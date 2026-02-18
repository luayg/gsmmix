{{-- resources/views/admin/services/imei/_modal_create.blade.php --}}

<form id="serviceCreateForm"
      class="service-create-form js-ajax-form"
      action="{{ route('admin.services.imei.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  <input type="hidden" name="main_field" id="mainFieldHidden" value="">
  <input type="hidden" name="params" id="paramsHidden" value="{}">

  <input type="hidden" name="name_en" id="nameEnHidden" value="">
  <input type="hidden" name="main_type" id="mainTypeHidden" value="imei">

  <input type="hidden" name="custom_fields_json" id="customFieldsJson" value="[]">

  <input type="hidden" name="supplier_id" value="">
  <input type="hidden" name="remote_id" value="">
  <input type="hidden" name="group_name" value="">

  <div class="service-tabs-content">

    {{-- ===================== ✅ GENERAL TAB ===================== --}}
    <div class="tab-pane active" data-tab="general">
      <div class="row g-3">

        <div class="col-xl-7">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label mb-1">Name</label>
              <input name="name" id="nameInput" type="text" class="form-control" required>
              <small class="text-muted">سيتم تعبئة name_en تلقائياً بنفس الاسم.</small>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Alias</label>
              <input name="alias" type="text" class="form-control" placeholder="unique-alias-like-this">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Delivery time</label>
              <input name="time" type="text" class="form-control" placeholder="e.g. 1-24h">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Group</label>
              <select name="group_id" class="form-select">
                <option value="">Group</option>
              </select>
            </div>

            {{-- ✅ IMEI: main field type visible --}}
            <div class="col-md-6">
              <label class="form-label mb-1">Main field type</label>
              <select name="main_field_type" class="form-select" id="mainFieldType">
                <option value="imei" selected>IMEI</option>
                <option value="serial">Serial</option>
                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="text">Text</option>
                <option value="custom">Custom</option>
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
              <input name="main_field_label" id="mainFieldLabel" type="text" class="form-control" value="IMEI">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Allowed characters</label>
              <select name="allowed_characters" id="allowedChars" class="form-select">
                <option value="any">Any</option>
                <option value="numbers" selected>Numbers</option>
                <option value="alphanumeric">Alphanumeric</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <div class="input-group">
                <input name="minimum" id="minChars" type="number" class="form-control" value="15">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <div class="input-group">
                <input name="maximum" id="maxChars" type="number" class="form-control" value="15">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input id="pricePreview" type="text" class="form-control" value="0.0000" readonly>
                <span class="input-group-text">Credits</span>
              </div>
              <div class="d-none">
                <input id="convertedPricePreview" type="text" value="0.0000" readonly>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Cost</label>
              <div class="input-group">
                <input name="cost" type="number" step="0.0001" class="form-control" value="0.0000">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Profit</label>
              <div class="input-group">
                <input name="profit" type="number" step="0.0001" class="form-control" value="0.0000">
                <span class="input-group-text">Credits</span>
                <select name="profit_type" class="form-select" style="max-width:120px">
                  <option value="1" selected>Credits</option>
                  <option value="2">Percent</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">Source</label>
              <select name="source" class="form-select">
                <option value="1" selected>Manual</option>
                <option value="2">API</option>
              </select>
            </div>

          </div>
        </div>

        <div class="col-xl-5">
          <label class="form-label mb-1">Info</label>

          {{-- ✅ Summernote (GLOBAL script expects textarea.summernote) --}}
          <textarea id="infoEditor"
                    class="form-control summernote"
                    data-summernote-height="320"
                    data-summernote-hidden="#infoHidden"
                    data-upload-url="{{ route('admin.uploads.summernote') }}"
                    rows="10"></textarea>

          <input type="hidden" name="info" id="infoHidden" value="">
          <small class="text-muted">Description, notes, terms…</small>
        </div>

      </div>
    </div>

    {{-- ===================== ✅ ADDITIONAL TAB ===================== --}}
    <div class="tab-pane" data-tab="additional">
      <div class="alert alert-light mb-0">
        Additional tab placeholder (IMEI عادة ما يحتاج حقول أقل، ولكن يمكنك نسخه من server إذا رغبت).
      </div>
    </div>

    {{-- ===================== ✅ META TAB (MATCH SERVER) ===================== --}}
    <div class="tab-pane" data-tab="meta">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Meta keywords</label>
          <input type="text" class="form-control" name="meta_keywords">
        </div>

        <div class="col-md-6">
          <label class="form-label">After "head" tag opening</label>
          <textarea class="form-control" rows="3" name="meta_after_head"></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Meta description</label>
          <textarea class="form-control" rows="3" name="meta_description"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Before "head" tag closing</label>
          <textarea class="form-control" rows="3" name="meta_before_head"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">After "body" tag opening</label>
          <textarea class="form-control" rows="3" name="meta_after_body"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Before "body" tag closing</label>
          <textarea class="form-control" rows="3" name="meta_before_body"></textarea>
        </div>
      </div>
    </div>

  </div>

  <div class="d-flex justify-content-end gap-2 mt-3">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>

</form>

<script>
(function(){
  const form = document.getElementById('serviceCreateForm');
  if(!form) return;

  const nameInput = form.querySelector('#nameInput');
  const nameEn    = form.querySelector('#nameEnHidden');

  const mainTypeHidden = form.querySelector('#mainTypeHidden');
  const mainFieldType  = form.querySelector('#mainFieldType');

  function buildMainFieldJson(){
    const type  = form.querySelector('#mainFieldType')?.value || 'imei';
    const label = form.querySelector('#mainFieldLabel')?.value || 'IMEI';
    const allowed = form.querySelector('#allowedChars')?.value || 'numbers';
    const min = Number(form.querySelector('#minChars')?.value || 0);
    const max = Number(form.querySelector('#maxChars')?.value || 0);

    return {
      type,
      label,
      allowed_characters: allowed,
      minimum: Number.isFinite(min) ? min : 0,
      maximum: Number.isFinite(max) ? max : 0,
    };
  }

  function syncMainFieldHidden(){
    const hidden = form.querySelector('#mainFieldHidden');
    if(!hidden) return;
    hidden.value = JSON.stringify(buildMainFieldJson());
  }

  function syncMainTypeHidden(){
    if(!mainTypeHidden || !mainFieldType) return;
    mainTypeHidden.value = mainFieldType.value || 'imei';
  }

  nameInput?.addEventListener('input', () => {
    if(nameEn) nameEn.value = nameInput.value;
  });

  mainFieldType?.addEventListener('change', () => {
    syncMainTypeHidden();
    syncMainFieldHidden();
  });

  ['#mainFieldLabel','#allowedChars','#minChars','#maxChars'].forEach(sel=>{
    form.querySelector(sel)?.addEventListener('input', syncMainFieldHidden);
    form.querySelector(sel)?.addEventListener('change', syncMainFieldHidden);
  });

  syncMainTypeHidden();
  syncMainFieldHidden();
})();
</script>
