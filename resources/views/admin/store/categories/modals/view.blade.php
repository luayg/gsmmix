<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">{{ $category->name }} | View</h5>
  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <table class="table table-bordered mb-0">
    <tr>
      <th style="width:180px">Name</th>
      <td>{{ $category->name }}</td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{{ $category->active ? 'Active' : 'Inactive' }}</td>
    </tr>
    <tr>
      <th>Ordering</th>
      <td>{{ $category->ordering }}</td>
    </tr>
    <tr>
      <th>Products</th>
      <td>{{ $category->products_count }}</td>
    </tr>
  </table>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" disabled>Activity logs</button>
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
</div>