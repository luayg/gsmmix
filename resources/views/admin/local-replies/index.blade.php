@extends('layouts.admin')

@section('title', 'Replies')

@section('content')
<div class="card">
  <div class="card-header bg-primary text-white">
    <i class="fas fa-reply me-1"></i> Replies
  </div>

  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
      <div class="d-flex align-items-center gap-2">
        <form method="GET" action="{{ route('admin.replies.index') }}" class="d-flex align-items-center gap-2">
          <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach([10,25,50,100] as $n)
              <option value="{{ $n }}" @selected((int)$perPage === $n)>Show {{ $n }} items</option>
            @endforeach
          </select>
          <input type="hidden" name="q" value="{{ $q }}">
          <input type="hidden" name="source_id" value="{{ $sourceId ?: '' }}">
          <input type="hidden" name="usage" value="{{ $usage }}">
        </form>

        <button type="button"
                class="btn btn-success btn-sm js-open-modal"
                data-url="{{ route('admin.replies.modal.create') }}">
          Create reply
        </button>
      </div>

      <form method="GET" action="{{ route('admin.replies.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="per_page" value="{{ $perPage }}">
        <label class="small mb-0">Search:</label>
        <input type="text" name="q" class="form-control form-control-sm" value="{{ $q }}" style="width:220px">
      </form>
    </div>

    <form method="GET" action="{{ route('admin.replies.index') }}" class="row g-2 align-items-end mb-3">
      <input type="hidden" name="per_page" value="{{ $perPage }}">
      <input type="hidden" name="q" value="{{ $q }}">

      <div class="col-md-4">
        <label class="form-label small">Source</label>
        <select name="source_id" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All sources</option>
          @foreach($sources as $source)
            <option value="{{ $source->id }}" @selected((int)$sourceId === (int)$source->id)>{{ $source->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small">Usage</label>
        <select name="usage" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="unused" @selected($usage === 'unused')>None</option>
          <option value="used" @selected($usage === 'used')>Used</option>
        </select>
      </div>

      <div class="col-md-2">
        <a href="{{ route('admin.replies.index', ['per_page' => $perPage]) }}" class="btn btn-light btn-sm w-100">Reset</a>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th style="min-width:220px">Source</th>
            <th style="width:180px">Device</th>
            <th style="width:190px">Usage</th>
            <th style="width:160px">Expiration</th>
            <th style="width:100px" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $reply)
            <tr>
              <td>{{ $reply->id }}</td>
              <td>{{ $reply->source?->name ?? 'None' }}</td>
              <td>{{ $reply->device_identifier ?: '-' }}</td>
              <td>
                @if($reply->used_by_product_order_id)
                  <a href="{{ route('admin.orders.product.index') }}"
                     class="btn btn-primary btn-sm">
                    Product order: {{ $reply->used_by_product_order_id }}
                  </a>
                @else
                  <span class="text-muted">None</span>
                @endif
              </td>
              <td>{{ $reply->expires_at ? $reply->expires_at->format('d/m/Y H:i:s') : 'None' }}</td>
              <td class="text-end text-nowrap">
                <button type="button"
                        class="btn btn-primary btn-sm js-open-modal"
                        data-url="{{ route('admin.replies.modal.view', $reply) }}">
                  View
                </button>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No replies found</td>
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
