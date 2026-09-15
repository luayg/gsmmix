<div class="modal-header bg-success text-white align-items-center">
  <h5 class="modal-title me-auto">Create product</h5>
  <ul class="nav nav-pills store-product-tabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#productGeneralCreate" type="button" role="tab">General</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#productAdditionalCreate" type="button" role="tab">Additional</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#productMetaCreate" type="button" role="tab">Meta</button>
    </li>
  </ul>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.products.store') }}" enctype="multipart/form-data">
  @csrf

  <style>
    .store-product-tabs .nav-link{color:#fff;border-radius:0;padding:.35rem .75rem;font-size:.82rem}
    .store-product-tabs .nav-link.active{background:rgba(255,255,255,.22);color:#fff}
    .store-product-toggle{display:flex;align-items:center;gap:.5rem;margin:.55rem 0}
    .store-product-toggle .form-check-input{margin-top:0}
    .store-product-info-editor .note-editor{margin-bottom:0}
    .product-pricing-title{background:#f1f1f1;padding:.45rem .65rem;font-weight:600;margin-bottom:.5rem}
    .product-pricing-row{border-bottom:1px solid #e5e7eb;padding-bottom:.75rem;margin-bottom:.75rem}
  </style>

  <input type="hidden" name="description" id="infoHidden" value="">

  <div class="modal-body p-0">
    <div class="tab-content">
      <div class="tab-pane fade show active p-3" id="productGeneralCreate" role="tabpanel">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="mb-2">
              <label class="form-label small mb-1">Name</label>
              <input type="text" name="name" class="form-control form-control-sm" placeholder="Name" required>
            </div>

            <div class="mb-2">
              <label class="form-label small mb-1">Alias <span class="text-muted">(Unique name containing only latin lowercase characters and dashes)</span></label>
              <input type="text" name="alias" class="form-control form-control-sm" placeholder="Alias">
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label small mb-1">Delivery time</label>
                <input type="text" name="delivery_time" class="form-control form-control-sm" placeholder="Delivery time">
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Category</label>
                <select name="product_category_id" class="form-select form-select-sm">
                  <option value="">Category</option>
                  @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label small mb-1">Source</label>
              <select name="source_type" id="productSourceTypeCreate" class="form-select form-select-sm" required>
                <option value="manual" selected>Manual</option>
                <option value="service">Service</option>
                <option value="local_source">Local source</option>
              </select>
            </div>

            <div class="row g-2 mb-2 d-none" id="productServiceFieldsCreate">
              <div class="col-md-5">
                <label class="form-label small mb-1">Service type</label>
                <select name="service_type" id="productServiceTypeCreate" class="form-select form-select-sm" disabled>
                  <option value="">Choose type</option>
                  @foreach(['imei' => 'IMEI', 'server' => 'Server', 'file' => 'File', 'smm' => 'SMM'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-7">
                <label class="form-label small mb-1">Linked service</label>
                <select name="service_id" id="productServiceIdCreate" class="form-select form-select-sm" disabled>
                  <option value="">Choose service</option>
                  @foreach($serviceOptions as $type => $services)
                    @foreach($services as $service)
                      <option value="{{ $service['id'] }}" data-service-type="{{ $type }}" data-service-cost="{{ $service['cost'] }}" hidden>
                        {{ $service['name'] ?: ('#' . $service['id']) }}{{ $service['active'] ? '' : ' (inactive)' }}
                      </option>
                    @endforeach
                  @endforeach
                </select>
              </div>
            </div>

            <div class="mb-2 d-none" id="productLocalSourceFieldCreate">
              <label class="form-label small mb-1">Local source</label>
              <select name="local_source_id" id="productLocalSourceCreate" class="form-select form-select-sm" disabled>
                <option value="">Choose local source</option>
                @foreach($sources as $source)
                  <option value="{{ $source->id }}">{{ $source->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label small mb-1">Price</label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.01" min="0" name="price" class="form-control" value="0.00" required>
                  <span class="input-group-text">Credits</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Converted price</label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.01" min="0" name="converted_price" class="form-control" value="0.00">
                  <select name="currency" class="form-select" style="max-width:90px">
                    <option value="USD" selected>USD</option>
                    <option value="EUR">EUR</option>
                    <option value="SAR">SAR</option>
                    <option value="AED">AED</option>
                    <option value="JOD">JOD</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-6">
                <label class="form-label small mb-1">Cost</label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.01" min="0" name="cost" class="form-control" value="0.00">
                  <span class="input-group-text">Credits</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label small mb-1">Profit</label>
                <div class="input-group input-group-sm">
                  <input type="number" step="0.01" min="0" name="profit" class="form-control" value="0.00">
                  <select name="profit_type" class="form-select" style="max-width:105px">
                    <option value="credits" selected>Credits</option>
                    <option value="percent">Percent</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="store-product-toggle">
              <input class="form-check-input" type="checkbox" name="active" value="1" id="productActiveCreate" checked>
              <label class="form-check-label" for="productActiveCreate">Active</label>
            </div>
            <div class="store-product-toggle">
              <input class="form-check-input" type="checkbox" name="unlimited" value="1" id="productUnlimitedCreate" checked>
              <label class="form-check-label" for="productUnlimitedCreate">Unlimited</label>
            </div>
            <div class="store-product-toggle">
              <input class="form-check-input" type="checkbox" name="hot" value="1" id="productHotCreate">
              <label class="form-check-label" for="productHotCreate">Hot</label>
            </div>
            <div class="store-product-toggle">
              <input class="form-check-input" type="checkbox" name="new" value="1" id="productNewCreate">
              <label class="form-check-label" for="productNewCreate">New</label>
            </div>
            <div class="store-product-toggle">
              <input class="form-check-input" type="checkbox" name="sale" value="1" id="productSaleCreate">
              <label class="form-check-label" for="productSaleCreate">Sale</label>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="mb-2">
              <label class="form-label small mb-1">Main Image</label>
              <input type="hidden" name="main_image" value="">
              <input type="file" name="main_image_file" id="mainImageFileCreate" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif">
              <div class="border rounded bg-light p-3 text-center">
                <img id="mainImagePreviewCreate" class="img-fluid rounded d-none mb-2" style="max-height:160px" alt="Product image preview">
                <div id="mainImageNameCreate" class="small text-muted mb-2">No image selected</div>
                <button type="button" class="btn btn-outline-success btn-sm" id="selectMainImageCreate">
                  <i class="fas fa-upload me-1"></i> Choose image from computer
                </button>
              </div>
            </div>

            <div class="store-product-info-editor">
              <label class="form-label small mb-1">Info</label>
              <textarea id="infoEditor"
                        class="form-control summernote"
                        data-editor="summernote"
                        data-summernote="1"
                        data-summernote-hidden="#infoHidden"
                        data-summernote-height="260"
                        data-upload-url="{{ route('admin.uploads.summernote') }}"
                        ></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade p-3" id="productAdditionalCreate" role="tabpanel">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Ordering</label>
            <input type="number" min="0" name="ordering" class="form-control" value="0">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="device_based" value="1" id="productDeviceBasedCreate">
              <label class="form-check-label" for="productDeviceBasedCreate">Device based product</label>
            </div>
          </div>
          <div class="col-12"><hr class="my-1"></div>
          <div class="col-12">
            <h6 class="mb-2">Groups</h6>
            <div id="productGroupsPricingCreate">
              @forelse($customerGroups as $group)
                <div class="product-pricing-row" data-product-group-price>
                  <div class="product-pricing-title">{{ $group->name }}</div>
                  <div class="row g-2">
                    <div class="col-md-6">
                      <label class="form-label small">Price <span class="text-muted" data-price-mode-label>Automatic</span></label>
                      <input type="hidden" name="group_prices[{{ $group->id }}][auto_price]" value="1" data-auto-price-input>
                      <div class="input-group input-group-sm">
                        <input type="number" step="0.0001" min="0" name="group_prices[{{ $group->id }}][price]" value="0.0000" class="form-control" data-group-price data-auto-price="1">
                        <span class="input-group-text">Credits</span>
                      </div>
                      <div class="small text-muted mt-1">Final: <span data-group-final>0.0000</span> Credits</div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small">Discount</label>
                      <div class="input-group input-group-sm">
                        <input type="number" step="0.0001" min="0" name="group_prices[{{ $group->id }}][discount]" value="0.0000" class="form-control" data-group-discount>
                        <select name="group_prices[{{ $group->id }}][discount_type]" class="form-select" data-group-discount-type>
                          <option value="1">Credits</option><option value="2">Percent</option>
                        </select>
                        <button type="button" class="btn btn-light" data-group-reset>Reset</button>
                      </div>
                    </div>
                  </div>
                </div>
              @empty
                <div class="text-muted">No customer groups found.</div>
              @endforelse
            </div>
            <div class="form-text">Set special prices or discounts per customer group for this product.</div>
          </div>
        </div>
      </div>

      <div class="tab-pane fade p-3" id="productMetaCreate" role="tabpanel">
        <div class="mb-3">
          <label class="form-label">Meta title</label>
          <input type="text" name="meta_title" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Meta keywords</label>
          <textarea name="meta_keywords" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-0">
          <label class="form-label">Meta description</label>
          <textarea name="meta_description" class="form-control" rows="4"></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Create</button>
  </div>
</form>

<script>
(function(){
  const imageInput = document.getElementById('mainImageFileCreate');
  const imagePreview = document.getElementById('mainImagePreviewCreate');
  const imageName = document.getElementById('mainImageNameCreate');
  document.getElementById('selectMainImageCreate')?.addEventListener('click', function(){
    imageInput?.click();
  });
  imageInput?.addEventListener('change', function(){
    const file = this.files?.[0];
    if (!file) return;
    imageName.textContent = file.name;
    imagePreview.src = URL.createObjectURL(file);
    imagePreview.classList.remove('d-none');
  });

  const typeSelect = document.getElementById('productServiceTypeCreate');
  const serviceSelect = document.getElementById('productServiceIdCreate');
  const sourceSelect = document.getElementById('productSourceTypeCreate');
  const serviceFields = document.getElementById('productServiceFieldsCreate');
  const localField = document.getElementById('productLocalSourceFieldCreate');
  const localSelect = document.getElementById('productLocalSourceCreate');
  const form = sourceSelect?.closest('form');
  const costInput = form?.querySelector('[name="cost"]');
  const priceInput = form?.querySelector('[name="price"]');
  const profitInput = form?.querySelector('[name="profit"]');
  const profitType = form?.querySelector('[name="profit_type"]');
  const calculatePrice = function(){
    if (sourceSelect?.value !== 'service') return;
    const cost = Number.parseFloat(costInput?.value || '0') || 0;
    const profit = Number.parseFloat(profitInput?.value || '0') || 0;
    const price = profitType?.value === 'percent' ? cost + (cost * profit / 100) : cost + profit;
    if (priceInput) priceInput.value = price.toFixed(2);
    syncGroupPrices();
  };
  const groupRows = Array.from(form?.querySelectorAll('[data-product-group-price]') || []);
  const refreshGroup = function(row){
    const price = Number.parseFloat(row.querySelector('[data-group-price]')?.value || '0') || 0;
    const discount = Number.parseFloat(row.querySelector('[data-group-discount]')?.value || '0') || 0;
    const percent = row.querySelector('[data-group-discount-type]')?.value === '2';
    const finalPrice = Math.max(0, price - (percent ? price * discount / 100 : discount));
    const output = row.querySelector('[data-group-final]');
    if (output) output.textContent = finalPrice.toFixed(4);
  };
  const syncGroupPrices = function(){
    const price = Number.parseFloat(priceInput?.value || '0') || 0;
    groupRows.forEach(function(row){
      const input = row.querySelector('[data-group-price]');
      if (input?.dataset.autoPrice === '1') input.value = price.toFixed(4);
      refreshGroup(row);
    });
  };
  groupRows.forEach(function(row){
    const price = row.querySelector('[data-group-price]');
    const mode = row.querySelector('[data-auto-price-input]');
    const label = row.querySelector('[data-price-mode-label]');
    price?.addEventListener('input', function(){ this.dataset.autoPrice = '0'; mode.value = '0'; label.textContent = 'Manual'; refreshGroup(row); });
    row.querySelector('[data-group-discount]')?.addEventListener('input', function(){ refreshGroup(row); });
    row.querySelector('[data-group-discount-type]')?.addEventListener('change', function(){ refreshGroup(row); });
    row.querySelector('[data-group-reset]')?.addEventListener('click', function(){
      price.dataset.autoPrice = '1'; mode.value = '1'; label.textContent = 'Automatic';
      row.querySelector('[data-group-discount]').value = '0.0000';
      row.querySelector('[data-group-discount-type]').value = '1'; syncGroupPrices();
    });
  });
  const applyServiceCost = function(){
    const option = serviceSelect?.selectedOptions?.[0];
    if (!option?.dataset.serviceCost) return;
    if (costInput) costInput.value = (Number.parseFloat(option.dataset.serviceCost) || 0).toFixed(2);
    calculatePrice();
  };
  sourceSelect?.addEventListener('change', function(){
    const isService = this.value === 'service';
    const isLocal = this.value === 'local_source';
    serviceFields.classList.toggle('d-none', !isService);
    localField.classList.toggle('d-none', !isLocal);
    typeSelect.disabled = !isService;
    serviceSelect.disabled = !isService || !typeSelect.value;
    localSelect.disabled = !isLocal;
    if (!isService) { typeSelect.value = ''; serviceSelect.value = ''; }
    if (!isLocal) localSelect.value = '';
  });
  typeSelect?.addEventListener('change', function(){
    const type = this.value;
    serviceSelect.value = '';
    serviceSelect.disabled = !type;
    serviceSelect.querySelectorAll('option[data-service-type]').forEach(function(option){
      option.hidden = option.dataset.serviceType !== type;
      option.disabled = option.hidden;
    });
  });
  serviceSelect?.addEventListener('change', applyServiceCost);
  profitInput?.addEventListener('input', calculatePrice);
  profitType?.addEventListener('change', calculatePrice);
  priceInput?.addEventListener('input', syncGroupPrices);
  syncGroupPrices();
})();
</script>
