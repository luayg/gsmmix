@extends('layouts.admin')

@section('title', 'Products')

@section('content')
<div class="card">
  <div class="card-header bg-primary text-white">
    <i class="fas fa-boxes me-1"></i> Products
  </div>

  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
      <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('admin.store.products.index') }}" class="d-flex align-items-center gap-2">
          <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected((int)$perPage === $n)>Show {{ $n }} items</option>
            @endforeach
          </select>
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="category_id" value="{{ $categoryId ?: '' }}">
          <input type="hidden" name="source_id" value="{{ $sourceId ?: '' }}">
          <input type="hidden" name="status" value="{{ $status }}">
        </form>

        <button type="button"
                class="btn btn-success btn-sm js-open-modal"
                data-url="{{ route('admin.store.products.modal.create') }}">
          Create product
        </button>
      </div>

      <form method="GET" action="{{ route('admin.store.products.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <input type="hidden" name="category_id" value="{{ $categoryId ?: '' }}">
        <input type="hidden" name="source_id" value="{{ $sourceId ?: '' }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <label class="small mb-0">Search:</label>
        <input type="text" name="q" class="form-control form-control-sm" value="{{ $q }}" style="width:220px">
      </form>
    </div>

    <form method="GET" action="{{ route('admin.store.products.index') }}" class="row g-2 align-items-end mb-3">
      <input type="hidden" name="per_page" value="{{ $perPage }}">
      <input type="hidden" name="q" value="{{ $q }}">

      <div class="col-md-3">
        <label class="form-label small">Category</label>
        <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All categories</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((int)$categoryId === (int)$category->id)>{{ $category->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small">Source</label>
        <select name="source_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All sources</option>
          @foreach($sources as $source)
            <option value="{{ $source->id }}" @selected((int)$sourceId === (int)$source->id)>{{ $source->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small">Status</label>
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="active" @selected($status === 'active')>Active</option>
          <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
      </div>

      <div class="col-md-2">
        <a href="{{ route('admin.store.products.index', ['per_page' => $perPage]) }}" class="btn btn-light btn-sm w-100">Reset</a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Name</th>
            <th style="width:180px">Category</th>
            <th style="width:220px">Source</th>
            <th style="width:110px" class="text-end">Price</th>
            <th style="width:90px">Status</th>
            <th style="width:120px">Device based</th>
            <th style="width:210px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $product)
            <tr>
              <td>{{ $product->id }}</td>
              <td>
                <div class="fw-semibold">{{ $product->name }}</div>
                <div class="small text-muted">{{ $product->alias }}</div>
              </td>
              <td>{{ $product->category?->name ?? 'None' }}</td>
              <td>{{ $product->localSource?->name ?? 'Manual' }}</td>
              <td class="text-end">{{ number_format((float)$product->price, 2) }}</td>
              <td>
                @if($product->active)
                  <span class="badge bg-success">Active</span>
                @else
                  <span class="badge bg-danger">Inactive</span>
                @endif
              </td>
              <td>{{ $product->device_based ? 'Yes' : 'No' }}</td>
              <td class="text-end text-nowrap">
                <button type="button" class="btn btn-primary btn-sm js-open-modal" data-url="{{ route('admin.store.products.modal.view', $product) }}">View</button>
                <button type="button" class="btn btn-warning btn-sm js-open-modal" data-url="{{ route('admin.store.products.modal.edit', $product) }}">Edit</button>
                <button type="button" class="btn btn-danger btn-sm js-open-modal" data-url="{{ route('admin.store.products.modal.delete', $product) }}">Delete</button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-4">No products found</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
      <div class="small text-muted">
        Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} items
      </div>
      {{ $rows->links('admin.components.pagination.compact') }}
    </div>
  </div>
</div>
@endsection