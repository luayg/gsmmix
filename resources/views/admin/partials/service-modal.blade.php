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
      setCost(v){
        if(cost) cost.value = Number(v||0).toFixed(4);
        recalc();
      }
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

  // ✅ Insert API Provider dropdown near Source (if not in template)
  function ensureApiProviderUI(scope){
    const sourceSel = scope.querySelector('[name="source"]');
    if(!sourceSel) return;

    // If already exists, skip
    if(scope.querySelector('#apiProviderWrap')) return;

    const wrap = document.createElement('div');
    wrap.id = 'apiProviderWrap';
    wrap.className = 'mt-2';
    wrap.style.display = 'none';
    wrap.innerHTML = `
      <label class="form-label">API Provider</label>
      <select class="form-select" name="api_provider_id">
        <option value="">-- Choose API Provider --</option>
        @php
          $providers = \App\Models\ApiProvider::orderBy('id')->get();
        @endphp
        @foreach($providers as $p)
          @php
            $nm = $p->name;
            if (is_string($nm)) {
              $decoded = json_decode($nm, true);
              if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $nm = $decoded['en'] ?? ($decoded['fallback'] ?? $p->id);
              }
            } elseif (is_array($nm)) {
              $nm = $nm['en'] ?? ($nm['fallback'] ?? $p->id);
            }
          @endphp
          <option value="{{ $p->id }}">{{ $nm }}</option>
        @endforeach
      </select>
      <div class="form-text">
        إذا كنت تعمل Clone من Remote Service سيتم تعبئته تلقائياً.
        إذا اخترت API يدويًا، اختر المزود ثم (اختياريًا) ضع Remote ID من الريموت.
      </div>
    `;

    // place it right after source select container
    const container = sourceSel.closest('.mb-3') || sourceSel.closest('.form-group') || sourceSel.parentElement;
    if(container && container.parentElement){
      container.parentElement.insertBefore(wrap, container.nextSibling);
    }else{
      sourceSel.parentElement.appendChild(wrap);
    }
  }

  function updateSourceUI(scope){
    const sourceSel = scope.querySelector('[name="source"]');
    const apiWrap = scope.querySelector('#apiProviderWrap');
    if(!sourceSel || !apiWrap) return;

    const isApi = String(sourceSel.value) === '2';
    apiWrap.style.display = isApi ? 'block' : 'none';

    // if switching to manual, clear api-related hidden fields to avoid bad values
    if(!isApi){
      const sup = scope.querySelector('[name="supplier_id"]');
      const rem = scope.querySelector('[name="remote_id"]');
      const ap  = scope.querySelector('[name="api_provider_id"]');
      if(sup) sup.value = '';
      if(rem) rem.value = '';
      if(ap)  ap.value = '';
    }
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
      serviceType: (btn.dataset.serviceType || 'imei').toLowerCase()
    };

    // header + badges
    document.getElementById('serviceModalSubtitle').innerText =
      isClone
        ? `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`
        : `Provider: — | Remote ID: —`;

    document.getElementById('badgeType').innerText =
      `Type: ${cloneData.serviceType.toUpperCase()}`;

    // fill fields
    body.querySelector('[name="supplier_id"]').value = cloneData.providerId;
    body.querySelector('[name="remote_id"]').value   = cloneData.remoteId;
    body.querySelector('[name="name"]').value        = cloneData.name;
    body.querySelector('[name="time"]').value        = cloneData.time || '';
    body.querySelector('[name="cost"]').value        = cloneData.credit.toFixed(4);
    body.querySelector('[name="profit"]').value      = '0.0000';

    // ✅ Manual = 1, API = 2
    body.querySelector('[name="source"]').value      = isClone ? 2 : 1;

    body.querySelector('[name="type"]').value        = cloneData.serviceType;
    body.querySelector('[name="alias"]').value       = slugify(cloneData.name || '');

    const priceHelper = initPrice(body);
    priceHelper.setCost(cloneData.credit);

    // ✅ Ensure API Provider selector exists
    ensureApiProviderUI(body);

    // bind change
    const sourceSel = body.querySelector('[name="source"]');
    if(sourceSel){
      sourceSel.addEventListener('change', ()=> updateSourceUI(body));
    }
    updateSourceUI(body);

    // if clone, preselect provider in dropdown
    const apiSel = body.querySelector('[name="api_provider_id"]');
    if(apiSel && isClone) apiSel.value = String(cloneData.providerId);

    // ✅ Load service groups dropdown (service_groups)
    fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(cloneData.serviceType))
      .then(r=>r.json())
      .then(rows=>{
        const sel = body.querySelector('[name="group_id"]');
        if(sel){
          sel.innerHTML = `<option value="">Group</option>` + rows.map(g=>`<option value="${g.id}">${g.name}</option>`).join('');
        }
      });

    // ✅ Load USER GROUPS for pricing table
    const userGroups = await loadUserGroups();
    buildPricingTable(body, userGroups);

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

    // push summernote html
    const html = jQuery(form).find('#infoEditor').summernote('code');
    const infoHidden = form.querySelector('#infoHidden');
    if(infoHidden) infoHidden.value = html;

    const btn = form.querySelector('[type="submit"]');
    if(btn) btn.disabled = true;

    try{
      const res = await fetch(form.action,{
        method: form.method,
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
        },
        body: new FormData(form)
      });

      if(btn) btn.disabled = false;

      if(res.status === 422){
        const json = await res.json();
        alert(Object.values(json.errors).flat().join("\n"));
        return;
      }

      if(res.ok){
        bootstrap.Modal.getInstance(document.getElementById('serviceModal')).hide();
        location.reload();
      }else{
        // ✅ Show server response snippet for debugging
        const text = await res.text();
        alert('Failed to save service\n\n' + text.slice(0, 800));
      }

    }catch(e){
      if(btn) btn.disabled = false;
      alert('Network error');
    }
  });

})();
</script>
  @endpush
@endonce
