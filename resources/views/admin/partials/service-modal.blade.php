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

  /* Pricing style */
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

  // ✅ Tabs (fixed scope)
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

  function slugify(text){
    return String(text||'')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g,'-')
      .replace(/^-+|-+$/g,'');
  }

  // ✅ Price calc
  function initPrice(scope){
    const cost = scope.querySelector('[name="cost"]');
    const profit = scope.querySelector('[name="profit"]');
    const pType = scope.querySelector('[name="profit_type"]');
    const pricePreview = scope.querySelector('#pricePreview');
    const convertedPreview = scope.querySelector('#convertedPricePreview');

    function recalc(){
      const c = Number(cost.value||0);
      const p = Number(profit.value||0);
      const isPercent = (pType.value == '2');
      const price = isPercent ? (c + (c*p/100)) : (c+p);

      if(pricePreview) pricePreview.value = price.toFixed(4);
      if(convertedPreview) convertedPreview.value = price.toFixed(4);

      document.getElementById('badgePrice').innerText =
        'Price: ' + price.toFixed(4) + ' Credits';
    }

    [cost,profit,pType].forEach(el=> el && el.addEventListener('input', recalc));
    recalc();

    return {
      setCost(v){ cost.value = Number(v||0).toFixed(4); recalc(); }
    };
  }

  // ✅ Pricing UI => writes into hidden pricing_table
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

  // ✅ Open modal
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-create-service]');
    if(!btn) return;

    e.preventDefault();

    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

    async function loadPricingGroups(serviceType){
  const wrap = body.querySelector('#groupsPricingList');
  if(!wrap) return;

  wrap.innerHTML = `<div class="text-muted small">Loading groups...</div>`;

  try{
    const res = await fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(serviceType));
    const groups = await res.json();

    if(!Array.isArray(groups) || groups.length === 0){
      wrap.innerHTML = `<div class="text-danger small">No groups found</div>`;
      return;
    }

    // ✅ build pricing UI
    wrap.innerHTML = groups.map(g => `
      <div class="mb-3 border rounded">
        <div class="bg-light px-3 py-2 fw-bold">${g.name}</div>

        <div class="p-3">
          <div class="row g-2 align-items-end">
            <div class="col-md-4">
              <label class="form-label mb-1">Price</label>
              <div class="input-group">
                <input type="number" step="0.0001"
                       class="form-control grp-price"
                       data-group-id="${g.id}"
                       value="0.0000">
                <span class="input-group-text">Credits</span>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label mb-1">Discount</label>
              <div class="input-group">
                <input type="number" step="0.0001"
                       class="form-control grp-discount"
                       data-group-id="${g.id}"
                       value="0.0000">
                <select class="form-select grp-discount-type"
                        data-group-id="${g.id}"
                        style="max-width:120px">
                  <option value="1">Credits</option>
                  <option value="2">Percent</option>
                </select>
                <button type="button" class="btn btn-light btn-sm grp-reset"
                        data-group-id="${g.id}">
                  Reset
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>
    `).join('');

    // reset buttons
    wrap.querySelectorAll('.grp-reset').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const gid = btn.dataset.groupId;
        wrap.querySelector(`.grp-price[data-group-id="${gid}"]`).value = "0.0000";
        wrap.querySelector(`.grp-discount[data-group-id="${gid}"]`).value = "0.0000";
        wrap.querySelector(`.grp-discount-type[data-group-id="${gid}"]`).value = "1";
      });
    });

  }catch(e){
    console.error(e);
    wrap.innerHTML = `<div class="text-danger small">Failed to load groups</div>`;
  }
}



    initTabs(body);

    await ensureSummernote();
    await ensureSelect2();

    jQuery(body).find('#infoEditor').summernote({
      placeholder:'Description, notes, terms…',
      height:320
    });

    // ✅ normalize type
    const serviceType = (btn.dataset.serviceType || 'imei').toLowerCase();

    const cloneData = {
      providerId: btn.dataset.providerId,
      providerName: btn.dataset.providerName,
      remoteId: btn.dataset.remoteId,
      name: btn.dataset.name,
      credit: Number(btn.dataset.credit||0),
      time: btn.dataset.time,
      serviceType
    };

    document.getElementById('serviceModalSubtitle').innerText =
      `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`;

    document.getElementById('badgeType').innerText =
      `Type: ${cloneData.serviceType.toUpperCase()}`;

    // Fill fields
    body.querySelector('[name="supplier_id"]').value = cloneData.providerId;
    body.querySelector('[name="remote_id"]').value   = cloneData.remoteId;
    body.querySelector('[name="name"]').value        = cloneData.name;
    body.querySelector('[name="time"]').value        = cloneData.time || '';
    body.querySelector('[name="cost"]').value        = cloneData.credit.toFixed(4);
    body.querySelector('[name="profit"]').value      = '0.0000';
    body.querySelector('[name="source"]').value      = 2;
    body.querySelector('[name="type"]').value        = cloneData.serviceType;

    body.querySelector('[name="alias"]').value = slugify(cloneData.name);

    const priceHelper = initPrice(body);
    priceHelper.setCost(cloneData.credit);

    // ✅ Load groups + build pricing
    fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(cloneData.serviceType))
      .then(r=>r.json())
      .then(rows=>{
        // dropdown
        const sel = body.querySelector('[name="group_id"]');
        if(sel){
          sel.innerHTML = `<option value="">Group</option>` + rows.map(g=>`<option value="${g.id}">${g.name}</option>`).join('');
        }

        // pricing table
        buildPricingTable(body, rows);
      });

    // ✅ Add field handler (next: implement old system)
    body.querySelector('#btnAddField')?.addEventListener('click', ()=>{
      alert("✅ Add field clicked — implement old custom fields system next.");
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
  });

  // ✅ Ajax submit => summernote content -> hidden
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
