{{-- resources/views/admin/services/imei/_modal_create.blade.php --}}
<form id="serviceCreateForm"
      class="service-create-form"
      action="{{ route('admin.services.imei.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  <input type="hidden" name="supplier_id" value="">
  <input type="hidden" name="remote_id" value="">
  <input type="hidden" name="group_name" value="">

  {{-- ✅ Pricing table json (service_group_prices) --}}
  <input type="hidden" name="pricing_table" id="pricingTableHidden" value="[]">

  <div class="service-tabs-content">

    {{-- ===================== ✅ GENERAL TAB ===================== --}}
    <div class="tab-pane active" data-tab="general">

      <div class="row g-3">
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
              <input name="alias" type="text" class="form-control">
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Delivery time</label>
              <input name="time" type="text" class="form-control">
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
            <div class="col-12 js-api-block">
              <div class="border rounded p-3 bg-light">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label mb-1">API connection</label>
                    <select class="form-select js-api-provider" name="api_provider_id"></select>
                  </div>
                  <div class="col-12">
                    <label class="form-label mb-1">API service</label>
                    <select class="form-select js-api-service" name="api_service_remote_id"></select>
                  </div>
                </div>
              </div>
            </div>

            {{-- ✅ switches --}}
            @php
              $toggles = [
                'use_remote_cost'    => 'Sync the cost of this service with price of remote API service',
                'use_remote_price'   => 'Sync the main and special prices of this service with price of remote API service',
                'stop_on_api_change' => 'Stop API if the remote service price went up',
                'needs_approval'     => 'Needs approval',
                'active'             => 'Active',
                'allow_bulk'         => 'Allow bulk orders',
                'allow_duplicates'   => 'Allow duplicates',
                'reply_with_latest'  => 'Reply with latest success result if possible',
                'allow_report'       => 'Allow submit to verify (success orders)',
                'allow_cancel'       => 'Allow cancel (waiting action orders)',
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
                         @checked($name === 'active')>
                  <label class="form-check-label" for="sw_{{ $name }}">{{ $label }}</label>
                </div>
              @endforeach
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Reporting deny timeout</label>
              <div class="input-group">
                <input name="allow_report_time" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Cancellation deny timeout</label>
              <div class="input-group">
                <input name="allow_cancel_time" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Reply expiration</label>
              <div class="input-group">
                <input name="reply_expiration" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
            </div>

          </div>
        </div>

        {{-- ✅ Summernote --}}
        <div class="col-xl-5">
          <label class="form-label mb-1">Info</label>
          <textarea id="infoEditor" class="form-control"></textarea>
          <input type="hidden" name="info" id="infoHidden">
        </div>
      </div>
    </div>

    {{-- ===================== ✅ ADDITIONAL TAB ===================== --}}
    <div class="tab-pane" data-tab="additional">

      <div class="d-flex justify-content-between mb-3">
        <div class="fw-bold">Custom fields</div>
        <button type="button" class="btn btn-link p-0" id="btnAddField">Add field</button>
      </div>

      <div class="row g-3">
        <div class="col-xl-6">
          <div class="border rounded p-3 bg-white" id="fieldsWrap" style="min-height:220px">
            <div class="text-muted small">Fields UI will be implemented here.</div>
          </div>
        </div>

        <div class="col-xl-6">
          <div class="fw-bold mb-2">Groups</div>

<div id="groupsPricingWrap" class="border rounded p-3 bg-white">
  <div id="groupsPricingList"></div>
</div>

      </div>

    </div>

          <input type="hidden" name="group_prices" id="groupPricesJson">
    


    {{-- ===================== ✅ META TAB ===================== --}}
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

  <div class="service-actions d-flex justify-content-end gap-2 mt-3">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>
