{{-- resources/views/admin/api/remote/server/modal.blade.php --}}
@php
  // $provider: ApiProvider
  // $groups: Collection grouped by group_name
  // $existing: array flip remote_id => true (to disable clone)

  // ✅ حماية: لا تسمح بكسر المودال لو لم تُمرّر $groups
  if (!isset($groups) || !($groups instanceof \Illuminate\Support\Collection)) {
    $groups = collect();
  }
  if (!isset($existing) || !is_array($existing)) {
    $existing = [];
  }
@endphp

<div class="modal-header align-items-center" style="background:#111;color:#fff;">
  <div>
    <div class="h6 mb-0">
      {{ $provider->name }} | SERVER remote services
    </div>
    <div class="small opacity-75">Clone services (with additional fields)</div>
  </div>

  <div class="d-flex gap-2 align-items-center ms-auto">
    <input id="svcSearch"
           type="text"
           class="form-control form-control-sm"
           placeholder="Search (name / group / remote id)..."
           style="width:min(460px, 48vw);">

    <button type="button" class="btn btn-success btn-sm" id="btnOpenImportWizard">
      Import Services with Category
    </button>

    <button type="button" class="btn-close btn-close-white ms-1" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
</div>

<div class="modal-body p-0">
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle" id="svcTable">
      <thead>
        <tr>
          <th style="width:220px">Group</th>
          <th style="width:110px">Remote ID</th>
          <th>Name</th>
          <th style="width:100px">Credits</th>
          <th style="width:140px">Time</th>
          <th class="text-end" style="width:140px">Action</th>
        </tr>
      </thead>

      <tbody>
      @forelse(($groups ?? collect()) as $groupName => $items)
        @foreach($items as $svc)
          @php
            $rid   = (string)($svc->remote_id ?? $svc->REMOTEID ?? $svc->id ?? '');
            $name  = (string)($svc->name ?? $svc->NAME ?? '');
            $time  = (string)($svc->time ?? $svc->TIME ?? '');
            $credit = (float)($svc->price ?? $svc->credit ?? $svc->CREDIT ?? 0);

            $af = $svc->additional_fields ?? $svc->ADDITIONAL_FIELDS ?? $svc->fields ?? null;
            $afJson = is_array($af) ? json_encode($af, JSON_UNESCAPED_UNICODE) : (string)($af ?? '[]');

            $isAdded = isset($existing[$rid]);
          @endphp

          <tr data-row
              data-group="{{ strtolower($groupName) }}"
              data-name="{{ strtolower(strip_tags($name)) }}"
              data-remote="{{ strtolower($rid) }}"
              data-remote-id="{{ $rid }}">
            <td>{{ $groupName }}</td>
            <td><code>{{ $rid }}</code></td>
            <td style="min-width:520px;">{!! $name !!}</td>
            <td>{{ number_format($credit, 4) }}</td>
            <td>{!! $time !!}</td>
            <td class="text-end">
              @if($isAdded)
                <button type="button" class="btn btn-outline-primary btn-sm" disabled>Added ✅</button>
              @else
                <button type="button"
                        class="btn btn-success btn-sm clone-btn"
                        data-create-service
                        data-service-type="server"
                        data-provider-id="{{ $provider->id }}"
                        data-provider-name="{{ e($provider->name) }}"
                        data-remote-id="{{ e($rid) }}"
                        data-group-name="{{ e($groupName) }}"
                        data-name="{{ e(strip_tags($name)) }}"
                        data-credit="{{ number_format($credit, 4, '.', '') }}"
                        data-time="{{ e(strip_tags($time)) }}"
                        data-additional-fields="{{ e($afJson) }}">
                  Clone
                </button>
              @endif
            </td>
          </tr>
        @endforeach
      @empty
        <tr><td colspan="6" class="text-center p-4 text-muted">No data</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ============ Import Wizard Modal ============ --}}
<div class="modal fade" id="importWizard" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width:min(1400px,96vw);">
    <div class="modal-content">
      <div class="modal-header" style="background:#0b5ed7;color:#fff;">
        <div class="h6 mb-0">Import Services — {{ $provider->name }} (SERVER)</div>
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
              @foreach($groups as $groupName => $items)
                @foreach($items as $svc)
                  @php
                    $rid   = (string)($svc->remote_id ?? '');
                    $name  = (string)($svc->name ?? '');
                    $time  = (string)($svc->time ?? '');
                    $credit= (float)($svc->price ?? $svc->credit ?? 0);
                    $isAdded = isset($existing[$rid]);
                  @endphp

                  <tr data-wiz-row
                      data-group="{{ strtolower($groupName) }}"
                      data-name="{{ strtolower(strip_tags($name)) }}"
                      data-remote="{{ strtolower($rid) }}">
                    <td>
                      @if($isAdded)
                        {{-- ✅ لا نُظهر checkbox للخدمة المضافة --}}
                        <span class="badge bg-success">Added</span>
                      @else
                        <input type="checkbox" class="wiz-check" value="{{ $rid }}">
                      @endif
                    </td>
                    <td>{{ $groupName }}</td>
                    <td><code>{{ $rid }}</code></td>
                    <td style="min-width:520px;">{{ strip_tags($name) }}</td>
                    <td>{{ number_format($credit,4) }}</td>
                    <td>{{ strip_tags($time) }}</td>
                  </tr>
                @endforeach
              @endforeach
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- Template required for Create Service Modal (Clone) --}}
<template id="serviceCreateTpl">
  @include('admin.services.server._modal_create')
</template>

<script>
(function(){
  const kind = 'server';
  const providerId = @json($provider->id);

  // ===================== Search in remote table =====================
  const svcSearch = document.getElementById('svcSearch');
  svcSearch?.addEventListener('input', () => {
    const q = (svcSearch.value || '').trim().toLowerCase();
    document.querySelectorAll('#svcTable tr[data-row]').forEach(tr => {
      const hit =
        (tr.dataset.group || '').includes(q) ||
        (tr.dataset.name  || '').includes(q) ||
        (tr.dataset.remote|| '').includes(q);
      tr.style.display = (!q || hit) ? '' : 'none';
    });
  });

  // ===================== Import wizard open =====================
  const btnOpen = document.getElementById('btnOpenImportWizard');
  const wizardEl = document.getElementById('importWizard');
  const wizard = wizardEl ? bootstrap.Modal.getOrCreateInstance(wizardEl) : null;

  // ===================== Wizard helpers =====================
  const wizSearch = document.getElementById('wizSearch');
  const wizChecks = () => Array.from(document.querySelectorAll('.wiz-check'));
  const wizSelected = () => wizChecks().filter(x => x.checked && !x.disabled).map(x => x.value);

  function updateCount(){
    const n = wizSelected().length;
    const out = document.getElementById('wizSelectedCount');
    if(out) out.innerText = `${n} selected`;
  }

  function markAsAdded(remoteIds){
    (remoteIds || []).forEach(id => {
      const rid = String(id || '').trim();
      if(!rid) return;

      // زر clone في الجدول الخلفي
      const row = document.querySelector(`#svcTable tr[data-remote-id="${CSS.escape(rid)}"]`);
      if(row){
        const btn = row.querySelector('.clone-btn');
        if(btn){
          btn.classList.remove('btn-success','btn-secondary','btn-danger','btn-warning','btn-info','btn-dark','btn-primary');
          btn.classList.add('btn-outline-primary');
          btn.innerText = 'Added ✅';
          btn.disabled = true;
          btn.removeAttribute('data-create-service');
        }
      }

      // checkbox في الـ wizard (إذا كانت غير مضافة من السيرفر أصلاً)
      document.querySelectorAll('.wiz-check').forEach(cb => {
        if(String(cb.value) === rid){
          cb.checked = false;
          cb.disabled = true;
          // اخفاءه بالكامل حسب طلبك
          cb.closest('td') && (cb.closest('td').innerHTML = '<span class="badge bg-success">Added</span>');
        }
      });
    });

    updateCount();
  }

  // ==========================================================
  // ✅ NEW: تطبيق "الذاكرة" قبل الفتح (Clone ثم Import مباشرة)
  // ==========================================================
  function applyAddedFromMemory(){
    const mem = window.__gsmmixAdded || {};
    const prefix = `${String(providerId)}:${String(kind)}:`;
    const ids = Object.keys(mem)
      .filter(k => k.startsWith(prefix))
      .map(k => k.substring(prefix.length))
      .filter(Boolean);

    if (ids.length) markAsAdded(ids);
  }

  btnOpen?.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    applyAddedFromMemory(); // ✅ قبل الفتح
    wizard?.show();
    updateCount();
  });

  wizardEl?.addEventListener('shown.bs.modal', () => {
    applyAddedFromMemory(); // ✅ احتياط
    updateCount();
  });

  wizSearch?.addEventListener('input', () => {
    const q = (wizSearch.value || '').trim().toLowerCase();
    document.querySelectorAll('#wizTable tr[data-wiz-row]').forEach(tr => {
      const hit =
        (tr.dataset.group || '').includes(q) ||
        (tr.dataset.name  || '').includes(q) ||
        (tr.dataset.remote|| '').includes(q);
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
    if (e.target && e.target.classList && e.target.classList.contains('wiz-check')) updateCount();
  });

  // ===================== Import submit =====================
  const importUrl  = @json(route('admin.apis.services.import_wizard', $provider));
  const csrfToken  = @json(csrf_token());

  async function sendImport(payload){
    const res = await fetch(importUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With':'XMLHttpRequest'
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

  // ✅ استمع لحدث “تم إنشاء خدمة بالـ Clone”
  window.addEventListener('gsmmix:service-created', (ev) => {
    const d = ev?.detail || {};
    if (String(d.provider_id) !== String(providerId)) return;
    if (String(d.kind) !== String(kind)) return;
    if (!d.remote_id) return;

    window.__gsmmixAdded = window.__gsmmixAdded || {};
    window.__gsmmixAdded[`${String(providerId)}:${String(kind)}:${String(d.remote_id)}`] = true;

    markAsAdded([d.remote_id]);
    updateCount();
  });

  document.getElementById('wizImportSelected')?.addEventListener('click', async () => {
    const ids = wizSelected();
    if(!ids.length) return alert('Select services first');

    const pricing_mode  = document.getElementById('wizPricingMode')?.value || 'percent';
    const pricing_value = document.getElementById('wizPricingValue')?.value || 0;

    const data = await sendImport({
      kind: 'server',
      apply_all: false,
      service_ids: ids,
      pricing_mode,
      pricing_value
    });

    if(data?.ok){
      (data.added_remote_ids || ids).forEach(rid=>{
        window.__gsmmixAdded = window.__gsmmixAdded || {};
        window.__gsmmixAdded[`${String(providerId)}:${String(kind)}:${String(rid)}`] = true;
      });

      markAsAdded(data.added_remote_ids || ids);
      wizard?.hide();
    }
  });

  document.getElementById('wizImportAll')?.addEventListener('click', async () => {
    if(!confirm('Import ALL services?')) return;

    const pricing_mode  = document.getElementById('wizPricingMode')?.value || 'percent';
    const pricing_value = document.getElementById('wizPricingValue')?.value || 0;

    const data = await sendImport({
      kind: 'server',
      apply_all: true,
      service_ids: [],
      pricing_mode,
      pricing_value
    });

    if(data?.ok){
      (data.added_remote_ids || []).forEach(rid=>{
        window.__gsmmixAdded = window.__gsmmixAdded || {};
        window.__gsmmixAdded[`${String(providerId)}:${String(kind)}:${String(rid)}`] = true;
      });

      markAsAdded(data.added_remote_ids || []);
      wizard?.hide();
    }
  });

  // ✅ تطبيق مبكر عند تحميل الملف
  applyAddedFromMemory();

})();
</script>
