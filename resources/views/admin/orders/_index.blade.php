{{-- Generic Orders Index --}}
@extends('layouts.admin')
@section('title', $title ?? 'Orders')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">{{ $title }}</h4>

  <button class="btn btn-success"
          data-modal-url="{{ $newModalUrl }}">
    New order
  </button>
</div>

<div class="card">
  <div class="card-body">
    <form class="row g-2 mb-3" method="get">
      <div class="col-md-5">
        <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Search: device / remote id / email">
      </div>
      <div class="col-md-3">
        <select class="form-select" name="status">
          <option value="">All status</option>
          @foreach(['WAITING','INPROGRESS','SUCCESS','FAILED','MANUAL'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ $s }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <select class="form-select" name="provider">
          <option value="">All providers</option>
          @foreach($providers as $p)
            <option value="{{ $p->id }}" @selected((string)request('provider')===(string)$p->id)>{{ $p->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary w-100">Go</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Date</th>
          <th>Device</th>
          <th>Service</th>
          <th>Provider</th>
          <th>Status</th>
          <th style="width:220px">Actions</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rows as $row)
          <tr>
            <td>#{{ $row->id }}</td>
            <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>
            <td>{{ $row->device }}</td>
            <td>{{ $row->service?->name_json['en'] ?? $row->service?->name ?? ('#'.$row->service_id) }}</td>
            <td>{{ $row->provider?->name ?? '—' }}</td>
            <td>
              @php
                $badge = match($row->status){
                  'SUCCESS' => 'bg-success',
                  'FAILED' => 'bg-danger',
                  'INPROGRESS' => 'bg-info',
                  'MANUAL' => 'bg-secondary',
                  default => 'bg-warning text-dark'
                };
              @endphp
              <span class="badge {{ $badge }}">{{ $row->status }}</span>
              @if($row->remote_id)
                <div class="small text-muted">Ref: {{ $row->remote_id }}</div>
              @endif
            </td>
            <td>
              <button class="btn btn-sm btn-primary"
                      data-modal-url="{{ $viewUrl($row->id) }}">View</button>

              <button class="btn btn-sm btn-warning"
                      data-modal-url="{{ $editUrl($row->id) }}">Edit</button>

              {{-- Retry/Send (اختياري) --}}
              <button class="btn btn-sm btn-outline-secondary js-send"
                      data-send-url="{{ $sendUrl($row->id) }}">Send</button>

              <button class="btn btn-sm btn-outline-info js-refresh"
                      data-refresh-url="{{ $refreshUrl($row->id) }}">Refresh</button>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-muted">No orders</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    {{ $rows->links() }}
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const modalEl = document.getElementById('appModal');
  const modalContent = document.getElementById('appModalContent');

  async function openModal(url){
    const res = await fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest'}});
    const html = await res.text();
    modalContent.innerHTML = html;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    bindModalForms();
  }

  function bindModalForms(){
    // Create form
    const createForm = modalContent.querySelector('form[data-ajax="create"]');
    if (createForm){
      createForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const res = await fetch(createForm.action, {
          method:'POST',
          headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
          body: new FormData(createForm)
        });
        const json = await res.json().catch(()=>null);
        if (!json || !json.ok){
          alert('Create failed');
          return;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        location.reload();
      });
    }

    // Update form
    const updateForm = modalContent.querySelector('form[data-ajax="update"]');
    if (updateForm){
      updateForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(updateForm);
        fd.append('_method','PUT');
        const res = await fetch(updateForm.action, {
          method:'POST',
          headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},
          body: fd
        });
        const json = await res.json().catch(()=>null);
        if (!json || !json.ok){
          alert('Save failed');
          return;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        location.reload();
      });
    }
  }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('[data-modal-url]');
    if (btn){
      e.preventDefault();
      openModal(btn.getAttribute('data-modal-url'));
    }
  });

  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('.js-send');
    if (!btn) return;
    e.preventDefault();
    const url = btn.getAttribute('data-send-url');
    btn.disabled = true;
    const res = await fetch(url, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
    await res.json().catch(()=>null);
    location.reload();
  });

  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('.js-refresh');
    if (!btn) return;
    e.preventDefault();
    const url = btn.getAttribute('data-refresh-url');
    btn.disabled = true;
    const res = await fetch(url, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
    await res.json().catch(()=>null);
    location.reload();
  });
})();
</script>
@endpush
