{{-- resources/views/admin/api/remote/server/modal.blade.php --}}
@php
  if (!isset($groups) || !($groups instanceof \Illuminate\Support\Collection)) $groups = collect();
  if (!isset($existing) || !is_array($existing)) $existing = [];
@endphp

<style>
  .wiz-groups-box{border:1px solid #eee;border-radius:.5rem;padding:.75rem;background:#fafafa}
  .wiz-pricing-row{border-bottom:1px solid #eee;padding:.6rem 0}
  .wiz-pricing-row:last-child{border-bottom:0}
  .wiz-pricing-title{font-weight:600;margin-bottom:.35rem}
  .wiz-pricing-inputs{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
</style>

<div class="modal-header align-items-center" style="background:#111;color:#fff;">
  <div>
    <div class="h6 mb-0">{{ $provider->name }} | SERVER remote services</div>
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
            $info = (string)($svc->info ?? '');
            $infoB64 = base64_encode($info);
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
                        data-info="{{ e($info) }}"
                         data-info-b64="{{ e($infoB64) }}"
                        data-additional-fields="{{ e($afJson) }}"
                          data-provider-base-url="{{ e(rtrim((string)$provider->url, "/")) }}">
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

        {{-- ✅ Groups Pricing --}}
        <div class="wiz-groups-box mb-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Groups Pricing</div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="wizGroupsResetAll">Reset</button>
          </div>
          <div class="small text-muted mt-1">
            سيتم تطبيق إعدادات الجروبات على <b>كل الخدمات</b> التي تستوردها الآن.
            <br>
            <b>Auto price</b> = يأخذ سعر الخدمة النهائي (Cost + Profit) لكل خدمة تلقائيًا، ثم يطبق الخصم لكل Group.
          </div>
          <div id="wizGroupsWrap" class="mt-2"></div>
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

{{-- Global modal + JS --}}
@include('admin.partials.service-modal')

<script>
(function(){
  const kind = 'server';
  const providerId = @json($provider->id);

  // Search in remote table
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

  // Import wizard open
  const btnOpen = document.getElementById('btnOpenImportWizard');
  const wizardEl = document.getElementById('importWizard');
  const wizard = wizardEl ? bootstrap.Modal.getOrCreateInstance(wizardEl) : null;

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

      document.querySelectorAll('.wiz-check').forEach(cb => {
        if(String(cb.value) === rid){
          cb.checked = false;
          cb.disabled = true;
          cb.closest('td') && (cb.closest('td').innerHTML = '<span class="badge bg-success">Added</span>');
        }
      });
    });

    updateCount();
  }

  function applyAddedFromMemory(){
    const mem = window.__gsmmixAdded || {};
    const prefix = `${String(providerId)}:${String(kind)}:`;
    const ids = Object.keys(mem)
      .filter(k => k.startsWith(prefix))
      .map(k => k.substring(prefix.length))
      .filter(Boolean);

    if (ids.length) markAsAdded(ids);
  }

  btnOpen?.addEventListener('click', async (e) => {
    e.preventDefault();
    e.stopPropagation();

    applyAddedFromMemory();
    await initWizardGroups();
    wizard?.show();
    updateCount();
  });

  wizardEl?.addEventListener('shown.bs.modal', () => {
    applyAddedFromMemory();
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

  // ===== Groups pricing (same as other modals) =====
  const groupsUrl = @json(route('admin.groups.options'));
  let __wizGroupsLoaded = false;

  async function loadUserGroups(){
    const res = await fetch(groupsUrl, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const rows = await res.json().catch(()=>[]);
    return Array.isArray(rows) ? rows : [];
  }

  function escapeHtml(s){
    const str = String(s ?? '');
    return str.replace(/[&<>"']/g, (m) => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[m]));
  }

  function buildGroupsUI(groups){
    const wrap = document.getElementById('wizGroupsWrap');
    if(!wrap) return;

    wrap.innerHTML = '';
    groups.forEach(g=>{
      const row = document.createElement('div');
      row.className = 'wiz-pricing-row';
      row.dataset.groupId = g.id;

      row.innerHTML = `
        <div class="wiz-pricing-title">${escapeHtml(g.name || ('Group #' + g.id))}</div>

        <div class="wiz-pricing-inputs">
          <div>
            <label class="form-label mb-1">Price</label>
            <div class="input-group">
              <input type="number" step="0.0001" class="form-control"
                     data-price value="0.0000" disabled>
              <span class="input-group-text">Credits</span>
            </div>

            <div class="form-check mt-1">
              <input class="form-check-input" type="checkbox" data-auto-price checked>
              <label class="form-check-label small">Auto price (service final price)</label>
            </div>
          </div>

          <div>
            <label class="form-label mb-1">Discount</label>
            <div class="input-group">
              <input type="number" step="0.0001" class="form-control" data-discount value="0.0000">
              <select class="form-select" style="max-width:120px" data-discount-type>
                <option value="1" selected>Credits</option>
                <option value="2">Percent</option>
              </select>
              <button type="button" class="btn btn-light" data-reset>Reset</button>
            </div>
          </div>
        </div>
      `;

      const autoCb = row.querySelector('[data-auto-price]');
      const price  = row.querySelector('[data-price]');
      const reset  = row.querySelector('[data-reset]');

      const syncPriceState = () => {
        const isAuto = !!autoCb?.checked;
        if(price){
          price.disabled = isAuto;
          if(isAuto) price.value = "0.0000";
        }
      };

      autoCb?.addEventListener('change', syncPriceState);
      reset?.addEventListener('click', ()=>{
        row.querySelector('[data-discount]').value = "0.0000";
        row.querySelector('[data-discount-type]').value = "1";
        autoCb.checked = true;
        syncPriceState();
      });

      syncPriceState();
      wrap.appendChild(row);
    });
  }

  function collectGroupPrices(){
    const wrap = document.getElementById('wizGroupsWrap');
    if(!wrap) return [];

    const out = [];
    wrap.querySelectorAll('.wiz-pricing-row').forEach(row=>{
      const group_id = Number(row.dataset.groupId || 0);
      if(!group_id) return;

      const auto_price = row.querySelector('[data-auto-price]')?.checked ? 1 : 0;
      const price = Number(row.querySelector('[data-price]')?.value || 0);
      const discount = Number(row.querySelector('[data-discount]')?.value || 0);
      const discount_type = Number(row.querySelector('[data-discount-type]')?.value || 1);

      out.push({
        group_id,
        auto_price,
        price: Number.isFinite(price) ? price : 0,
        discount: Number.isFinite(discount) ? discount : 0,
        discount_type: (discount_type === 2 ? 2 : 1),
      });
    });

    return out;
  }

  async function initWizardGroups(){
    if(__wizGroupsLoaded) return;
    const groups = await loadUserGroups();
    buildGroupsUI(groups);
    __wizGroupsLoaded = true;

    document.getElementById('wizGroupsResetAll')?.addEventListener('click', ()=>{
      document.querySelectorAll('#wizGroupsWrap .wiz-pricing-row').forEach(row=>{
        row.querySelector('[data-discount]').value = "0.0000";
        row.querySelector('[data-discount-type]').value = "1";
        row.querySelector('[data-auto-price]').checked = true;
        row.querySelector('[data-price]').value = "0.0000";
        row.querySelector('[data-price]').disabled = true;
      });
    });
  }

  // Import submit
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

    const group_prices = collectGroupPrices();

    const data = await sendImport({
      kind: 'server',
      apply_all: false,
      service_ids: ids,
      pricing_mode,
      pricing_value,
      group_prices
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

    const group_prices = collectGroupPrices();

    const data = await sendImport({
      kind: 'server',
      apply_all: true,
      service_ids: [],
      pricing_mode,
      pricing_value,
      group_prices
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

  applyAddedFromMemory();
})();
</script>
