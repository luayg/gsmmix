{{-- resources/views/admin/partials/service-modal.blade.php --}}
@once
  @push('styles')
<style>
  #serviceModal .modal-dialog{width:96vw;max-width:min(1400px,96vw);margin:1rem auto}
  #serviceModal .modal-content{display:flex;flex-direction:column;max-height:96dvh;border-radius:.6rem;overflow:hidden}
  #serviceModal .modal-header{background:#3bb37a;color:#fff;padding:.75rem 1rem;border:0}
  #serviceModal .modal-title{font-weight:600}
  #serviceModal .modal-body{flex:1 1 auto;overflow:auto;padding:1rem;background:#fff}
  #serviceModal .tabs-top{display:flex;gap:.5rem;margin-left:auto}
  #serviceModal .tabs-top button{border:0;background:#ffffff22;color:#fff;padding:.35rem .8rem;border-radius:.35rem}
  #serviceModal .tabs-top button.active{background:#fff;color:#000}
  #serviceModal .badge-box{display:flex;gap:.4rem;align-items:center;margin-left:1rem}
  #serviceModal .badge-box .badge{background:#111;color:#fff;padding:.35rem .55rem;border-radius:.35rem;font-size:.75rem}
  #serviceModal .tab-pane{display:none}
  #serviceModal .tab-pane.active{display:block}

  /* Pricing layout */
  #serviceModal .pricing-row{border-bottom:1px solid #eee}
  #serviceModal .pricing-title{background:#f3f3f3;padding:.55rem .75rem;font-weight:600}
  #serviceModal .pricing-inputs{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;padding:.65rem .75rem}
</style>
  @endpush

  @push('modals')
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <div>
          <div class="modal-title">Create service</div>
          <div class="small opacity-75" id="serviceModalSubtitle">Provider: — | Remote ID: —</div>
        </div>

        <div class="tabs-top">
          <button type="button" class="tab-btn active" data-tab="general">General</button>
          <button type="button" class="tab-btn" data-tab="additional">Additional</button>
          <button type="button" class="tab-btn" data-tab="meta">Meta</button>
        </div>

        <div class="badge-box">
          <span class="badge" id="badgeType">Type: —</span>
          <span class="badge" id="badgePrice">Price: —</span>
        </div>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="serviceModalBody"></div>
    </div>
  </div>
</div>
  @endpush

  @push('scripts')
<script>
(function(){

  // ✅ Tabs
  function initTabs(scope){
    const btns = document.querySelectorAll('#serviceModal .tab-btn');
    btns.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        btns.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');

        scope.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
        const pane = scope.querySelector('.tab-pane[data-tab="'+btn.dataset.tab+'"]');
        if(pane) pane.classList.add('active');
      });
    });
  }

  // ✅ Load libs once
  const loadCssOnce=(id,href)=>{ if(document.getElementById(id)) return;
    const l=document.createElement('link'); l.id=id; l.rel='stylesheet'; l.href=href; document.head.appendChild(l);
  };
  const loadScriptOnce=(id,src)=>new Promise((res,rej)=>{
    if(document.getElementById(id)) return res();
    const s=document.createElement('script'); s.id=id; s.src=src; s.async=false;
    s.onload=res; s.onerror=rej; document.body.appendChild(s);
  });

  async function ensureSummernote(){
    loadCssOnce('sn-css','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');
    if(!window.jQuery || !window.jQuery.fn?.summernote){
      await loadScriptOnce('jq','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
      window.$=window.jQuery;
      await loadScriptOnce('sn','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');
    }
  }

  async function ensureSelect2(){
    if(!window.jQuery) await loadScriptOnce('jq','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
    if(!$.fn?.select2){
      loadCssOnce('sel2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
      await loadScriptOnce('sel2','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
    }
  }

  // ✅ slugify
  function slugify(text){
    return String(text||'')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g,'-')
      .replace(/^-+|-+$/g,'');
  }

  // ✅ Price
  function initPrice(scope){
    const cost = scope.querySelector('[name="cost"]');
    const profit = scope.querySelector('[name="profit"]');
    const pType = scope.querySelector('[name="profit_type"]');
    const pricePreview = scope.querySelector('#pricePreview');
    const convertedPreview = scope.querySelector('#convertedPricePreview');

    function recalc(){
      const c = Number(cost?.value||0);
      const p = Number(profit?.value||0);
      const isPercent = (pType?.value == '2');
      const price = isPercent ? (c + (c*p/100)) : (c+p);

      if(pricePreview) pricePreview.value = price.toFixed(4);
      if(convertedPreview) convertedPreview.value = price.toFixed(4);

      const badge = document.getElementById('badgePrice');
      if(badge) badge.innerText = 'Price: ' + price.toFixed(4) + ' Credits';
    }

    [cost,profit,pType].forEach(el=> el && el.addEventListener('input', recalc));
    recalc();

    return {
      setCost(v){ if(cost){ cost.value = Number(v||0).toFixed(4); recalc(); } }
    };
  }

  // ✅ Build pricing table from USER GROUPS
  function buildPricingTable(scope, groups){
    const wrap = scope.querySelector('#groupsPricingWrap');
    const hidden = scope.querySelector('#pricingTableHidden');
    if(!wrap || !hidden) return;

    wrap.innerHTML = '';

    function updateHidden(){
      const rows = [];
      wrap.querySelectorAll('.pricing-row').forEach(row=>{
        rows.push({
          group_id: row.dataset.groupId,
          price: row.querySelector('[data-price]')?.value || 0,
          discount: row.querySelector('[data-discount]')?.value || 0,
          discount_type: row.querySelector('[data-discount-type]')?.value || 1
        });
      });
      hidden.value = JSON.stringify(rows);
    }

    groups.forEach(g=>{
      const row = document.createElement('div');
      row.className = 'pricing-row';
      row.dataset.groupId = g.id;

      row.innerHTML = `
        <div class="pricing-title">${g.name}</div>
        <div class="pricing-inputs">
          <div>
            <label class="form-label">Price</label>
            <div class="input-group">
              <input type="number" step="0.0001" class="form-control" data-price value="0.0000">
              <span class="input-group-text">Credits</span>
            </div>
          </div>

          <div>
            <label class="form-label">Discount</label>
            <div class="input-group">
              <input type="number" step="0.0001" class="form-control" data-discount value="0.0000">
              <select class="form-select" style="max-width:110px" data-discount-type>
                <option value="1" selected>Credits</option>
                <option value="2">Percent</option>
              </select>
              <button type="button" class="btn btn-light btn-reset">Reset</button>
            </div>
          </div>
        </div>
      `;

      row.querySelector('.btn-reset').addEventListener('click', ()=>{
        row.querySelector('[data-price]').value = "0.0000";
        row.querySelector('[data-discount]').value = "0.0000";
        row.querySelector('[data-discount-type]').value = "1";
        updateHidden();
      });

      row.querySelectorAll('input,select').forEach(el=>{
        el.addEventListener('input', updateHidden);
        el.addEventListener('change', updateHidden);
      });

      wrap.appendChild(row);
    });

    updateHidden();
  }

  // ✅ Load USER GROUPS (Basic/VIP/Reseller..)
  async function loadUserGroups(){
    const res = await fetch("{{ route('admin.groups.options') }}");
    const rows = await res.json();
    if(!Array.isArray(rows)) return [];
    return rows;
  }

  // ✅ Load SERVICE GROUPS for select (ServiceGroup table)
  async function loadServiceGroups(type){
    const url = "{{ route('admin.services.groups.options') }}" + "?type=" + encodeURIComponent(type || 'imei');
    const res = await fetch(url);
    const rows = await res.json();
    return Array.isArray(rows) ? rows : [];
  }

  // ✅ Load API providers
  async function loadApiProviders(){
    const res = await fetch("{{ route('admin.apis.options') }}");
    const rows = await res.json();
    return Array.isArray(rows) ? rows : [];
  }

  // ✅ Load provider services list (remote services)
  async function loadProviderServices(providerId, type, q=''){
    if(!providerId) return [];
    const url = "{{ route('admin.services.clone.provider_services') }}"
      + "?provider_id=" + encodeURIComponent(providerId)
      + "&type=" + encodeURIComponent(type || 'imei')
      + "&q=" + encodeURIComponent(q || '');

    const res = await fetch(url);
    const rows = await res.json();
    return Array.isArray(rows) ? rows : [];
  }

  // ✅ open modal
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-create-service]');
    if(!btn) return;

    e.preventDefault();

    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

    initTabs(body);
    await ensureSummernote();
    await ensureSelect2();

    // ✅ Summernote
    jQuery(body).find('#infoEditor').summernote({
      placeholder:'Description, notes, terms…',
      height:320
    });

    // ✅ detect type + clone
    const serviceType = (btn.dataset.serviceType || 'imei').toLowerCase();

    const providerId = btn.dataset.providerId;
    const remoteId   = btn.dataset.remoteId;

    const isClone =
      providerId !== undefined && providerId !== '' && providerId !== 'undefined' &&
      remoteId   !== undefined && remoteId   !== '' && remoteId   !== 'undefined';

    const cloneData = {
      providerId: isClone ? providerId : '',
      providerName: btn.dataset.providerName || '—',
      remoteId: isClone ? remoteId : '',
      name: btn.dataset.name || '',
      credit: Number(btn.dataset.credit || 0),
      time: btn.dataset.time || '',
      serviceType
    };

    // header
    document.getElementById('serviceModalSubtitle').innerText =
      isClone
        ? `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`
        : `Provider: — | Remote ID: —`;

    document.getElementById('badgeType').innerText =
      `Type: ${cloneData.serviceType.toUpperCase()}`;

    // fill fields
    const elSupplier = body.querySelector('[name="supplier_id"]');
    const elRemote   = body.querySelector('[name="remote_id"]');
    const elName     = body.querySelector('[name="name"]');
    const elTime     = body.querySelector('[name="time"]');
    const elCost     = body.querySelector('[name="cost"]');
    const elProfit   = body.querySelector('[name="profit"]');
    const elSource   = body.querySelector('[name="source"]');
    const elType     = body.querySelector('[name="type"]');
    const elAlias    = body.querySelector('[name="alias"]');

    if(elSupplier) elSupplier.value = cloneData.providerId || '';
    if(elRemote)   elRemote.value   = cloneData.remoteId   || '';
    if(elName)     elName.value     = cloneData.name || '';
    if(elTime)     elTime.value     = cloneData.time || '';
    if(elCost)     elCost.value     = Number(cloneData.credit || 0).toFixed(4);
    if(elProfit)   elProfit.value   = '0.0000';
    if(elType)     elType.value     = cloneData.serviceType;

    // ✅ default source:
    // clone => API (2)
    // manual add => Manual (1)
    if(elSource) elSource.value = isClone ? 2 : 1;

    // ✅ alias: initial + auto-update (if user keeps it empty)
    if(elAlias) elAlias.value = slugify(cloneData.name || '');
    if(elName && elAlias){
      elName.addEventListener('input', ()=>{
        if(!elAlias.value) elAlias.value = slugify(elName.value);
      });
    }

    const priceHelper = initPrice(body);
    priceHelper.setCost(cloneData.credit);

    // ✅ Fill SERVICE GROUPS dropdown
    try{
      const groups = await loadServiceGroups(cloneData.serviceType);
      const sel = body.querySelector('[name="group_id"]');
      if(sel){
        sel.innerHTML =
          `<option value="">Group</option>` +
          groups.map(g=>`<option value="${g.id}">${g.name}</option>`).join('');
      }
    }catch(err){
      console.error('Failed to load service groups', err);
    }

    // ✅ API block logic
    const apiBlock    = body.querySelector('.js-api-block');
    const apiProvider = body.querySelector('.js-api-provider');
    const apiService  = body.querySelector('.js-api-service');

    let servicesCache = new Map(); // id -> row

    async function fillApiProviders(selectedId=''){
      if(!apiProvider) return;
      const rows = await loadApiProviders();
      apiProvider.innerHTML =
        `<option value="">Select provider</option>` +
        rows.map(r=>`<option value="${r.id}">${r.name}</option>`).join('');
      if(selectedId) apiProvider.value = selectedId;

      // select2 (optional)
      try{
        jQuery(apiProvider).select2({ dropdownParent: jQuery('#serviceModal') });
      }catch(e){}
    }

    async function fillApiServices(providerId, selectedRemoteId=''){
      if(!apiService) return;

      servicesCache.clear();
      apiService.innerHTML = `<option value="">Select service</option>`;

      if(!providerId) return;

      const rows = await loadProviderServices(providerId, cloneData.serviceType);
      rows.forEach(r=> servicesCache.set(String(r.id), r));

      apiService.innerHTML =
        `<option value="">Select service</option>` +
        rows.map(r=>`<option value="${r.id}">${r.name}</option>`).join('');

      if(selectedRemoteId) apiService.value = String(selectedRemoteId);

      try{
        jQuery(apiService).select2({ dropdownParent: jQuery('#serviceModal') });
      }catch(e){}
    }

    function toggleApiUI(){
      if(!apiBlock || !elSource) return;
      const isApi = String(elSource.value) === '2';

      apiBlock.classList.toggle('d-none', !isApi);

      if(isApi){
        // load providers first time
        fillApiProviders(isClone ? String(cloneData.providerId) : '');
        if(isClone){
          // load services and preselect remote service
          fillApiServices(String(cloneData.providerId), String(cloneData.remoteId));
        }
      }else{
        // clear API selects when not API
        if(apiProvider) apiProvider.innerHTML = '';
        if(apiService) apiService.innerHTML = '';
      }
    }

    if(elSource){
      elSource.addEventListener('change', async ()=>{
        toggleApiUI();
      });
    }

    if(apiProvider){
      apiProvider.addEventListener('change', async ()=>{
        await fillApiServices(apiProvider.value, '');
      });
    }

    if(apiService){
      apiService.addEventListener('change', ()=>{
        const row = servicesCache.get(String(apiService.value));
        if(!row) return;

        // (Optional) auto-fill basic fields when selecting API service
        if(elName && !elName.value) elName.value = row.name || '';
        if(elTime && !elTime.value) elTime.value = row.time || '';
        if(elCost) {
          const c = Number(row.credit || 0);
          elCost.value = c.toFixed(4);
          priceHelper.setCost(c);
        }

        // ✅ ensure hidden fields also updated (not required, backend maps api_* anyway)
        if(elSupplier) elSupplier.value = apiProvider?.value || '';
        if(elRemote)   elRemote.value   = apiService.value || '';

        // alias if empty
        if(elAlias && !elAlias.value && elName?.value){
          elAlias.value = slugify(elName.value);
        }
      });
    }

    // run initial toggle
    toggleApiUI();

    // ✅ Load USER GROUPS for pricing table
    try{
      const userGroups = await loadUserGroups();
      buildPricingTable(body, userGroups);
    }catch(err){
      console.error('Failed to load user groups', err);
    }

    // ✅ Add field handler (next)
    body.querySelector('#btnAddField')?.addEventListener('click', ()=>{
      alert("✅ Add field clicked — implement full custom fields system next.");
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
  });

  // ✅ Submit
  document.addEventListener('submit', async (ev)=>{
    const form = ev.target.closest('#serviceModal form[data-ajax="1"]');
    if(!form) return;

    ev.preventDefault();

    const html = jQuery(form).find('#infoEditor').summernote('code');
    form.querySelector('#infoHidden').value = html;

    const btn = form.querySelector('[type="submit"]');
    btn.disabled = true;

    try{
      const res = await fetch(form.action,{
        method: form.method,
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
        },
        body: new FormData(form)
      });

      btn.disabled = false;

      if(res.status === 422){
        const json = await res.json();
        alert(Object.values(json.errors).flat().join("\n"));
        return;
      }

      if(res.ok){
        bootstrap.Modal.getInstance(document.getElementById('serviceModal')).hide();
        location.reload();
      }else{
        alert('Failed to save service');
      }

    }catch(e){
      btn.disabled = false;
      alert('Network error');
    }
  });

})();
</script>
  @endpush
@endonce
