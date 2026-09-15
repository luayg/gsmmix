<div class="modal-header bg-warning text-white">
  <h5 class="modal-title">{{ $product->name }} | Edit</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form class="js-ajax-form" method="POST" action="{{ route('admin.store.products.update', $product) }}" enctype="multipart/form-data">
  @csrf
  @method('PUT')
  <input type="hidden" name="description" id="infoHidden" value="{{ $product->description }}">

  <div class="modal-body">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Source</label>
        <select name="source_type" id="productSourceTypeEdit" class="form-select" required>
          <option value="manual" @selected(($product->source_type ?? 'manual') === 'manual')>Manual</option>
          <option value="service" @selected($product->source_type === 'service')>Service</option>
          <option value="local_source" @selected($product->source_type === 'local_source')>Local source</option>
        </select>
      </div>

      <div class="col-md-6 product-service-field-edit @if($product->source_type !== 'service') d-none @endif">
        <label class="form-label">Service type</label>
        <select name="service_type" id="productServiceTypeEdit" class="form-select" @disabled($product->source_type !== 'service')>
          @foreach(['imei' => 'IMEI', 'server' => 'Server', 'file' => 'File', 'smm' => 'SMM'] as $value => $label)
            <option value="{{ $value }}" @selected($product->service_type === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6 product-service-field-edit @if($product->source_type !== 'service') d-none @endif">
        <label class="form-label">Linked service</label>
        <select name="service_id" id="productServiceIdEdit" class="form-select" @disabled($product->source_type !== 'service')>
          @foreach($serviceOptions as $type => $services)
            @foreach($services as $service)
              <option value="{{ $service['id'] }}" data-service-type="{{ $type }}" data-service-cost="{{ $service['cost'] }}"
                @selected($product->service_type === $type && (int)$product->service_id === (int)$service['id'])
                @if($product->service_type !== $type) hidden disabled @endif>
                {{ $service['name'] ?: ('#' . $service['id']) }}{{ $service['active'] ? '' : ' (inactive)' }}
              </option>
            @endforeach
          @endforeach
        </select>
      </div>

      <div class="col-md-6 @if($product->source_type !== 'local_source') d-none @endif" id="productLocalSourceFieldEdit">
        <label class="form-label">Category</label>
        <select name="product_category_id" class="form-select">
          <option value="">None</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((int)$product->product_category_id === (int)$category->id)>{{ $category->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Local source</label>
        <select name="local_source_id" id="productLocalSourceEdit" class="form-select" @disabled($product->source_type !== 'local_source')>
          <option value="">Choose local source</option>
          @foreach($sources as $source)
            <option value="{{ $source->id }}" @selected((int)$product->local_source_id === (int)$source->id)>{{ $source->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ $product->name }}" required>
      </div>

      <div class="col-12">
        <label class="form-label">Alias</label>
        <input type="text" name="alias" class="form-control" value="{{ $product->alias }}">
      </div>

      <div class="col-12">
        <label class="form-label">Main image</label>
        <input type="hidden" name="main_image" value="{{ $product->main_image }}">
        <input type="file" name="main_image_file" id="mainImageFileEdit" class="d-none" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="border rounded bg-light p-3 text-center">
          <img id="mainImagePreviewEdit" src="{{ $product->main_image }}"
               class="img-fluid rounded @if(!$product->main_image) d-none @endif mb-2"
               style="max-height:160px" alt="Product image preview">
          <div id="mainImageNameEdit" class="small text-muted mb-2">{{ $product->main_image ? 'Current image' : 'No image selected' }}</div>
          <button type="button" class="btn btn-outline-success btn-sm" id="selectMainImageEdit">
            <i class="fas fa-upload me-1"></i> Choose image from computer
          </button>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Cost</label>
        <input type="number" step="0.01" min="0" name="cost" class="form-control" value="{{ $product->cost }}">
      </div>

      <div class="col-md-6">
        <label class="form-label">Price</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ $product->price }}" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Profit</label>
        <div class="input-group">
          <input type="number" step="0.01" min="0" name="profit" class="form-control" value="{{ $product->profit }}">
          <select name="profit_type" class="form-select" style="max-width:120px">
            <option value="credits" @selected(($product->profit_type ?? 'credits') === 'credits')>Credits</option>
            <option value="percent" @selected($product->profit_type === 'percent')>Percent</option>
          </select>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Ordering</label>
        <input type="number" min="0" name="ordering" class="form-control" value="{{ $product->ordering }}">
      </div>

      <div class="col-md-6 d-flex align-items-end gap-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="active" value="1" id="productActiveEdit" @checked($product->active)>
          <label class="form-check-label" for="productActiveEdit">Active</label>
        </div>

        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="device_based" value="1" id="productDeviceBasedEdit" @checked($product->device_based)>
          <label class="form-check-label" for="productDeviceBasedEdit">Device based</label>
        </div>
      </div>

      <div class="col-12">
        <label class="form-label">Info</label>
        <textarea id="infoEditor"
                  class="form-control summernote"
                  data-editor="summernote"
                  data-summernote="1"
                  data-summernote-hidden="#infoHidden"
                  data-summernote-height="260"
                  data-upload-url="{{ route('admin.uploads.summernote') }}">{{ $product->description }}</textarea>
      </div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
    <button type="submit" class="btn btn-success">Save</button>
  </div>
</form>

<script>
(function(){
  const imageInput = document.getElementById('mainImageFileEdit');
  const imagePreview = document.getElementById('mainImagePreviewEdit');
  const imageName = document.getElementById('mainImageNameEdit');
  document.getElementById('selectMainImageEdit')?.addEventListener('click', function(){ imageInput?.click(); });
  imageInput?.addEventListener('change', function(){
    const file = this.files?.[0];
    if (!file) return;
    imageName.textContent = file.name;
    imagePreview.src = URL.createObjectURL(file);
    imagePreview.classList.remove('d-none');
  });

  const typeSelect = document.getElementById('productServiceTypeEdit');
  const serviceSelect = document.getElementById('productServiceIdEdit');
  const sourceSelect = document.getElementById('productSourceTypeEdit');
  const serviceFields = document.querySelectorAll('.product-service-field-edit');
  const localField = document.getElementById('productLocalSourceFieldEdit');
  const localSelect = document.getElementById('productLocalSourceEdit');
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
  };
  const applyServiceCost = function(){
    const option = serviceSelect?.selectedOptions?.[0];
    if (!option?.dataset.serviceCost) return;
    if (costInput) costInput.value = (Number.parseFloat(option.dataset.serviceCost) || 0).toFixed(2);
    calculatePrice();
  };
  sourceSelect?.addEventListener('change', function(){
    const isService = this.value === 'service';
    const isLocal = this.value === 'local_source';
    serviceFields.forEach(function(field){ field.classList.toggle('d-none', !isService); });
    localField.classList.toggle('d-none', !isLocal);
    typeSelect.disabled = !isService;
    serviceSelect.disabled = !isService;
    localSelect.disabled = !isLocal;
    if (!isService) { typeSelect.value = ''; serviceSelect.value = ''; }
    if (!isLocal) localSelect.value = '';
  });
  typeSelect?.addEventListener('change', function(){
    const type = this.value;
    serviceSelect.value = '';
    serviceSelect.querySelectorAll('option[data-service-type]').forEach(function(option){
      option.hidden = option.dataset.serviceType !== type;
      option.disabled = option.hidden;
    });
  });
  serviceSelect?.addEventListener('change', applyServiceCost);
  profitInput?.addEventListener('input', calculatePrice);
  profitType?.addEventListener('change', calculatePrice);
})();
</script>
