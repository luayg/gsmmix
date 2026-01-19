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

  #serviceModal .pricing-row{border-bottom:1px solid #eee}
  #serviceModal .pricing-title{background:#f3f3f3;padding:.55rem .75rem;font-weight:600}
  #serviceModal .pricing-inputs{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;padding:.65rem .75rem}

  .api-box{
    border:1px solid #e9e9e9;
    border-radius:.5rem;
    padding:.75rem;
    margin-top:.5rem;
    background:#fafafa;
  }
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

  const clean = (v) => {
    if (v === undefined || v === null) return '';
    const s = String(v);
    if (s === 'undefined' || s === 'null') return '';
    return s;
  };
  const escAttr = (s) => clean(s).replaceAll('"','&quot;');

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

    return { setCost(v){ cost.value = Number(v||0).toFixed(4); recalc(); } };
  }

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

  async function loadUserGroups(){
    const res = await fetch("{{ route('admin.groups.options') }}");
    const rows = await res.json();
    if(!Array.isArray(rows)) return [];
    return rows;
  }

  function ensureApiUI(scope){
    const sourceSel = scope.querySelector('[name="source"]');
    if(!sourceSel) return;

    let box = scope.querySelector('#apiBox');
    if(!box){
      box = document.createElement('div');
      box.id = 'apiBox';
      box.className = 'api-box';
      box.innerHTML = `
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">API connection</label>
            <select class="form-select" id="apiProviderSelect">
              <option value="">Select provider...</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">API service</label>
            <select class="form-select" id="apiServiceSelect">
              <option value="">Select service...</option>
            </select>
            <div class="form-text">اختر الخدمة وسيظهر السعر بجانب اسمها، ثم سيتم تعبئة Remote ID + Provider تلقائياً.</div>
          </div>
        </div>
      `;
      sourceSel.closest('.mb-3, .form-group, div')?.appendChild(box);
      if(!box.parentElement) scope.appendChild(box);
    }

    box.style.display = (Number(sourceSel.value) === 2) ? '' : 'none';
  }

  async function loadApiProviders(scope){
    const sel = scope.querySelector('#apiProviderSelect');
    if(!sel) return;

    const res = await fetch("{{ route('admin.apis.options') }}");
    if(!res.ok) return;

    const rows = await res.json();
    sel.innerHTML = `<option value="">Select provider...</option>` +
      (Array.isArray(rows) ? rows.map(r=>`<option value="${r.id}">${clean(r.name)}</option>`).join('') : '');
  }

  async function loadProviderServices(scope, providerId, type){
    const sel = scope.querySelector('#apiServiceSelect');
    if(!sel) return;

    sel.innerHTML = `<option value="">Loading...</option>`;

    const url = new URL("{{ route('admin.services.clone.provider_services') }}", window.location.origin);
    url.searchParams.set('provider_id', providerId);
    url.searchParams.set('type', type);

    const res = await fetch(url.toString());
    if(!res.ok){
      sel.innerHTML = `<option value="">Failed to load</option>`;
      return;
    }

    const rows = await res.json();
    if(!Array.isArray(rows) || rows.length === 0){
      sel.innerHTML = `<option value="">No services found</option>`;
      return;
    }

    sel.innerHTML = `<option value="">Select service...</option>` + rows.map(s=>{
      const rid  = clean(s.remote_id ?? s.id ?? s.service_id);
      const name = clean(s.name);
      const time = clean(s.time ?? s.delivery_time);

      // ✅ FIX: استخدم price أولاً (remote tables) ثم credit/cost
      const creditNum = Number(s.price ?? s.credit ?? s.cost ?? 0);
      const creditTxt = Number.isFinite(creditNum) ? creditNum.toFixed(4) : '0.0000';

      const timeTxt = time ? ` — ${time}` : '';
      const ridTxt  = rid ? ` (#${rid})` : '';

      return `<option value="${rid}"
        data-name="${escAttr(name)}"
        data-credit="${creditTxt}"
        data-time="${escAttr(time)}"
      >${name}${timeTxt} — ${creditTxt} Credits${ridTxt}</option>`;
    }).join('');
  }

  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-create-service]');
    if(!btn) return;

    e.preventDefault();
    window.__serviceReturnUrl = window.location.href;

    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

// ✅ IMPORTANT: execute injected <script> tags inside the template
(function runInjectedScripts(container){
  const scripts = Array.from(container.querySelectorAll('script'));
  scripts.forEach(old => {
    const s = document.createElement('script');

    // copy attributes (if any)
    for (const attr of old.attributes) s.setAttribute(attr.name, attr.value);

    s.text = old.textContent || '';
    old.parentNode?.removeChild(old);
    container.appendChild(s);
  });
})(body);


    initTabs(body);
    await ensureSummernote();
    await ensureSelect2();

    jQuery(body).find('#infoEditor').summernote({ placeholder:'Description, notes, terms…', height:320 });

    const providerId = btn.dataset.providerId;
    const remoteId   = btn.dataset.remoteId;

    const isClone = (providerId !== undefined && providerId !== '' && providerId !== 'undefined'
                  && remoteId   !== undefined && remoteId   !== '' && remoteId   !== 'undefined');

    // ✅ FIX: اجلب providerName من data-provider-name أو من عنوان الصفحة
    const providerName =
      btn.dataset.providerName ||
      document.querySelector('.card-header h5')?.textContent?.split('|')?.[0]?.trim() ||
      '—';

    const cloneData = {
      providerId: isClone ? providerId : '',
      providerName,
      remoteId: isClone ? remoteId : '',
      groupName: clean(btn.dataset.groupName || ''), // ✅ FIX
      name: btn.dataset.name || '',
      credit: Number(btn.dataset.credit || 0),
      time: btn.dataset.time || '',
      serviceType: (btn.dataset.serviceType || 'imei').toLowerCase()
    };

    document.getElementById('serviceModalSubtitle').innerText =
      isClone ? `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`
              : `Provider: — | Remote ID: —`;

    document.getElementById('badgeType').innerText = `Type: ${cloneData.serviceType.toUpperCase()}`;

    body.querySelector('[name="supplier_id"]').value = isClone ? cloneData.providerId : '';
    body.querySelector('[name="remote_id"]').value   = isClone ? cloneData.remoteId : '';
    body.querySelector('[name="group_name"]').value  = isClone ? cloneData.groupName : ''; // ✅ FIX
    body.querySelector('[name="name"]').value        = cloneData.name;
    body.querySelector('[name="time"]').value        = cloneData.time || '';
    body.querySelector('[name="cost"]').value        = cloneData.credit.toFixed(4);
    body.querySelector('[name="profit"]').value      = '0.0000';

    body.querySelector('[name="source"]').value      = isClone ? 2 : 1;
    body.querySelector('[name="type"]').value        = cloneData.serviceType;
    body.querySelector('[name="alias"]').value       = slugify(cloneData.name || '');

    const priceHelper = initPrice(body);
    priceHelper.setCost(cloneData.credit);

        // ✅ MAIN FIELD PRESETS (IMEI / IMEI+Serial / Serial / Custom ...)
    const mainTypeSel  = body.querySelector('[name="main_field_type"]');
    const mainLabelInp = body.querySelector('[name="main_field_label"]');
    const allowedSel   = body.querySelector('[name="allowed_characters"]');
    const minInp       = body.querySelector('[name="min"]');
    const maxInp       = body.querySelector('[name="max"]');
    const typeSel      = body.querySelector('[name="type"]');

    const presets = {
      imei: {
        label: 'IMEI',
        type: 'imei',
        allowed: 'numbers',
        min: 15,
        max: 15,
        lockLabel: true,
      },
      imei_serial: {
        label: 'IMEI/Serial number',
        type: 'imei',
        allowed: 'alnum',
        min: 10,
        max: 15,
        lockLabel: true,
      },
      serial: {
        label: 'Serial number',
        type: 'imei',
        allowed: 'alnum',
        min: 10,
        max: 13,
        lockLabel: true,
      },
      number: {
        label: 'Number',
        type: cloneData.serviceType || 'imei',
        allowed: 'numbers',
        min: 1,
        max: 32,
        lockLabel: true,
      },
      email: {
        label: 'Email',
        type: cloneData.serviceType || 'imei',
        allowed: 'any',
        min: 5,
        max: 128,
        lockLabel: true,
      },
      text: {
        label: 'Text',
        type: cloneData.serviceType || 'imei',
        allowed: 'any',
        min: 1,
        max: 255,
        lockLabel: true,
      },
      custom: {
        label: '', // لا نغيّرها
        type: cloneData.serviceType || 'imei',
        allowed: null,
        min: null,
        max: null,
        lockLabel: false,
      },
    };

    function applyMainFieldPreset(key){
      if (!mainTypeSel || !mainLabelInp) return;
      const p = presets[key] || presets.custom;

      // type
      if (typeSel && p.type) typeSel.value = p.type;

      // label
      if (p.lockLabel) {
        if (p.label) mainLabelInp.value = p.label;
        mainLabelInp.readOnly = true;
        mainLabelInp.classList.add('bg-light');
      } else {
        mainLabelInp.readOnly = false;
        mainLabelInp.classList.remove('bg-light');
        // في custom: لا نلمس القيمة (تظل حسب المستخدم)
      }

      // allowed characters
      if (allowedSel && p.allowed) allowedSel.value = p.allowed;

      // min/max
      if (minInp && Number.isFinite(p.min)) minInp.value = String(p.min);
      if (maxInp && Number.isFinite(p.max)) maxInp.value = String(p.max);
    }

    if (mainTypeSel) {
      mainTypeSel.addEventListener('change', () => {
        applyMainFieldPreset(mainTypeSel.value);
      });

      // initial apply
      applyMainFieldPreset(mainTypeSel.value || 'imei');
    }


    fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(cloneData.serviceType))
      .then(r=>r.json())
      .then(rows=>{
        const sel = body.querySelector('[name="group_id"]');
        if(sel){
          sel.innerHTML = `<option value="">Group</option>` +
            (Array.isArray(rows) ? rows.map(g=>`<option value="${g.id}">${clean(g.name)}</option>`).join('') : '');
        }
      });

    const userGroups = await loadUserGroups();
    buildPricingTable(body, userGroups);

    ensureApiUI(body);
    body.querySelector('[name="source"]')?.addEventListener('change', ()=> ensureApiUI(body));

    try{ await loadApiProviders(body); }catch(e){}

    const apiProviderSel = body.querySelector('#apiProviderSelect');
    const apiServiceSel  = body.querySelector('#apiServiceSelect');

    // ✅ إذا هذا Clone: اجعل provider مختار تلقائياً ثم حمّل خدماته
    if (isClone && apiProviderSel) {
      apiProviderSel.value = cloneData.providerId;
      try { await loadProviderServices(body, cloneData.providerId, cloneData.serviceType); } catch(e) {}
      // حاول اختيار الخدمة نفسها
      if (apiServiceSel) {
        const opt = Array.from(apiServiceSel.options).find(o => String(o.value) === String(cloneData.remoteId));
        if (opt) {
          apiServiceSel.value = opt.value;
          apiServiceSel.dispatchEvent(new Event('change'));
        }
      }
    }

    apiProviderSel?.addEventListener('change', async ()=>{
      const pid = apiProviderSel.value;
      if(!pid){
        apiServiceSel.innerHTML = `<option value="">Select service...</option>`;
        return;
      }
      await loadProviderServices(body, pid, cloneData.serviceType);
    });

    apiServiceSel?.addEventListener('change', ()=>{
      const opt = apiServiceSel.selectedOptions?.[0];
      if(!opt || !opt.value) return;

      body.querySelector('[name="supplier_id"]').value = apiProviderSel.value;
      body.querySelector('[name="remote_id"]').value   = opt.value;

      const name = clean(opt.dataset.name);
      const credit = Number(opt.dataset.credit || 0);
      const time = clean(opt.dataset.time);

      if(name){
        body.querySelector('[name="name"]').value = name;
        body.querySelector('[name="alias"]').value = slugify(name);
      }
      if(time) body.querySelector('[name="time"]').value = time;

      if(Number.isFinite(credit) && credit >= 0){
        body.querySelector('[name="cost"]').value = credit.toFixed(4);
        priceHelper.setCost(credit);
      }
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
  });

  document.addEventListener('submit', async (ev)=>{
    const form = ev.target;
    if(!form || !form.matches('#serviceModal form')) return;

    ev.preventDefault();

    const html = jQuery(form).find('#infoEditor').summernote('code');
    form.querySelector('#infoHidden').value = html;

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
        window.location.href = window.__serviceReturnUrl || window.location.href;
      }else{
        const t = await res.text();
        alert('Failed to save service\n\n' + t);
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
