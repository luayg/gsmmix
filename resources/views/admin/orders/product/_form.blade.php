<form action="{{ route('admin.orders.product.store') }}" method="POST">
  @csrf
  <input type="hidden" name="request_uid" value="{{ old('request_uid', $requestUid) }}">
  @if($errors->any())
    <div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
  @endif
  <p class="text-muted">Each order purchases one item at its catalog price in credits. Local stock is delivered immediately; manual products remain waiting for delivery.</p>
  <div class="mb-3">
    <label class="form-label" for="productOrderUser">Customer</label>
    <select id="productOrderUser" class="form-select" name="user_id" required>
      <option value="">Choose customer</option>
      @foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('user_id') === (string)$user->id)>{{ $user->name }} — {{ $user->email }}</option>@endforeach
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="productOrderProduct">Product</label>
    <select id="productOrderProduct" class="form-select" name="product_id" required>
      <option value="">Choose product</option>
      @foreach($products as $product)
        <option value="{{ $product->id }}" @selected((string)old('product_id') === (string)$product->id)>{{ $product->name }} — {{ number_format($product->price, 2) }} credits — {{ $product->local_source_id ? 'Local stock' : 'Manual delivery' }}{{ $product->device_based ? ' — Device required' : '' }}</option>
      @endforeach
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="productOrderDevice">Device identifier (required for device products)</label>
    <input id="productOrderDevice" class="form-control" name="device" maxlength="255" value="{{ old('device') }}">
  </div>
  <div class="mb-3">
    <label class="form-label" for="productOrderComments">Comments</label>
    <textarea id="productOrderComments" class="form-control" name="comments" maxlength="5000">{{ old('comments') }}</textarea>
  </div>
  <button class="btn btn-primary" type="submit">Create and charge credits</button>
  @can('orders.view')<a class="btn btn-light" href="{{ route('admin.orders.product.index') }}">Back to orders</a>@endcan
</form>
