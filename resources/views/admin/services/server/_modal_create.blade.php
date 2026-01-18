{{-- resources/views/admin/services/server/_modal_create.blade.php --}}
<form id="serviceCreateForm"
      class="service-create-form"
      action="{{ route('admin.services.server.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  {{-- ✅ Injected by service-modal.js --}}
  <input type="hidden" name="supplier_id" value="">
  <input type="hidden" name="remote_id" value="">
  <input type="hidden" name="group_name" value="">

  <div class="service-tabs-content">

    {{-- ===================== ✅ GENERAL TAB ===================== --}}
    <div class="tab-pane active" data-tab="general">
      <div class="row g-3">

        {{-- LEFT SIDE --}}
        <div class="col-xl-7">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label mb-1">Name</label>
              <input name="name" type="text" class="form-control" required>
            </div>

            <div class="col-12">
              <label class="form-label mb-1">
                Alias (Unique name containing only latin lowercase characters and dashes)
              </label>
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

            <div class="col-md-6">
              <label class="form-label mb-1">Main field type</label>
              <select name="main_field_type" class="form-select">
                <option value="Serial" selected>Serial</option>
                <option value="IMEI">IMEI</option>
                <option value="Number">Number</option>
                <option value="Email">Email</option>
                <option value="Text">Text</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Type</label>
              <select name="type" class="form-select">
                <option value="server" selected>Server</option>
                <option value="imei">IMEI</option>
                <option value="file">File</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Main field label</label>
              <input name="main_field_label" type="text" class="form-control" value="Serial">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Allowed characters</label>
              <select name="allowed_characters" class="form-select">
                <option value="any" selected>Any</option>
                <option value="numbers">Numbers</option>
                <option value="hex">HEX</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <input name="min" type="number" class="form-control" value="1">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <input name="max" type="number" class="form-control" value="50">
            </div>

            {{-- Price --}}
            <div class="col-md-6">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input id="pricePreview" type="text" class="form-control" value="0.0000" disabled>
                <span class="input-group-text">Credits</span>
              </div>
              <small class="text-muted d-block mt-1">Price = Cost + Profit</small>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Converted price</label>
              <div class="input-group">
                <input id="convertedPricePreview" type="text" class="form-control" value="0.0000" disabled>
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
                <option value="1">Manual</option>
                <option value="2" selected>API</option>
                <option value="3">Supplier</option>
                <option value="4">Local source</option>
              </select>
            </div>

            {{-- ✅ API block --}}
            <div class="col-12 js-api-block d-none">
              <div class="border rounded p-3 bg-light">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label mb-1">API connection</label>
                    <select class="form-select js-api-provider" name="api_provider_id"></select>
                  </div>
                  <div class="col-12">
                    <label class="form-label mb-1">API service</label>
                    <select class="form-select js-api-service" name="api_service_remote_id"></select>
                    <small class="text-muted">Search directly inside list.</small>
                  </div>
                </div>
              </div>
            </div>

            {{-- switches --}}
            @php
              $toggles = [
                'active'           => 'Active',
                'allow_bulk'       => 'Allow bulk orders',
                'allow_duplicates' => 'Allow duplicates',
              ];
            @endphp

            <div class="col-12">
              @foreach($toggles as $name => $label)
                <input type="hidden" name="{{ $name }}" value="0">
                <div class="form-check form-switch mb-1">
                  <input class="form-check-input"
                         type="checkbox"
                         name="{{ $name }}"
                         value="1"
                         id="sw_{{ $name }}"
                         @checked(in_array($name,['active','allow_bulk']) ? true : false)>
                  <label class="form-check-label" for="sw_{{ $name }}">{{ $label }}</label>
                </div>
              @endforeach
            </div>

          </div>
        </div>

        {{-- RIGHT SIDE --}}
        <div class="col-xl-5">
          <label class="form-label mb-1">Info</label>

          <textarea id="infoEditor" class="form-control d-none"></textarea>
          <input type="hidden" name="info" id="infoHidden">

          <small class="text-muted">Description, notes, terms…</small>
        </div>
      </div>
    </div>

  </div>

  <div class="service-actions d-flex justify-content-end gap-2 mt-3">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>

</form>
