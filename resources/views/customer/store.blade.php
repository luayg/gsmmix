@extends(auth()->check() ? 'layouts.customer' : 'layouts.site')
@section('title', 'Products')
@section('content')
<section class="{{ auth()->check() ? '' : 'container py-5 mt-5' }}">
  <div class="page-heading"><div><span class="eyebrow">GSM MARKETPLACE</span><h1>Products & digital services</h1><p>Choose a product, enter the required details and pay instantly from your balance.</p></div></div>
  <div class="row g-4">
    @forelse($products as $product)
      @php
        $groupPrice = auth()->check() ? $product->groupPrices->first() : null;
        $price = $groupPrice?->finalPrice($product) ?? $product->price;
        $needsFile = $product->source_type === 'service' && $product->service_type === 'file';
      @endphp
      <div class="col-sm-6 col-xl-4"><article class="product-card h-100">
        @if($product->main_image)<img class="product-image" src="{{ asset($product->main_image) }}" alt="{{ $product->name }}">@else<div class="product-image product-placeholder"><i class="fas fa-toolbox"></i></div>@endif
        <div class="product-card-body">
          <div class="d-flex gap-2 mb-2">@if($product->hot)<span class="badge text-bg-danger">Hot</span>@endif @if($product->new)<span class="badge text-bg-primary">New</span>@endif @if($product->sale)<span class="badge text-bg-success">Sale</span>@endif</div>
          <h2 class="h5 fw-bold">{{ $product->name }}</h2><p class="text-muted small">{{ Str::limit(strip_tags($product->description),120) }}</p>
          <div class="product-meta"><div><small>PRICE</small><strong>${{ number_format($price,2) }}</strong></div><div><small>DELIVERY</small><strong>{{ $product->delivery_time ?: 'Fast delivery' }}</strong></div></div>
          @auth
            <button class="btn btn-gsm w-100 mt-3 js-buy-product" type="button" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $price }}" data-device="{{ $product->device_based ? 1 : 0 }}" data-file="{{ $needsFile ? 1 : 0 }}">Order now</button>
          @else<a class="btn btn-gsm w-100 mt-3" href="{{ route('login') }}">Login to order</a>@endauth
        </div>
      </article></div>
    @empty
      <div class="col-12"><div class="empty-state"><i class="fas fa-store"></i><h2>No products yet</h2></div></div>
    @endforelse
  </div><div class="mt-4">{{ $products->links() }}</div>
</section>
@auth
<div class="modal fade" id="productOrderModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content order-modal">
  <div class="modal-header"><div><span class="eyebrow">PRODUCT ORDER</span><h2 class="h5 mb-0" id="productOrderTitle"></h2></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <form id="productOrderForm" enctype="multipart/form-data"><div class="modal-body">@csrf
    <input type="hidden" name="product_id" id="productId"><input type="hidden" name="request_uid" id="productRequestUid">
    <div class="product-checkout-price"><span>Total from balance</span><strong id="productPrice"></strong></div>
    <div class="mb-3" id="productDeviceWrap"><label class="form-label fw-bold">Device / IMEI / target</label><input class="form-control" name="device" id="productDevice"></div>
    <div class="mb-3 d-none" id="productFileWrap"><label class="form-label fw-bold">Required file</label><input class="form-control" type="file" name="file" id="productFile"></div>
    <div><label class="form-label fw-bold">Comments (optional)</label><textarea class="form-control" name="comments" rows="3"></textarea></div><div class="alert alert-danger d-none mt-3" id="productOrderError"></div>
  </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gsm" id="productOrderSubmit">Confirm & pay</button></div></form>
</div></div></div>
@push('scripts')
<script>
const productModal=document.getElementById('productOrderModal'),productForm=document.getElementById('productOrderForm');
document.querySelectorAll('.js-buy-product').forEach(button=>button.addEventListener('click',()=>{
  document.getElementById('productId').value=button.dataset.id; document.getElementById('productRequestUid').value=crypto.randomUUID();
  document.getElementById('productOrderTitle').textContent=button.dataset.name; document.getElementById('productPrice').textContent='$'+Number(button.dataset.price).toFixed(2);
  const needsDevice=button.dataset.device==='1',needsFile=button.dataset.file==='1'; document.getElementById('productDevice').required=needsDevice; document.getElementById('productDeviceWrap').classList.toggle('d-none',!needsDevice);
  document.getElementById('productFile').required=needsFile; document.getElementById('productFileWrap').classList.toggle('d-none',!needsFile); document.getElementById('productOrderError').classList.add('d-none');
  window.bootstrap.Modal.getOrCreateInstance(productModal).show();
}));
productForm.addEventListener('submit',async event=>{event.preventDefault();const button=document.getElementById('productOrderSubmit'),error=document.getElementById('productOrderError');button.disabled=true;const response=await fetch(@json(route('customer.product-orders.store')),{method:'POST',body:new FormData(productForm),headers:{Accept:'application/json'}}),json=await response.json().catch(()=>({}));button.disabled=false;if(response.ok){location.href=@json(route('customer.orders.type','product'));return}error.textContent=json.message||Object.values(json.errors||{}).flat().join(' ');error.classList.remove('d-none');});
</script>
@endpush
@endauth
@endsection
