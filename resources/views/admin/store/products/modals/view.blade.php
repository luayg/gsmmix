<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">{{ $product->name }} | View</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <table class="table table-bordered mb-0">
    <tr>
      <th style="width:180px">Name</th>
      <td>{{ $product->name }}</td>
    </tr>
    <tr>
      <th>Alias</th>
      <td>{{ $product->alias }}</td>
    </tr>
    <tr>
      <th>Category</th>
      <td>{{ $product->category?->name ?? 'None' }}</td>
    </tr>
    <tr>
      <th>Local source</th>
      <td>{{ $product->localSource?->name ?? 'Manual' }}</td>
    </tr>
    <tr>
      <th>Cost</th>
      <td>{{ number_format((float)$product->cost, 2) }}</td>
    </tr>
    <tr>
      <th>Price</th>
      <td>{{ number_format((float)$product->price, 2) }}</td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{{ $product->active ? 'Active' : 'Inactive' }}</td>
    </tr>
    <tr>
      <th>Device based</th>
      <td>{{ $product->device_based ? 'Yes' : 'No' }}</td>
    </tr>
    <tr>
      <th>Orders</th>
      <td>{{ $product->orders_count }}</td>
    </tr>
    <tr>
      <th>Description</th>
      <td style="white-space:pre-wrap">{{ $product->description ?: 'None' }}</td>
    </tr>
  </table>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" disabled>Activity logs</button>
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
</div>