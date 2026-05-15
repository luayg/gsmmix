@extends('layouts.admin')

@section('title', 'Product categories')

@section('content')
<div class="card">
  <div class="card-header bg-primary text-white">
    <i class="fas fa-tags me-1"></i> Product categories
  </div>

  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
      <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('admin.store.categories.index') }}" class="d-flex align-items-center gap-2">
          <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected((int)$perPage === $n)>Show {{ $n }} items</option>
            @endforeach
          </select>
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="status" value="{{ $status }}">
        </form>

        <button type="button"
                class="btn btn-success btn-sm js-open-modal"
                data-url="{{ route('admin.store.categories.modal.create') }}">
          Create category
        </button>
      </div>

      <form method="GET" action="{{ route('admin.store.categories.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:140px">
          <option value="">All status</option>
          <option value="active" @selected($status === 'active')>Active</option>
          <option value="inactive" @selected($status === 'inactive')>Inactive</option>
        </select>
        <label class="small mb-0">Search:</label>
        <input type="text" name="q" class="form-control form-control-sm" value="{{ $q }}" style="width:220px">
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Name</th>
            <th style="width:110px">Status</th>
            <th style="width:100px">Ordering</th>
            <th style="width:120px" class="text-center">Products</th>
            <th style="width:220px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $category)
            <tr>
              <td>{{ $category->id }}</td>
              <td>{{ $category->name }}</td>
              <td>
                @if($category->active)
                  <span class="badge bg-success">Active</span>
                @else
                  <span class="badge bg-danger">Inactive</span>
                @endif
              </td>
              <td>{{ $category->ordering }}</td>
              <td class="text-center">{{ $category->products_count }}</td>
              <td class="text-end text-nowrap">
                <button type="button" class="btn btn-primary btn-sm js-open-modal" data-url="{{ route('admin.store.categories.modal.view', $category) }}">View</button>
                <button type="button" class="btn btn-warning btn-sm js-open-modal" data-url="{{ route('admin.store.categories.modal.edit', $category) }}">Edit</button>
                <button type="button" class="btn btn-danger btn-sm js-open-modal" data-url="{{ route('admin.store.categories.modal.delete', $category) }}">Delete</button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No categories found</td>
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
