<div class="modal-header" style="background:#111;color:#fff;">
  <div>
    <div class="h6 mb-0">Import Server Services — {{ $provider->name }}</div>
    <div class="small opacity-75">Import Selected / Import All</div>
  </div>
  <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
  <div class="row g-2 align-items-end mb-3">
    <div class="col-md-6">
      <label class="form-label mb-1">Search</label>
      <input type="text" class="form-control form-control-sm" id="wizSearch" placeholder="Search...">
    </div>

    <div class="col-md-3">
      <label class="form-label mb-1">Pricing mode</label>
      <select class="form-select form-select-sm" id="wizPricingMode">
        <option value="fixed">+ Fixed Credits</option>
        <option value="percent">+ % Profit</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label mb-1">Value</label>
      <input type="number" step="0.01" class="form-control form-control-sm" id="wizPricingValue" value="0">
    </div>
  </div>

  <div class="d-flex gap-2 mb-2">
    <button class="btn btn-outline-secondary btn-sm" id="wizSelectAll" type="button">Select all</button>
    <button class="btn btn-outline-secondary btn-sm" id="wizUnselectAll" type="button">Unselect all</button>
    <div class="ms-auto small text-muted" id="wizSelectedCount">0 selected</div>
  </div>

  <div class="table-responsive border rounded" style="max-height:55vh; overflow:auto;">
    <table class="table table-sm table-striped align-middle mb-0" id="wizTable">
      <thead class="position-sticky top-0 bg-white" style="z-index:1">
        <tr>
          <th style="width:40px"></th>
          <th style="min-width:260px">Group</th>
          <th style="width:110px">Remote ID</th>
          <th style="min-width:520px">Name</th>
          <th style="width:110px">Credit</th>
          <th style="width:140px">Time</th>
        </tr>
      </thead>
      <tbody>
        @foreach($services as $s)
          @php
            $rid = (string)($s->remote_id ?? '');
            $isAdded = isset($existing[$rid]);
          @endphp
          <tr data-wiz-row
              data-group="{{ strtolower((string)($s->group_name ?? '')) }}"
              data-name="{{ strtolower((string)($s->name ?? '')) }}"
              data-remote="{{ strtolower($rid) }}">
            <td>
              <input type="checkbox" class="wiz-check" value="{{ $rid }}" @disabled($isAdded)>
            </td>
            <td>{{ $s->group_name ?? '' }}</td>
            <td><code>{{ $rid }}</code></td>
            <td style="min-width:520px;">{{ $s->name ?? '' }}</td>
            <td>{{ number_format((float)($s->price ?? $s->credit ?? 0), 4) }}</td>
            <td>{{ $s->time ?? '' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

</div>

<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
  <button type="button" class="btn btn-dark" id="wizImportAll">Import ALL</button>
  <button type="button" class="btn btn-success" id="wizImportSelected">Import Selected</button>
</div>

<script>
(function(){
  const importUrl = @json(route('admin.apis.remote.server.import', $provider));
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const wizSearch = document.getElementById('wizSearch');
  const checks = () => Array.from(document.querySelectorAll('.wiz-check'));
  const selected = () => checks().filter(x => x.checked && !x.disabled).map(x => x.value);

  function updateCount(){
    document.getElementById('wizSelectedCount').innerText = `${selected().length} selected`;
  }

  wizSearch?.addEventListener('input', (e)=>{
    const q = (e.target.value||'').toLowerCase().trim();
    document.querySelectorAll('#wizTable tr[data-wiz-row]').forEach(tr=>{
      const hit = tr.dataset.group.includes(q) || tr.dataset.name.includes(q) || tr.dataset.remote.includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });

  document.getElementById('wizSelectAll')?.addEventListener('click', ()=>{
    checks().forEach(cb => { if(!cb.disabled) cb.checked = true; });
    updateCount();
  });
  document.getElementById('wizUnselectAll')?.addEventListener('click', ()=>{
    checks().forEach(cb => cb.checked = false);
    updateCount();
  });
  document.addEventListener('change', (e)=>{
    if(e.target.classList?.contains('wiz-check')) updateCount();
  });

  async function sendImport(payload){
    const res = await fetch(importUrl,{
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With':'XMLHttpRequest'
      },
      body: JSON.stringify(payload)
    });

    const data = await res.json().catch(()=>null);
    if(!res.ok || !data?.ok){
      alert(data?.msg || 'Import failed');
      return null;
    }
    return data;
  }

  document.getElementById('wizImportSelected')?.addEventListener('click', async ()=>{
    const ids = selected();
    if(!ids.length) return alert('Select services first');

    const pricing_mode = document.getElementById('wizPricingMode').value;
    const pricing_value = document.getElementById('wizPricingValue').value;

    const data = await sendImport({ apply_all:false, service_ids: ids, pricing_mode, pricing_value });
    if(data?.ok){
      alert(`✅ Imported ${data.count} services`);
      location.reload();
    }
  });

  document.getElementById('wizImportAll')?.addEventListener('click', async ()=>{
    if(!confirm('Import ALL services?')) return;

    const pricing_mode = document.getElementById('wizPricingMode').value;
    const pricing_value = document.getElementById('wizPricingValue').value;

    const data = await sendImport({ apply_all:true, service_ids: [], pricing_mode, pricing_value });
    if(data?.ok){
      alert(`✅ Imported ${data.count} services`);
      location.reload();
    }
  });

  updateCount();
})();
</script>
