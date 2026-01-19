{{-- resources/views/admin/services/imei/_modal_create.blade.php --}}
<form id="serviceCreateForm"
      class="service-create-form"
      action="{{ route('admin.services.imei.store') }}"
      method="POST"
      data-ajax="1">
  @csrf

  {{-- ✅ Injected by service-modal.js --}}
  <input type="hidden" name="supplier_id" value="">
  <input type="hidden" name="remote_id" value="">
  <input type="hidden" name="group_name" value="">

  {{-- ✅ REQUIRED for Additional (Groups pricing + Custom fields) --}}
  <input type="hidden" id="pricingTableHidden" name="group_prices_json" value="[]">
  <input type="hidden" id="customFieldsHidden" name="custom_fields_json" value="[]">

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

            {{-- ✅ MAIN FIELD PRESETS --}}
            <div class="col-md-6">
              <label class="form-label mb-1">Main field type</label>
              <select name="main_field_type" class="form-select">
                <option value="imei" selected>IMEI</option>
                <option value="imei_serial">IMEI/Serial number</option>
                <option value="serial">Serial number</option>
                <option value="custom">Custom</option>

                <option value="number">Number</option>
                <option value="email">Email</option>
                <option value="text">Text</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Type</label>
              <select name="type" class="form-select">
                {{-- (اتركها حسب نظامك الحالي) --}}
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
                <option value="alnum">Letters and numbers</option>
                <option value="any">Any</option>
                <option value="hex">HEX</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Minimum</label>
              <div class="input-group">
                <input name="min" type="number" class="form-control" value="15">
                <span class="input-group-text">Characters</span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Maximum</label>
              <div class="input-group">
                <input name="max" type="number" class="form-control" value="15">
                <span class="input-group-text">Characters</span>
              </div>
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

            {{-- ✅ API block (hidden unless Source=API) --}}
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

            {{-- ✅ Required Switches --}}
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
                         @checked(in_array($name,['active','allow_bulk']) ? true : false)>
                  <label class="form-check-label" for="sw_{{ $name }}">{{ $label }}</label>
                </div>
              @endforeach
            </div>

            {{-- Reporting / Cancel timeouts --}}
            <div class="col-md-6">
              <label class="form-label mb-1">Reporting deny timeout</label>
              <div class="input-group">
                <input name="allow_report_time" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
              <small class="text-muted">Leave blank or set to 0 for unlimited</small>
            </div>

            <div class="col-md-6">
              <label class="form-label mb-1">Cancellation deny timeout</label>
              <div class="input-group">
                <input name="allow_cancel_time" type="number" class="form-control" value="0">
                <span class="input-group-text">Minutes</span>
              </div>
              <small class="text-muted">Leave blank or set to 0 for unlimited</small>
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

        {{-- RIGHT SIDE (INFO = SUMMERNOTE) --}}
        <div class="col-xl-5">
          <label class="form-label mb-1">Info</label>
          <textarea id="infoEditor" class="form-control d-none"></textarea>
          <input type="hidden" name="info" id="infoHidden">
          <small class="text-muted">Description, notes, terms…</small>
        </div>
      </div>
    </div>

    {{-- ===================== ✅ ADDITIONAL TAB ===================== --}}
    <div class="tab-pane" data-tab="additional">

      <div class="row g-3">
        {{-- LEFT: Custom fields --}}
        <div class="col-lg-7">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold">Custom fields</div>
            <button type="button" class="btn btn-link p-0" id="btnAddField">Add field</button>
          </div>

          <div id="fieldsWrap"></div>

          <template id="fieldRowTpl">
            <div class="border rounded p-3 mb-3 bg-white field-row" data-field-row>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" data-f-active checked>
                  <label class="form-check-label">Active</label>
                </div>
                <button type="button" class="btn btn-sm btn-danger" data-f-remove>&times;</button>
              </div>

              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label mb-1">Name</label>
                  <input type="text" class="form-control" data-f-name placeholder="Name">
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Field type</label>
                  <select class="form-select" data-f-type>
                    <option value="text" selected>Text</option>
                    <option value="number">Number</option>
                    <option value="email">Email</option>
                    <option value="password">Password</option>
                    <option value="textarea">Textarea</option>
                    <option value="select">Select</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Input name (API Param)</label>
                  <input type="text" class="form-control" data-f-input placeholder="e.g. username / password / serial">
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Description</label>
                  <input type="text" class="form-control" data-f-desc placeholder="Description">
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Minimum</label>
                  <input type="number" class="form-control" data-f-min value="0">
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Maximum</label>
                  <input type="number" class="form-control" data-f-max value="0">
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Validation</label>
                  <select class="form-select" data-f-validation>
                    <option value="" selected>None</option>
                    <option value="numeric">Numeric</option>
                    <option value="email">Email</option>
                    <option value="alnum">AlphaNumeric</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label mb-1">Required</label>
                  <select class="form-select" data-f-required>
                    <option value="0" selected>No</option>
                    <option value="1">Yes</option>
                  </select>
                </div>

                <div class="col-12 d-none" data-f-options-wrap>
                  <label class="form-label mb-1">Options (for Select) - one per line</label>
                  <textarea class="form-control" rows="3" data-f-options placeholder="Option 1&#10;Option 2"></textarea>
                </div>

              </div>
            </div>
          </template>

          <small class="text-muted">
            هذه الحقول سيتم حفظها وربطها بالخدمة لاستخدامها لاحقًا عند إرسال الطلبات (خصوصًا server).
          </small>
        </div>

        {{-- RIGHT: Groups pricing --}}
        <div class="col-lg-5">
          <div class="fw-bold mb-2">Groups</div>
          <div id="groupsPricingWrap" class="border rounded p-3 bg-white">
            <div class="text-muted small">Groups pricing table will be generated here.</div>
          </div>
          <small class="text-muted d-block mt-2">
            يتم توليد الجدول تلقائياً من User Groups (Basic / VIP / Reseller ...).
          </small>
        </div>
      </div>

      <script>
      (function(){
        // ===== Custom Fields UI inside modal create (works with hidden JSON) =====
        const wrap   = document.getElementById('fieldsWrap');
        const tpl    = document.getElementById('fieldRowTpl');
        const hidden = document.getElementById('customFieldsHidden');

        if(!wrap || !tpl || !hidden) return;

        const readAll = () => {
          const rows = [];
          wrap.querySelectorAll('[data-field-row]').forEach((row, idx) => {
            const type = row.querySelector('[data-f-type]')?.value || 'text';
            const optionsText = row.querySelector('[data-f-options]')?.value || '';
            const optionsArr = optionsText.split('\n').map(x => x.trim()).filter(Boolean);

            rows.push({
              ordering: idx + 1,
              active: row.querySelector('[data-f-active]')?.checked ? 1 : 0,
              name: row.querySelector('[data-f-name]')?.value || '',
              input: row.querySelector('[data-f-input]')?.value || '',
              description: row.querySelector('[data-f-desc]')?.value || '',
              type,
              minimum: Number(row.querySelector('[data-f-min]')?.value || 0),
              maximum: Number(row.querySelector('[data-f-max]')?.value || 0),
              validation: row.querySelector('[data-f-validation]')?.value || null,
              required: Number(row.querySelector('[data-f-required]')?.value || 0),
              options: (type === 'select') ? optionsArr : []
            });
          });
          hidden.value = JSON.stringify(rows);
        };

        const bindRow = (row) => {
          const typeSel = row.querySelector('[data-f-type]');
          const optWrap = row.querySelector('[data-f-options-wrap]');

          const syncType = () => {
            const t = typeSel.value;
            if(optWrap) optWrap.classList.toggle('d-none', t !== 'select');
            readAll();
          };

          row.querySelectorAll('input,select,textarea').forEach(el => {
            el.addEventListener('input', readAll);
            el.addEventListener('change', readAll);
          });

          typeSel?.addEventListener('change', syncType);
          row.querySelector('[data-f-remove]')?.addEventListener('click', () => {
            row.remove();
            readAll();
          });

          syncType();
        };

        document.getElementById('btnAddField')?.addEventListener('click', () => {
          const node = document.createElement('div');
          node.innerHTML = tpl.innerHTML.trim();
          const row = node.firstElementChild;
          wrap.appendChild(row);
          bindRow(row);
          readAll();
        });

        // init
        readAll();
      })();
      </script>

    </div>

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
