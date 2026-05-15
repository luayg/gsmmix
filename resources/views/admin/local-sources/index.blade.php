@extends('layouts.admin')

@section('title', 'Sources')

@section('content')
<div class="card">
  <div class="card-header bg-primary text-white">
    <i class="fas fa-database me-1"></i> Sources
  </div>

  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
      <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('admin.sources.index') }}" class="d-flex align-items-center gap-2">
          <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected((int)$perPage === $n)>Show {{ $n }} items</option>
            @endforeach
          </select>
          <input type="hidden" name="q" value="{{ $q }}">
        </form>

        <button type="button"
                class="btn btn-success btn-sm js-open-modal"
                data-url="{{ route('admin.sources.modal.create') }}">
          Create source
        </button>
      </div>

      <form method="GET" action="{{ route('admin.sources.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
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
            <th style="width:120px" class="text-center">Replies</th>
            <th style="width:210px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $source)
            <tr>
              <td>{{ $source->id }}</td>
              <td>{{ $source->name }}</td>
              <td class="text-center">{{ $source->replies_count }}</td>
              <td class="text-end text-nowrap">
                <button type="button"
                        class="btn btn-primary btn-sm js-open-modal"
                        data-url="{{ route('admin.sources.modal.view', $source) }}">
                  View
                </button>
                <button type="button"
                        class="btn btn-warning btn-sm js-open-modal"
                        data-url="{{ route('admin.sources.modal.edit', $source) }}">
                  Edit
                </button>
                <button type="button"
                        class="btn btn-danger btn-sm js-open-modal"
                        data-url="{{ route('admin.sources.modal.delete', $source) }}">
                  Delete
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">No sources found</td>
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