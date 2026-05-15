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

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.products.store') }}">
  @csrf

  <style>
    .store-product-tabs .nav-link{color:#fff;border-radius:0;padding:.35rem .75rem;font-size:.82rem}
    .store-product-tabs .nav-link.active{background:rgba(255,255,255,.22);color:#fff}
    .store-product-toggle{display:flex;align-items:center;gap:.5rem;margin:.55rem 0}
    .store-product-toggle .form-check-input{margin-top:0}
    .store-product-info-editor .note-editor{margin-bottom:0}
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

            <div class="mb-2">
              <label class="form-label small mb-1">Source</label>
              <select name="local_source_id" class="form-select form-select-sm">
                <option value="">Manual</option>
                @foreach($sources as $source)
                  <option value="{{ $source->id }}">{{ $source->name }}</option>
                @endforeach
              </select>
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
              <input type="text" name="main_image" id="mainImageCreate" class="form-control form-control-sm" placeholder="Image URL or path">
            </div>

            <div class="mb-3">
              <button type="button" class="btn btn-light btn-sm border" id="selectMainImageCreate">Select image</button>
            </div>

            <div class="store-product-info-editor">
              <label class="form-label small mb-1">Info</label>
              <textarea id="infoEditor"
                        data-editor="summernote"
                        data-summernote="1"
                        data-summernote-height="260"
                        data-upload-url="{{ route('admin.uploads.summernote') }}"
                        class="form-control"></textarea>
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
  const imageInput = document.getElementById('mainImageCreate');
  document.getElementById('selectMainImageCreate')?.addEventListener('click', function(){
    const current = imageInput?.value || '';
    const value = window.prompt('Image URL or path', current);
    if (value !== null && imageInput) {
      imageInput.value = value;
    }
  });

  if (window.initModalEditors) {
    window.initModalEditors(document.currentScript.closest('.modal-content') || document);
  }
})();
</script>