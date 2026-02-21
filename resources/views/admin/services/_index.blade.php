{{-- resources/views/admin/services/_index.blade.php --}}
@php use Illuminate\Support\Str; @endphp

@if(isset($viewPrefix))
  <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
    <div class="fs-4 fw-semibold text-capitalize">{{ $viewPrefix }} services</div>

    <form class="ms-auto d-flex gap-2 align-items-center" method="GET" action="">
      <input class="form-control form-control-sm" name="q" placeholder="Smart search"
             value="{{ request('q') }}" style="max-width:240px">

      <select class="form-select form-select-sm" name="api_provider_id" style="max-width:260px" onchange="this.form.submit()">
        <option value="">API connection</option>
        @foreach(($apis ?? collect()) as $a)
          <option value="{{ $a->id }}" @selected((string)request('api_provider_id') === (string)$a->id)>
            {{ $a->name }}
          </option>
        @endforeach
      </select>

      <a class="btn btn-sm btn-success" href="javascript:;" data-create-service data-service-type="{{ $viewPrefix }}">
        Create service
      </a>
    </form>
  </div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Name</th>
          <th>Status</th>
          <th>Group</th>
          <th class="text-end">Price</th>
          <th class="text-end">Cost</th>
          <th>Supplier</th>
          <th>API connection</th>
          <th style="width:200px" class="text-end">Actions</th>
        </tr>
      </thead>

      <tbody>
        @forelse($rows as $r)
          @php
            // ✅ Fix display when name stored as JSON string
            $displayName = $r->name;
            if (is_string($displayName)) {
              $trim = trim($displayName);
              if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $j = json_decode($trim, true);
                if (is_array($j)) {
                  $displayName = $j['fallback'] ?? $j['en'] ?? (is_array(reset($j)) ? null : reset($j)) ?? $r->name;
                }
              }
            }

            $jsonUrl   = route($routePrefix.'.show.json', $r->id);
            $updateUrl = route($routePrefix.'.update', $r->id);
            $deleteUrl = route($routePrefix.'.destroy', $r->id);
          @endphp

          <tr data-service-row data-service-id="{{ $r->id }}">
            <td><span class="fw-semibold">{{ $r->id }}</span></td>

            <td title="{{ is_string($displayName) ? $displayName : '' }}">
              {{ Str::limit((string)$displayName, 80) }}
            </td>

            <td>
              @if($r->active)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>

            <td>
              {{ $r->group?->name ?? 'None' }}
            </td>

            <td class="text-end">{{ number_format((float)($r->price ?? 0), 2) }}</td>
            <td class="text-end">{{ number_format((float)($r->cost ?? 0), 2) }}</td>

            <td>{{ $r->supplier?->name ?? 'None' }}</td>

            {{-- ✅ API connection الصحيح: نفس supplier لو كان source=2 وإلا None --}}
            <td>
              @if((int)($r->source ?? 1) === 2)
                {{ $r->supplier?->name ?? 'API' }}
              @else
                None
              @endif
            </td>

            <td class="text-nowrap text-end">
              <button
                type="button"
                class="btn btn-sm btn-warning"
                data-edit-service
                data-service-type="{{ $viewPrefix }}"
                data-service-id="{{ $r->id }}"
                data-json-url="{{ $jsonUrl }}"
                data-update-url="{{ $updateUrl }}"
              >Edit</button>

              <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                data-delete-service
                data-delete-url="{{ $deleteUrl }}"
                data-row-id="{{ $r->id }}"
              >Delete</button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center text-muted py-4">No services found</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card-footer">
    {{ $rows->links() }}
  </div>
</div>