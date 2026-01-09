{{-- المتغيرات المطلوبة: $rows, $apis, $routePrefix, $viewPrefix --}}

<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center gap-2 flex-wrap">
    <div class="fw-semibold text-capitalize">{{ $viewPrefix }} services</div>

    <form class="ms-auto d-flex gap-2 align-items-center" method="GET" action="">
      <input class="form-control form-control-sm" name="q" placeholder="Smart search"
             value="{{ request('q') }}" style="max-width:240px">
      <select class="form-select form-select-sm" name="api_id" style="max-width:220px" onchange="this.form.submit()">
        <option value="">API connection</option>
        @foreach($apis as $a)
          <option value="{{ $a }}" @selected(request('api_id')==$a)>{{ $a }}</option>
        @endforeach
      </select>
    </form>

    <button type="button" class="btn btn-success btn-sm" data-create-service>
      Create service
    </button>
  </div>

  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead class="table-light">
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Status</th>
        <th>Group</th>
        <th class="text-end">Price</th>
        <th class="text-end">Cost</th>
        <th>Supplier</th>
        <th>API connection</th>
        <th class="text-end">Actions</th>
      </tr>
      </thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r->id }}</td>
          <td>{{ $r->name_json['fallback'] ?? \Illuminate\Support\Str::limit($r->name, 60) }}</td>
          <td>
            @if($r->active)
              <span class="badge text-bg-success">Active</span>
            @else
              <span class="badge text-bg-warning">Inactive</span>
            @endif
          </td>
          <td>{{ $r->group?->name ?? 'None' }}</td>
          <td class="text-end">${{ number_format($r->cost + $r->profit, 2) }}</td>
          <td class="text-end">${{ number_format($r->cost, 2) }}</td>

          {{-- ✅ اسم المزوّد بدل رقم الـID --}}
          <td>{{ $r->supplier?->name ?? 'None' }}</td>

          {{-- بإمكانك هنا إظهار كلمة "API" أو مصدر الخدمة --}}
          <td>{{ $r->api?->name ?? $r->supplier?->name ?? 'None' }}</td>


          <td class="text-end">
            @if(($viewPrefix ?? '') === 'imei')
              {{-- ✅ يفتح مودال التعديل عبر AJAX --}}
              <a class="btn btn-warning btn-sm js-open-modal"
                 data-url="{{ route('admin.services.imei.modal.edit', $r->id) }}">Edit</a>
            @else
              <a class="btn btn-warning btn-sm" href="{{ route("{$routePrefix}.edit", $r->id) }}">Edit</a>
            @endif

            <form class="d-inline" method="POST"
                  action="{{ route("{$routePrefix}.destroy", $r->id) }}"
                  onsubmit="return confirm('Delete this service?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="text-center text-muted py-4">No services found.</td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>

  @if($rows instanceof \Illuminate\Contracts\Pagination\Paginator)
    <div class="card-footer d-flex justify-content-between">
      <div class="text-muted small">
        Showing {{ $rows->firstItem() }} to {{ $rows->lastItem() }} of {{ $rows->total() }} items
      </div>
      {{ $rows->appends(request()->query())->links() }}
    </div>
  @endif
</div>
