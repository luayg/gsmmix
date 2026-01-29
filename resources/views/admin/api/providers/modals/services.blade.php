{{-- resources/views/admin/api/providers/modals/services.blade.php --}}

@php
  // kind: imei | server | file
  $kindLabel = strtoupper($kind);
  $importUrl = route('admin.apis.services.import_wizard', $provider);

  // خدمات موجودة محليًا لمنع إعادة الإضافة
  $existing = match ($kind) {
    'imei'   => \App\Models\ImeiService::where('supplier_id',$provider->id)->pluck('remote_id')->map(fn($v)=>(string)$v)->flip()->all(),
    'server' => \App\Models\ServerService::where('supplier_id',$provider->id)->pluck('remote_id')->map(fn($v)=>(string)$v)->flip()->all(),
    'file'   => \App\Models\FileService::where('supplier_id',$provider->id)->pluck('remote_id')->map(fn($v)=>(string)$v)->flip()->all(),
    default  => [],
  };
@endphp

<div class="modal-header align-items-center" style="background:#3bb37a;color:#fff;">
  <div>
    <div class="h6 mb-0">{{ $provider->name }} | {{ $kindLabel }} services</div>
    <div class="small opacity-75">Remote services list</div>
  </div>

  <div class="d-flex gap-2 align-items-center ms-auto">
    <input id="svcSearch"
           type="text"
           class="form-control form-control-sm"
           placeholder="Search service (name / group / remote id)..."
           style="width:min(420px, 46vw);">

    <button type="button" class="btn btn-dark btn-sm" id="btnOpenImportWizard">
      Import Services with Group
    </button>

    {{-- ✅ X close أعلى اليمين --}}
    <button type="button" class="btn-close btn-close-white ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
</div>

<div class="modal-body p-0">
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle" id="svcTable">
      <thead>
        <tr>
          <th style="width:190px">Group</th>
          <th style="width:110px">Remote ID</th>
          <th>Name</th>
          <th style="width:90px">Credits</th>
          <th style="width:120px">Time</th>
          <th class="text-end" style="width:130px">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($services as $s)
          @php
            $group = (string)($s['GROUPNAME'] ?? '');
            $rid   = (string)($s['REMOTEID'] ?? '');
            $name  = (string)($s['NAME'] ?? '');
            $credit= (float)($s['CREDIT'] ?? 0);
            $time  = (string)($s['TIME'] ?? '');
            $isAdded = isset($existing[$rid]);
          @endphp

          <tr data-row
              data-group="{{ strtolower($group) }}"
              data-name="{{ strtolower($name) }}"
              data-remote="{{ strtolower($rid) }}"
              data-remote-id="{{ $rid }}">
            <td>{{ $group }}</td>
            <td><code>{{ $rid }}</code></td>
            <td style="min-width:520px;">{{ $name }}</td>
            <td>{{ number_format($credit, 4) }}</td>
            <td>{{ $time }}</td>
            <td class="text-end">
              @if($isAdded)
                <button type="button" class="btn btn-secondary btn-sm" disabled>Added ✅</button>
              @else
                {{-- ✅ FIX: خلي زر Clone يستخدم data-create-service حتى يفتح مودال الإنشاء --}}
                <button type="button"
                        class="btn btn-success btn-sm clone-btn"
                        data-create-service
                        data-service-type="{{ $kind }}"
                        data-provider-id="{{ $provider->id }}"
                        data-provider-name="{{ $provider->name }}"
                        data-remote-id="{{ $rid }}"
                        data-group-name="{{ e($group) }}"
                        data-name="{{ e($name) }}"
                        data-credit="{{ number_format($credit, 4, '.', '') }}"
                        data-time="{{ e($time) }}">
                  Clone
                </button>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center p-4">No data</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ============ Import Wizard Modal (Step) ============ --}}
<div class="modal fade" id="importWizard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:min(1400px,96vw);">
    <div class="modal-content">
      <div class="modal-header" style="background:#111;color:#fff;">
        <div class="h6 mb-0">Import Services — {{ $provider->name }} ({{ $kindLabel }})</div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-6">
            <label class="form-label mb-1">Search</label>
            <input type="text" class="form-control form-control-sm" id="wizSearch" placeholder="Search...">
          </div>

          <div class="col-md-2">
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="wizSelectAll">Select all</button>
          </div>

          <div class="col-md-2">
            <button type="button" class="btn btn-outline-secondary btn-sm w-100" id="wizUnselectAll">Unselect all</button>
          </div>

          <div class="col-md-2 text-end">
            <span class="small text-muted" id="wizSelectedCount">0 selected</span>
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <label class="form-label mb-1">Pricing mode</label>
            <select class="form-select form-select-sm" id="wizPricingMode">
              <option value="percent">+ % Profit</option>
              <option value="fixed">+ Fixed Credits</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label mb-1">Value</label>
            <input type="number" step="0.01" class="form-control form-control-sm" id="wizPricingValue" value="0">
          </div>

          <div class="col-md-4 d-flex align-items-end justify-content-end gap-2">
            <button type="button" class="btn btn-dark btn-sm" id="wizImportAll">Import ALL</button>
            <button type="button" class="btn btn-success btn-sm" id="wizImportSelected">Import Selected</button>
          </div>
        </div>

        <div class="table-responsive border rounded">
          <table class="table table-sm table-striped mb-0 align-middle" id="wizTable">
            <thead>
              <tr>
                <th style="width:40px"></th>
                <th>Group</th>
                <th style="width:110px">Remote ID</th>
                <th>Name</th>
                <th style="width:90px">Credits</th>
                <th style="width:110px">Time</th>
              </tr>
            </thead>
            <tbody>
              @foreach($services as $s)
                @php
                  $group = (string)($s['GROUPNAME'] ?? '');
                  $rid   = (string)($s['REMOTEID'] ?? '');
                  $name  = (string)($s['NAME'] ?? '');
                  $credit= (float)($s['CREDIT'] ?? 0);
                  $time  = (string)($s['TIME'] ?? '');
                  $isAdded = isset($existing[$rid]);
                @endphp
                <tr data-wiz-row
                    data-group="{{ strtolower($group) }}"
                    data-name="{{ strtolower($name) }}"
                    data-remote="{{ strtolower($rid) }}">
                  <td>
                    <input type="checkbox" class="wiz-check" value="{{ $rid }}" @disabled($isAdded)>
                  </td>
                  <td>{{ $group }}</td>
                  <td><code>{{ $rid }}</code></td>
                  <td style="min-width:520px;">{{ $name }}</td>
                  <td>{{ number_format($credit, 4) }}</td>
                  <td>{{ $time }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const kind      = @json($kind);
  const providerId = @json($provider->id);
  const importUrl = @json($importUrl);
  const csrfToken = @json(csrf_token());

  const toastOk = (msg) => {
    if (window.showToast) window.showToast('success', msg, { title: 'Done' });
    else alert(msg);
  };
  const toastErr = (msg) => {
    if (window.showToast) window.showToast('danger', msg, { title: 'Error', delay: 5000 });
    else alert(msg);
  };

  // ===== Main search =====
  const svcSearch = document.getElementById('svcSearch');
  svcSearch?.addEventListener('input', () => {
    const q = (svcSearch.value || '').trim().toLowerCase();
    document.querySelectorAll('#svcTable tr[data-row]').forEach(tr => {
      const hit =
        tr.dataset.group.includes(q) ||
        tr.dataset.name.includes(q) ||
        tr.dataset.remote.includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });

  // ===== Open wizard =====
  const wizardEl = document.getElementById('importWizard');
  const wizard = bootstrap.Modal.getOrCreateInstance(wizardEl);
  document.getElementById('btnOpenImportWizard')?.addEventListener('click', () => wizard.show());

  // ===== Wizard helpers =====
  const wizSearch = document.getElementById('wizSearch');
  const wizChecks = () => Array.from(document.querySelectorAll('.wiz-check'));
  const wizSelected = () => wizChecks().filter(x => x.checked && !x.disabled).map(x => x.value);

  function updateCount(){
    const n = wizSelected().length;
    document.getElementById('wizSelectedCount').innerText = `${n} selected`;
  }

  wizSearch?.addEventListener('input', () => {
    const q = (wizSearch.value || '').trim().toLowerCase();
    document.querySelectorAll('#wizTable tr[data-wiz-row]').forEach(tr => {
      const hit =
        tr.dataset.group.includes(q) ||
        tr.dataset.name.includes(q) ||
        tr.dataset.remote.includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });

  document.getElementById('wizSelectAll')?.addEventListener('click', () => {
    wizChecks().forEach(cb => { if (!cb.disabled) cb.checked = true; });
    updateCount();
  });

  document.getElementById('wizUnselectAll')?.addEventListener('click', () => {
    wizChecks().forEach(cb => cb.checked = false);
    updateCount();
  });

  document.addEventListener('change', (e) => {
    if (e.target.classList?.contains('wiz-check')) updateCount();
  });

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

    if (!res.ok || !data?.ok) {
      toastErr(data?.msg || 'Import failed');
      return null;
    }
    return data;
  }

  function markAsAdded(remoteIds){
    (remoteIds || []).forEach(id => {
      const row = document.querySelector(`#svcTable tr[data-remote-id="${CSS.escape(String(id))}"]`);
      if (row) {
        const btn = row.querySelector('.clone-btn');
        if (btn) {
          // ✅ اجعل الزر "Add" + لون فاتح
          btn.classList.remove(
            'btn-success','btn-secondary','btn-danger','btn-warning','btn-info','btn-dark','btn-primary'
          );
          btn.classList.add('btn-outline-primary'); // لون فاتح

          btn.innerText = 'Add';
          btn.disabled = true;

          // لا تفتح مودال الإنشاء بعد الإضافة
          btn.removeAttribute('data-create-service');
        }
      }

      // ✅ عطل checkbox في الـ wizard أيضًا
      document.querySelectorAll('.wiz-check').forEach(cb => {
        if (String(cb.value) === String(id)) {
          cb.checked = false;
          cb.disabled = true;
        }
      });
    });

    updateCount();
  }

  // ✅ NEW: خلي markAsAdded متاح عالميًا (اختياري)
  window.gsmmixMarkRemoteAdded = window.gsmmixMarkRemoteAdded || {};
  window.gsmmixMarkRemoteAdded[`${providerId}:${kind}`] = markAsAdded;

  // ✅ NEW: استمع لحدث “تم إنشاء خدمة بالـ Clone” وحدث الـ wizard فورًا بدون خروج/دخول
  window.addEventListener('gsmmix:service-created', (ev) => {
    const d = ev?.detail || {};
    if (String(d.provider_id) !== String(providerId)) return;
    if (String(d.kind) !== String(kind)) return;
    if (!d.remote_id) return;

    markAsAdded([d.remote_id]);

    // لو كان الـ wizard مفتوح وكنت محدد checkbox، نحدّث العداد فورًا
    updateCount();
  });

  document.getElementById('wizImportSelected')?.addEventListener('click', async () => {
    const ids = wizSelected();
    if (!ids.length) return toastErr('Select services first');

    const pricing_mode  = document.getElementById('wizPricingMode').value;
    const pricing_value = document.getElementById('wizPricingValue').value;

    const data = await sendImport({
      kind,
      apply_all: false,
      service_ids: ids,
      pricing_mode,
      pricing_value,
    });

    if (data?.ok) {
      markAsAdded(data.added_remote_ids || ids);
      toastOk(`Imported ${data.count} services successfully ✅`);
      wizard.hide();
    }
  });

  document.getElementById('wizImportAll')?.addEventListener('click', async () => {
    if (!confirm('Import ALL services?')) return;

    const pricing_mode  = document.getElementById('wizPricingMode').value;
    const pricing_value = document.getElementById('wizPricingValue').value;

    const data = await sendImport({
      kind,
      apply_all: true,
      service_ids: [],
      pricing_mode,
      pricing_value,
    });

    if (data?.ok) {
      markAsAdded(data.added_remote_ids || []);
      toastOk(`Imported ${data.count} services successfully ✅`);
      wizard.hide();
    }
  });

})();
</script>

{{-- ✅ Template required for Service Modal Clone --}}
<template id="serviceCreateTpl">
  @if($kind === 'imei')
    @include('admin.services.imei._modal_create')
  @elseif($kind === 'server')
    @include('admin.services.server._modal_create')
  @elseif($kind === 'file')
    @include('admin.services.file._modal_create')
  @endif
</template>
