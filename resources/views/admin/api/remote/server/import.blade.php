{{-- resources/views/admin/api/remote/server/import.blade.php --}}
@extends('admin.layouts.app')

@section('content')
  <div class="container-fluid py-3">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-0">Import — {{ $provider->name }} (SERVER)</h4>
        <div class="text-muted small">Standalone import page (with full admin layout & assets)</div>
      </div>

      <div class="d-flex gap-2">
        <a href="{{ route('admin.apis.services.server.page', $provider) }}" class="btn btn-outline-secondary btn-sm">
          Back to list
        </a>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center gap-2">
        <input id="importSearch"
               type="text"
               class="form-control form-control-sm"
               placeholder="Search..."
               style="width:min(520px, 60vw);">

        <div class="ms-auto d-flex gap-2">
          <select class="form-select form-select-sm" id="pricing_mode" style="width:180px">
            <option value="percent">+ % Profit</option>
            <option value="fixed">+ Fixed Credits</option>
          </select>

          <input type="number" step="0.01" class="form-control form-control-sm" id="pricing_value" value="0" style="width:140px">

          <button type="button" class="btn btn-dark btn-sm" id="btnImportAll">Import ALL</button>
          <button type="button" class="btn btn-success btn-sm" id="btnImportSelected">Import Selected</button>
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive border-top">
          <table class="table table-sm table-striped mb-0 align-middle" id="importTable">
            <thead>
              <tr>
                <th style="width:44px"></th>
                <th style="width:220px">Group</th>
                <th style="width:110px">Remote ID</th>
                <th>Name</th>
                <th style="width:90px">Credits</th>
                <th style="width:140px">Time</th>
              </tr>
            </thead>
            <tbody>
              @foreach(($services ?? []) as $svc)
                @php
                  $rid   = (string)($svc->remote_id ?? '');
                  $group = (string)($svc->group_name ?? '');
                  $name  = (string)($svc->name ?? '');
                  $time  = (string)($svc->time ?? '');
                  $credit= (float)($svc->price ?? $svc->credit ?? 0);
                  $isAdded = isset($existing[$rid]);
                @endphp

                <tr data-row
                    data-group="{{ strtolower($group) }}"
                    data-name="{{ strtolower(strip_tags($name)) }}"
                    data-remote="{{ strtolower($rid) }}">
                  <td>
                    <input type="checkbox" class="row-check" value="{{ $rid }}" @disabled($isAdded)>
                  </td>
                  <td>{{ $group }}</td>
                  <td><code>{{ $rid }}</code></td>
                  <td>{{ strip_tags($name) }}</td>
                  <td>{{ number_format($credit,4) }}</td>
                  <td>{{ strip_tags($time) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
@endsection

@push('scripts')
<script>
(function(){
  const csrfToken = @json(csrf_token());
  const importUrl = @json(route('admin.apis.services.server.import', $provider));

  const importSearch = document.getElementById('importSearch');
  importSearch?.addEventListener('input', () => {
    const q = (importSearch.value || '').trim().toLowerCase();
    document.querySelectorAll('#importTable tr[data-row]').forEach(tr => {
      const hit =
        (tr.dataset.group || '').includes(q) ||
        (tr.dataset.name  || '').includes(q) ||
        (tr.dataset.remote|| '').includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });

  const checks = () => Array.from(document.querySelectorAll('.row-check'));
  const selected = () => checks().filter(x => x.checked && !x.disabled).map(x => x.value);

  async function sendImport(payload){
    const res = await fetch(importUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json().catch(() => null);
    if(!res.ok || !data?.ok){
      alert(data?.msg || 'Import failed');
      return null;
    }
    return data;
  }

  function markDisabled(remoteIds){
    (remoteIds || []).forEach(id => {
      const rid = String(id || '').trim();
      if(!rid) return;

      document.querySelectorAll('.row-check').forEach(cb => {
        if(String(cb.value) === rid){
          cb.checked = false;
          cb.disabled = true;
        }
      });
    });
  }

  document.getElementById('btnImportSelected')?.addEventListener('click', async () => {
    const ids = selected();
    if(!ids.length) return alert('Select services first');

    const pricing_mode  = document.getElementById('pricing_mode')?.value || 'percent';
    const pricing_value = document.getElementById('pricing_value')?.value || 0;

    const data = await sendImport({
      kind: 'server',
      apply_all: false,
      service_ids: ids,
      pricing_mode,
      pricing_value
    });

    if(data?.ok){
      markDisabled(data.added_remote_ids || ids);
      alert(`Imported: ${data.count || 0}`);
    }
  });

  document.getElementById('btnImportAll')?.addEventListener('click', async () => {
    if(!confirm('Import ALL services?')) return;

    const pricing_mode  = document.getElementById('pricing_mode')?.value || 'percent';
    const pricing_value = document.getElementById('pricing_value')?.value || 0;

    const data = await sendImport({
      kind: 'server',
      apply_all: true,
      service_ids: [],
      pricing_mode,
      pricing_value
    });

    if(data?.ok){
      markDisabled(data.added_remote_ids || []);
      alert(`Imported: ${data.count || 0}`);
    }
  });

})();
</script>
@endpush