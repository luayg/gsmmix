@extends('layouts.admin')

@section('title', 'Service groups')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="h4 mb-0">Service groups</div>

    <button type="button" class="btn btn-primary btn-sm"
            data-ajax-modal="{{ route('admin.services.groups.modal.create') }}">
      <i class="fas fa-plus"></i> New group
    </button>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead>
          <tr>
            <th style="width:80px">ID</th>
            <th>Name</th>
            <th>Type</th>
            <th>Active</th>
            <th>Ordering</th>
            <th class="text-end" style="width:200px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse(($rows ?? []) as $r)
            <tr>
              <td>{{ $r->id }}</td>
              <td>{{ $r->name ?? '' }}</td>
              <td class="text-uppercase">{{ $r->type ?? '' }}</td>
              <td>
                @if(($r->active ?? false))
                  <span class="badge bg-success">Active</span>
                @else
                  <span class="badge bg-secondary">Inactive</span>
                @endif
              </td>
              <td>{{ $r->ordering ?? 0 }}</td>
              <td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-warning"
                        data-ajax-modal="{{ route('admin.services.groups.modal.edit', $r->id) }}">
                  Edit
                </button>

                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-ajax-modal="{{ route('admin.services.groups.modal.delete', $r->id) }}">
                  Delete
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No groups yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($rows, 'links'))
      <div class="card-footer">{{ $rows->withQueryString()->links() }}</div>
    @endif
  </div>

  {{-- ✅ Main Ajax Modal (reusable) --}}
  @once
    @push('modals')
      <div class="modal fade" id="mainAjaxModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content" id="mainAjaxModalContent"></div>
        </div>
      </div>
    @endpush

    @push('scripts')
      <script>
        (function(){
          const modalEl = document.getElementById('mainAjaxModal');
          const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
          const box = document.getElementById('mainAjaxModalContent');

          document.addEventListener('click', async (e)=>{
            const btn = e.target.closest('[data-ajax-modal]');
            if(!btn) return;
            e.preventDefault();

            const url = btn.dataset.ajaxModal;
            const res = await fetch(url, { headers:{'X-Requested-With':'XMLHttpRequest'} });
            const html = await res.text();

            box.innerHTML = html;

            // re-run scripts inside loaded html
            Array.from(box.querySelectorAll('script')).forEach(old=>{
              const s = document.createElement('script');
              s.text = old.textContent || '';
              old.parentNode?.removeChild(old);
              box.appendChild(s);
            });

            modal?.show();
          });
        })();
      </script>
    @endpush
  @endonce
@endsection