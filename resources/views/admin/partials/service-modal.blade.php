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
  .api-box{border:1px solid #e9e9e9;border-radius:.5rem;padding:.75rem;margin-top:.5rem;background:#fafafa;}
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
  function openGeneralTab(){
    document.querySelector('#serviceModal .tab-btn[data-tab="general"]')?.click();
  }

  function slugify(text){
    return String(text||'')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g,'-')
      .replace(/^-+|-+$/g,'');
  }

  function calcServiceFinalPrice(scope){
    const cost   = Number(scope.querySelector('[name="cost"]')?.value || 0);
    const profit = Number(scope.querySelector('[name="profit"]')?.value || 0);
    const pType  = String(scope.querySelector('[name="profit_type"]')?.value || '1');
    const price = (pType === '2') ? (cost + (cost * profit / 100)) : (cost + profit);
    return Number.isFinite(price) ? price : 0;
  }

  function syncGroupPricesFromService(scope){
    const wrap = scope.querySelector('#groupsPricingWrap');
    if (!wrap) return;
    const servicePrice = calcServiceFinalPrice(scope);

    wrap.querySelectorAll('.pricing-row').forEach(row=>{
      const priceInput = row.querySelector('[data-price]');
      if (!priceInput) return;
      if (priceInput.dataset.autoPrice !== '1') return;

      priceInput.value = servicePrice.toFixed(4);
      const outEl = row.querySelector('[data-final]');
      if (outEl) outEl.textContent = servicePrice.toFixed(4);
    });
  }

  function initPrice(scope){
    const cost = scope.querySelector('[name="cost"]');
    const profit = scope.querySelector('[name="profit"]');
    const pType = scope.querySelector('[name="profit_type"]');
    const pricePreview = scope.querySelector('#pricePreview');
    const convertedPreview = scope.querySelector('#convertedPricePreview');

    function recalc(){
      const price = calcServiceFinalPrice(scope);
      if(pricePreview) pricePreview.value = price.toFixed(4);
      if(convertedPreview) convertedPreview.value = price.toFixed(4);
      const badge = document.getElementById('badgePrice');
      if (badge) badge.innerText = 'Price: ' + price.toFixed(4) + ' Credits';
      syncGroupPricesFromService(scope);
    }

    [cost,profit,pType].forEach(el=> el && el.addEventListener('input', recalc));
    [cost,profit,pType].forEach(el=> el && el.addEventListener('change', recalc));
    recalc();

    return {
      setCost(v){
        if(cost){ cost.value = Number(v||0).toFixed(4); }
        recalc();
      }
    };
  }

  async function loadUserGroups(){
    const res = await fetch("{{ route('admin.groups.options') }}", { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const rows = await res.json().catch(()=>[]);
    if(!Array.isArray(rows)) return [];
    return rows;
  }

  function buildPricingTable(scope, groups){
    const wrap = scope.querySelector('#groupsPricingWrap');
    if(!wrap) return;
    wrap.innerHTML = '';
    const initialServicePrice = calcServiceFinalPrice(scope);

    groups.forEach(g=>{
      const row = document.createElement('div');
      row.className = 'pricing-row';
      row.dataset.groupId = g.id;
      row.innerHTML = `
        <div class="pricing-title">${clean(g.name)}</div>
        <div class="pricing-inputs">
          <div>
            <label class="form-label">Price</label>
            <div class="input-group">
              <input type="number" step="0.0001" class="form-control" data-price data-auto-price="1"
                     name="group_prices[${g.id}][price]" value="${initialServicePrice.toFixed(4)}">
              <span class="input-group-text">Credits</span>
            </div>
            <div class="small text-muted mt-1">
              Final: <span class="fw-semibold" data-final>${initialServicePrice.toFixed(4)}</span> Credits
            </div>
          </div>
          <div>
            <label class="form-label">Discount</label>
            <div class="input-group">
              <input type="number" step="0.0001" class="form-control" data-discount
                     name="group_prices[${g.id}][discount]" value="0.0000">
              <select class="form-select" style="max-width:110px" data-discount-type
                      name="group_prices[${g.id}][discount_type]">
                <option value="1" selected>Credits</option>
                <option value="2">Percent</option>
              </select>
              <button type="button" class="btn btn-light btn-reset">Reset</button>
            </div>
          </div>
        </div>
      `;

      const priceInput = row.querySelector('[data-price]');
      const discInput  = row.querySelector('[data-discount]');
      const typeSelect = row.querySelector('[data-discount-type]');
      const outEl      = row.querySelector('[data-final]');

      const updateFinal = () => {
        const price = Number(priceInput?.value || 0);
        const disc  = Number(discInput?.value  || 0);
        const dtype = Number(typeSelect?.value || 1);
        let final = price;
        if (dtype === 2) final = price - (price * (disc/100));
        else final = price - disc;
        if (!Number.isFinite(final) || final < 0) final = 0;
        if (outEl) outEl.textContent = final.toFixed(4);
      };

      priceInput?.addEventListener('input', ()=>{ priceInput.dataset.autoPrice = '0'; updateFinal(); });
      discInput?.addEventListener('input', updateFinal);
      typeSelect?.addEventListener('change', updateFinal);

      row.querySelector('.btn-reset')?.addEventListener('click', ()=>{
        const sp = calcServiceFinalPrice(scope);
        priceInput.dataset.autoPrice = '1';
        priceInput.value = sp.toFixed(4);
        discInput.value = "0.0000";
        typeSelect.value = "1";
        updateFinal();
      });

      updateFinal();
      wrap.appendChild(row);
    });
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
            <select class="form-select" id="apiProviderSelect"><option value="">Select provider...</option></select>
          </div>
          <div class="col-md-6">
            <label class="form-label">API service</label>
            <select class="form-select" id="apiServiceSelect"><option value="">Select service...</option></select>
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

    const res = await fetch("{{ route('admin.apis.options') }}", { headers:{'X-Requested-With':'XMLHttpRequest'} });
    if(!res.ok) return;

    const rows = await res.json().catch(()=>[]);
    sel.innerHTML = `<option value="">Select provider...</option>` +
      (Array.isArray(rows) ? rows.map(r=>`<option value="${r.id}">${clean(r.name)}</option>`).join('') : '');
  }

  function parseJsonAttr(s){
    try{
      if(!s) return null;
      let str = String(s);
      str = str.replaceAll('&quot;', '"').replaceAll('&#34;', '"').replaceAll('&amp;', '&');
      return JSON.parse(str);
    }catch(e){ return null; }
  }

  function guessMainFieldFromRemoteFields(fields){
    if(!Array.isArray(fields) || fields.length === 0) return { type:'serial', label:'Serial' };
    const names = fields.map(f => String(f.fieldname || f.name || '').toLowerCase().trim());
    if (names.some(n => n.includes('imei')))   return { type:'imei',   label:'IMEI' };
    if (names.some(n => n.includes('serial'))) return { type:'serial', label:'Serial' };
    if (names.some(n => n.includes('email')) && fields.length === 1) return { type:'email', label:'Email' };
    const first = fields[0];
    const lab = String(first.fieldname || first.name || 'Text').trim();
    return { type:'text', label: lab || 'Text' };
  }

  async function loadProviderServices(scope, providerId, type){
    const sel = scope.querySelector('#apiServiceSelect');
    if(!sel) return;

    sel.innerHTML = `<option value="">Loading...</option>`;

    const url = new URL("{{ route('admin.services.clone.provider_services') }}", window.location.origin);
    url.searchParams.set('provider_id', providerId);
    url.searchParams.set('type', type);

    const res = await fetch(url.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} });
    if(!res.ok){ sel.innerHTML = `<option value="">Failed to load</option>`; return; }

    const rows = await res.json().catch(()=>[]);
    if(!Array.isArray(rows) || rows.length === 0){ sel.innerHTML = `<option value="">No services found</option>`; return; }

    sel.innerHTML = `<option value="">Select service...</option>` + rows.map(s=>{
      const rid  = clean(s.remote_id ?? s.id ?? s.service_id);
      const name = clean(s.name);
      const time = clean(s.time ?? s.delivery_time);
      const creditNum = Number(s.credit ?? s.price ?? s.cost ?? 0);
      const creditTxt = Number.isFinite(creditNum) ? creditNum.toFixed(4) : '0.0000';
      const af = (s.additional_fields ?? s.fields ?? []);
      const afJson = JSON.stringify(Array.isArray(af) ? af : []);
      const timeTxt = time ? ` — ${time}` : '';
      const ridTxt  = rid ? ` (#${rid})` : '';
      return `<option value="${rid}"
        data-name="${escAttr(name)}"
        data-credit="${creditTxt}"
        data-time="${escAttr(time)}"
        data-additional-fields="${escAttr(afJson)}"
      >${name}${timeTxt} — ${creditTxt} Credits${ridTxt}</option>`;
    }).join('');
  }

  function markCloneAsAdded(remoteId){
    const rid = String(remoteId || '').trim();
    if(!rid) return;
    const esc = (v) => { try { return CSS.escape(v); } catch(e){ return v.replace(/["\\]/g, '\\$&'); } };
    const row = document.querySelector(`#svcTable tr[data-remote-id="${esc(rid)}"]`) ||
                document.querySelector(`#servicesTable tr[data-remote-id="${esc(rid)}"]`) ||
                document.querySelector(`tr[data-remote-id="${esc(rid)}"]`);
    if(!row) return;

    const btn = row.querySelector('.clone-btn') || row.querySelector('[data-create-service]') || row.querySelector('button');
    if(!btn) return;

    btn.disabled = true;
    btn.classList.remove('btn-success','btn-secondary','btn-danger','btn-warning','btn-info','btn-dark','btn-primary','btn-light','btn-outline-success','btn-outline-primary');
    btn.classList.add('btn-outline-primary');
    btn.textContent = 'Added ✅';
    btn.removeAttribute('data-create-service');
  }

  function ensureRequiredFields(form){
    const ensureHidden = (name, value) => {
      let el = form.querySelector(`[name="${name}"]`);
      if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = name; form.appendChild(el); }
      if (el.value === '' || el.value === null || el.value === undefined) el.value = (value ?? '');
      return el;
    };
    const nameVal = clean(form.querySelector('[name="name"]')?.value || '');
    ensureHidden('name_en', nameVal);
    const mainFieldVal = clean(form.querySelector('[name="main_type"]')?.value || form.querySelector('[name="main_field_type"]')?.value || '');
    ensureHidden('main_type', mainFieldVal);
    const typeVal = clean(form.querySelector('[name="type"]')?.value || '');
    if (typeVal) ensureHidden('type', typeVal);
  }

  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-create-service]');
    if(!btn) return;
    e.preventDefault();

    const modalEl = document.getElementById('serviceModal');
    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

    // execute scripts embedded in template
    (function runInjectedScripts(container){
      Array.from(container.querySelectorAll('script')).forEach(old => {
        const s = document.createElement('script');
        for (const attr of old.attributes) s.setAttribute(attr.name, attr.value);
        s.text = old.textContent || '';
        old.parentNode?.removeChild(old);
        container.appendChild(s);
      });
    })(body);

    initTabs(body);
    openGeneralTab();

    const providerId = btn.dataset.providerId;
    const remoteId   = btn.dataset.remoteId;

    const afFromBtn = parseJsonAttr(btn.dataset.additionalFields || btn.getAttribute('data-additional-fields') || '');
    if (Array.isArray(afFromBtn) && afFromBtn.length) {
      window.__serverServiceApplyRemoteFields__?.(body, afFromBtn);
      const mf = guessMainFieldFromRemoteFields(afFromBtn);
      window.__serverServiceSetMainField__?.(body, mf.type, mf.label);
      openGeneralTab();
    }

    const isClone = (providerId && providerId !== 'undefined' && remoteId && remoteId !== 'undefined');
    const providerName = btn.dataset.providerName || document.querySelector('.card-header h5')?.textContent?.split('|')?.[0]?.trim() || '—';

    const cloneData = {
      providerId: isClone ? providerId : '',
      providerName,
      remoteId: isClone ? remoteId : '',
      groupName: clean(btn.dataset.groupName || ''),
      name: btn.dataset.name || '',
      credit: Number(btn.dataset.credit || 0),
      time: btn.dataset.time || '',
      serviceType: (btn.dataset.serviceType || 'imei').toLowerCase()
    };

    document.getElementById('serviceModalSubtitle').innerText =
      isClone ? `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}` : `Provider: — | Remote ID: —`;
    document.getElementById('badgeType').innerText = `Type: ${cloneData.serviceType.toUpperCase()}`;

    body.querySelector('[name="supplier_id"]').value = isClone ? cloneData.providerId : '';
    body.querySelector('[name="remote_id"]').value   = isClone ? cloneData.remoteId : '';
    body.querySelector('[name="group_name"]').value  = isClone ? cloneData.groupName : '';
    body.querySelector('[name="name"]').value        = cloneData.name;
    body.querySelector('[name="time"]').value        = cloneData.time || '';
    body.querySelector('[name="cost"]').value        = cloneData.credit.toFixed(4);
    body.querySelector('[name="profit"]').value      = '0.0000';
    body.querySelector('[name="source"]').value      = isClone ? 2 : 1;
    body.querySelector('[name="type"]').value        = cloneData.serviceType;
    body.querySelector('[name="alias"]').value       = slugify(cloneData.name || '');

    const priceHelper = initPrice(body);
    priceHelper.setCost(cloneData.credit);

    fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(cloneData.serviceType))
      .then(r=>r.json()).then(rows=>{
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
    await loadApiProviders(body);

    const apiProviderSel = body.querySelector('#apiProviderSelect');
    const apiServiceSel  = body.querySelector('#apiServiceSelect');

    if (isClone && apiProviderSel) {
      const pid = String(cloneData.providerId || '').trim();
      if (pid && !apiProviderSel.querySelector(`option[value="${pid.replace(/"/g,'\\\"')}"]`)) {
        const opt = document.createElement('option');
        opt.value = pid;
        opt.textContent = cloneData.providerName || ('Provider #' + pid);
        apiProviderSel.appendChild(opt);
      }
      apiProviderSel.value = pid;
      apiProviderSel.disabled = true;

      await loadProviderServices(body, pid, cloneData.serviceType);

      const opt2 = Array.from(apiServiceSel.options).find(o => String(o.value) === String(cloneData.remoteId));
      if (opt2) { apiServiceSel.value = opt2.value; apiServiceSel.dispatchEvent(new Event('change')); }
    }

    apiProviderSel?.addEventListener('change', async ()=>{
      if (apiProviderSel.disabled) return;
      const pid = apiProviderSel.value;
      if(!pid){ apiServiceSel.innerHTML = `<option value="">Select service...</option>`; return; }
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

      if(name){ body.querySelector('[name="name"]').value = name; body.querySelector('[name="alias"]').value = slugify(name); }
      if(time) body.querySelector('[name="time"]').value = time;

      if(Number.isFinite(credit) && credit >= 0){
        body.querySelector('[name="cost"]').value = credit.toFixed(4);
        priceHelper.setCost(credit);
      }

      const af = parseJsonAttr(opt.dataset.additionalFields || opt.getAttribute('data-additional-fields') || '');
      if (Array.isArray(af) && af.length) {
        window.__serverServiceApplyRemoteFields__?.(body, af);
        const mf = guessMainFieldFromRemoteFields(af);
        window.__serverServiceSetMainField__?.(body, mf.type, mf.label);
        openGeneralTab();
      }
    });

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      if (typeof window.initSummernoteIn === 'function') await window.initSummernoteIn(body);
      else console.error('Missing global summernote script: initSummernoteIn');
    };
    modalEl.addEventListener('shown.bs.modal', onShown);

    const onHidden = () => {
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      window.destroySummernoteIn?.(body);
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);

    modal.show();
  });

  document.addEventListener('submit', async (ev)=>{
    const form = ev.target;
    if(!form || !form.matches('#serviceModal form')) return;
    ev.preventDefault();

    ensureRequiredFields(form);
    window.syncSummernoteToHidden?.(form);

    const submitBtn = form.querySelector('[type="submit"]');
    if(submitBtn) submitBtn.disabled = true;

    try{
      const res = await fetch(form.action,{
        method: form.method,
        headers:{
          'X-Requested-With':'XMLHttpRequest',
          'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content
        },
        body: new FormData(form)
      });

      if(submitBtn) submitBtn.disabled = false;

      if(res.status === 422){
        const json = await res.json().catch(()=>({}));
        alert(Object.values(json.errors||{}).flat().join("\n"));
        return;
      }

      if(res.ok){
        const rid = form.querySelector('[name="remote_id"]')?.value;
        markCloneAsAdded(rid);

        // ✅ NEW: ذاكرة + Event للتزامن مع Import Wizard بدون Refresh
        const providerId = form.querySelector('[name="supplier_id"]')?.value || '';
        const kind = form.querySelector('[name="type"]')?.value || '';
        if (providerId && kind && rid) {
          window.__gsmmixAdded = window.__gsmmixAdded || {};
          window.__gsmmixAdded[`${String(providerId)}:${String(kind)}:${String(rid)}`] = true;

          window.dispatchEvent(new CustomEvent('gsmmix:service-created', {
            detail: { provider_id: String(providerId), kind: String(kind), remote_id: String(rid) }
          }));
        }

        window.bootstrap.Modal.getInstance(document.getElementById('serviceModal'))?.hide();
        window.showToast?.('success', '✅ Service created successfully', { title: 'Done' });
        return;
      }else{
        const t = await res.text();
        alert('Failed to save service\n\n' + t);
      }
    }catch(e){
      if(submitBtn) submitBtn.disabled = false;
      alert('Network error');
    }
  });
})();
</script>
  @endpush
@endonce
