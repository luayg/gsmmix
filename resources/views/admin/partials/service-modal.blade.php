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
      const rid  = clean(s.remote_id ?? s.id ?? s.service_id ?? s.SERVICEID);
      const name = clean(s.name ?? s.SERVICENAME);
      const time = clean(s.time ?? s.delivery_time ?? s.TIME);
      const creditNum = Number(s.credit ?? s.price ?? s.cost ?? s.CREDIT ?? 0);
      const creditTxt = Number.isFinite(creditNum) ? creditNum.toFixed(4) : '0.0000';

      const af = (s.additional_fields ?? s.fields ?? s.ADDITIONAL_FIELDS ?? []);
      const afJson = JSON.stringify(Array.isArray(af) ? af : []);

      // ✅ NEW: allow extensions for file services
      const allowExt = clean(
        s.allow_extension ?? s.allow_extensions ?? s.ALLOW_EXTENSION ?? s.extensions ?? s.EXTENSIONS ?? ''
      );

      const timeTxt = time ? ` — ${time}` : '';
      const ridTxt  = rid ? ` (#${rid})` : '';
      return `<option value="${rid}"
        data-name="${escAttr(name)}"
        data-credit="${creditTxt}"
        data-time="${escAttr(time)}"
        data-additional-fields="${escAttr(afJson)}"
        data-allow-extensions="${escAttr(allowExt)}"
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

  // ✅ NEW: resolve correct hooks by service type (imei/server/file) + file extensions hook
  function resolveHooks(serviceType){
    const t = String(serviceType || '').toLowerCase().trim();
    const apply  = window[`__${t}ServiceApplyRemoteFields__`] || window.__serverServiceApplyRemoteFields__ || null;
    const setMain= window[`__${t}ServiceSetMainField__`]      || window.__serverServiceSetMainField__      || null;
    const setExt = window[`__${t}ServiceSetAllowedExtensions__`] || null; // file only typically
    return { apply, setMain, setExt };
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

    // ✅ resolve hooks now (so IMEI uses __imei..., FILE uses __file...)
    const hooks = resolveHooks(cloneData.serviceType);

    const afFromBtn = parseJsonAttr(btn.dataset.additionalFields || btn.getAttribute('data-additional-fields') || '');
    if (Array.isArray(afFromBtn) && afFromBtn.length) {
      hooks.apply?.(body, afFromBtn);
      const mf = guessMainFieldFromRemoteFields(afFromBtn);
      hooks.setMain?.(body, mf.type, mf.label);
      openGeneralTab();
    }

      // ==========================
  // ✅ EDIT SERVICE (open same modal, fill values from JSON)
  // ==========================
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-edit-service]');
    if(!btn) return;

    e.preventDefault();

    const jsonUrl = btn.dataset.jsonUrl;
    const serviceType = (btn.dataset.serviceType || 'imei').toLowerCase();
    const serviceId = btn.dataset.serviceId;

    if(!jsonUrl) return alert('Missing json url');

    const res = await fetch(jsonUrl, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const payload = await res.json().catch(()=>null);
    if(!res.ok || !payload?.ok) return alert(payload?.msg || 'Failed to load service');

    const s = payload.service || {};

    const modalEl = document.getElementById('serviceModal');
    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

    // execute scripts embedded in template
    (function runInjectedScripts(container){
      Array.from(container.querySelectorAll('script')).forEach(old => {
        const sc = document.createElement('script');
        for (const attr of old.attributes) sc.setAttribute(attr.name, attr.value);
        sc.text = old.textContent || '';
        old.parentNode?.removeChild(old);
        container.appendChild(sc);
      });
    })(body);

    initTabs(body);
    openGeneralTab();

    // ✅ resolve hooks by type
    const hooks = resolveHooks(serviceType);

    // ✅ subtitle + badges
    document.getElementById('serviceModalSubtitle').innerText =
      `Provider: ${clean(s.supplier_name || s.api_name || '—')} | Remote ID: ${clean(s.remote_id || '—')}`;
    document.getElementById('badgeType').innerText = `Type: ${serviceType.toUpperCase()}`;

    // ✅ fill form basic fields
    body.querySelector('[name="supplier_id"]').value = clean(s.supplier_id ?? '');
    body.querySelector('[name="remote_id"]').value   = clean(s.remote_id ?? '');
    body.querySelector('[name="name"]').value        = clean(s.name ?? '');
    body.querySelector('[name="time"]').value        = clean(s.time ?? '');
    body.querySelector('[name="cost"]').value        = Number(s.cost || 0).toFixed(4);
    body.querySelector('[name="profit"]').value      = Number(s.profit || 0).toFixed(4);
    body.querySelector('[name="profit_type"]').value = String(s.profit_type || 1);
    body.querySelector('[name="source"]').value      = Number(s.source || 1);
    body.querySelector('[name="type"]').value        = serviceType;
    body.querySelector('[name="alias"]').value       = slugify(clean(s.name ?? ''));

    // group
    fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(serviceType))
      .then(r=>r.json()).then(rows=>{
        const sel = body.querySelector('[name="group_id"]');
        if(sel){
          sel.innerHTML = `<option value="">Group</option>` +
            (Array.isArray(rows) ? rows.map(g=>`<option value="${g.id}">${clean(g.name)}</option>`).join('') : '');
          if (s.group_id) sel.value = String(s.group_id);
        }
      });

    const priceHelper = initPrice(body);
    priceHelper.setCost(Number(s.cost || 0));

    // ✅ Groups pricing table (load all user groups, then apply stored group_prices)
    const userGroups = await loadUserGroups();
    buildPricingTable(body, userGroups);

    // apply existing group prices
    const gp = Array.isArray(s.group_prices) ? s.group_prices : [];
    if (gp.length){
      gp.forEach(row=>{
        const gid = String(row.group_id || '');
        const price = Number(row.price || 0);
        const disc  = Number(row.discount || 0);
        const dtype = Number(row.discount_type || 1);

        const wrap = body.querySelector('#groupsPricingWrap');
        const elRow = wrap?.querySelector(`.pricing-row[data-group-id="${gid}"]`);
        if(!elRow) return;

        const priceInput = elRow.querySelector('[data-price]');
        const discInput  = elRow.querySelector('[data-discount]');
        const typeSelect = elRow.querySelector('[data-discount-type]');
        const outEl      = elRow.querySelector('[data-final]');

        if(priceInput){
          priceInput.dataset.autoPrice = '0';
          priceInput.value = price.toFixed(4);
        }
        if(discInput) discInput.value = disc.toFixed(4);
        if(typeSelect) typeSelect.value = String(dtype);

        // update final
        const p = Number(priceInput?.value || 0);
        const d = Number(discInput?.value  || 0);
        const dt = Number(typeSelect?.value || 1);
        let final = p;
        if (dt === 2) final = p - (p * (d/100));
        else final = p - d;
        if (!Number.isFinite(final) || final < 0) final = 0;
        if (outEl) outEl.textContent = final.toFixed(4);
      });
    }

    // ✅ Custom fields from params/custom_fields
    const cf = Array.isArray(s.custom_fields) ? s.custom_fields : [];
    if (cf.length){
      hooks.apply?.(body, cf);
      const mf = guessMainFieldFromRemoteFields(cf);
      hooks.setMain?.(body, mf.type, mf.label);
      openGeneralTab();
    }

    // ✅ API UI
    ensureApiUI(body);
    body.querySelector('[name="source"]')?.addEventListener('change', ()=> ensureApiUI(body));
    await loadApiProviders(body);

    // ✅ إذا الخدمة من API: اعمل preselect للـProvider والـService ثم فعّل change
try {
  const sourceVal = Number(s.source || 1);
  const pid = String(s.supplier_id ?? '').trim();
  const rid = String(s.remote_id ?? '').trim();

  const apiProviderSel = body.querySelector('#apiProviderSelect');
  const apiServiceSel  = body.querySelector('#apiServiceSelect');

  if (sourceVal === 2 && pid && apiProviderSel) {
    // اجعل الـSource = API
    const sourceSel = body.querySelector('[name="source"]');
    if (sourceSel) { sourceSel.value = '2'; ensureApiUI(body); }

    // اختر المزود
    apiProviderSel.value = pid;

    // حمّل خدمات المزود لهذا النوع
    await loadProviderServices(body, pid, serviceType);

    // اختر خدمة المزود (Remote ID) وفعّل change ليتم تطبيق additional_fields + السعر + الوقت
    if (apiServiceSel && rid) {
      const opt = Array.from(apiServiceSel.options).find(o => String(o.value) === rid);
      if (opt) {
        apiServiceSel.value = opt.value;
        apiServiceSel.dispatchEvent(new Event('change'));
      }
    }
  }
} catch (e) {
  // ignore
}

    // ✅ change FORM to UPDATE
    const form = body.querySelector('form');
    if(form){
      form.action = `{{ url('/admin/service-management') }}/${serviceType}-services/${serviceId}`;
      form.method = 'POST';

      // add _method PUT
      let method = form.querySelector('input[name="_method"]');
      if(!method){
        method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        form.appendChild(method);
      }
      method.value = 'PUT';
    }

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      if (typeof window.initSummernoteIn === 'function') await window.initSummernoteIn(body);
    };
    modalEl.addEventListener('shown.bs.modal', onShown);

    const onHidden = () => {
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      window.destroySummernoteIn?.(body);
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);

    modal.show();
  });

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

            // ✅ File: apply allowed extensions if provided
      const st = String(cloneData.serviceType || '').toLowerCase();
      if (st === 'file') {
        const exts = clean(opt.dataset.allowExtensions || opt.getAttribute('data-allow-extensions') || '');
        if (typeof window.__fileServiceSetAllowedExtensions__ === 'function') {
          window.__fileServiceSetAllowedExtensions__(body, exts);
        }
      }




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

      // ✅ NEW: FILE only -> fill allowed extensions preview + store it in params via file modal hook
      const allowExt = clean(opt.dataset.allowExtension || opt.getAttribute('data-allow-extension') || '');
      hooks.setExt?.(body, allowExt);

      // ✅ use hooks for correct service type (imei/server/file)
      const af = parseJsonAttr(opt.dataset.additionalFields || opt.getAttribute('data-additional-fields') || '');
      if (Array.isArray(af) && af.length) {
        hooks.apply?.(body, af);
        const mf = guessMainFieldFromRemoteFields(af);
        hooks.setMain?.(body, mf.type, mf.label);
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
  const rid = (form.querySelector('[name="remote_id"]')?.value || '').trim();

  // ✅ 1) عطّل زر Clone مباشرة في الجدول الخلفي
  markCloneAsAdded(rid);

  // ✅ 2) خزّنها في ذاكرة الصفحة (حتى Import Wizard يقرأها فوراً)
  const providerId = (form.querySelector('[name="supplier_id"]')?.value || '').trim();
  const kind       = (form.querySelector('[name="type"]')?.value || '').trim().toLowerCase(); // imei/server/file

  if (providerId && kind && rid) {
    window.__gsmmixAdded = window.__gsmmixAdded || {};
    window.__gsmmixAdded[`${providerId}:${kind}:${rid}`] = true;

    // ✅ 3) أطلق Event عام لكي Import Wizard يعمل refresh فوري
    window.dispatchEvent(new CustomEvent('gsmmix:service-created', {
      detail: {
        provider_id: providerId,
        kind: kind,
        remote_id: rid
      }
    }));
  }

  // ✅ اغلق المودال واشعار
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

    // ==========================================================
  // ✅ EDIT SERVICE (open same modal + fill values)
  // ==========================================================
  async function openEditService(btn){
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

    const jsonUrl   = btn.dataset.jsonUrl;
    const updateUrl = btn.dataset.updateUrl;
    const serviceType = (btn.dataset.serviceType || '').toLowerCase();

    if(!jsonUrl || !updateUrl) return alert('Missing json/update URL');

    // Load service json
    const res = await fetch(jsonUrl, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const payload = await res.json().catch(()=>null);
    if(!res.ok || !payload?.ok) return alert(payload?.msg || 'Failed to load service');

    const s = payload.service || {};
    document.getElementById('serviceModalSubtitle').innerText = `Edit Service #${s.id || ''}`;
    document.getElementById('badgeType').innerText = `Type: ${(serviceType || s.type || '').toUpperCase()}`;

    // set form action + method PUT
    const form = body.querySelector('form#serviceCreateForm') || body.querySelector('form');
    if(!form) return alert('Form not found inside modal');

    form.action = updateUrl;
    form.method = 'POST';

    let spoof = form.querySelector('input[name="_method"]');
    if(!spoof){
      spoof = document.createElement('input');
      spoof.type = 'hidden';
      spoof.name = '_method';
      form.appendChild(spoof);
    }
    spoof.value = 'PUT';

    // change button label
    const submitBtn = form.querySelector('[type="submit"]');
    if(submitBtn) submitBtn.textContent = 'Save';

    // fill basic fields
    const nameText = (typeof s.name === 'object' ? (s.name.fallback || s.name.en || '') : (s.name || ''));
    const timeText = (typeof s.time === 'object' ? (s.time.fallback || s.time.en || '') : (s.time || ''));
    const infoText = (typeof s.info === 'object' ? (s.info.fallback || s.info.en || '') : (s.info || ''));

    form.querySelector('[name="name"]') && (form.querySelector('[name="name"]').value = nameText);
    form.querySelector('[name="alias"]') && (form.querySelector('[name="alias"]').value = s.alias || '');
    form.querySelector('[name="time"]') && (form.querySelector('[name="time"]').value = timeText);

    // Info (summernote hidden)
    const infoHidden = form.querySelector('#infoHidden');
    if(infoHidden) infoHidden.value = infoText;

    // pricing
    form.querySelector('[name="cost"]') && (form.querySelector('[name="cost"]').value = Number(s.cost||0).toFixed(4));
    form.querySelector('[name="profit"]') && (form.querySelector('[name="profit"]').value = Number(s.profit||0).toFixed(4));
    form.querySelector('[name="profit_type"]') && (form.querySelector('[name="profit_type"]').value = String(s.profit_type||1));

    // group
    if(form.querySelector('[name="group_id"]') && s.group_id){
      form.querySelector('[name="group_id"]').value = String(s.group_id);
    }

    // source
    if(form.querySelector('[name="source"]')){
      form.querySelector('[name="source"]').value = String(s.source || 1);
    }

    // Keep supplier/remote ids
    if(form.querySelector('[name="supplier_id"]')) form.querySelector('[name="supplier_id"]').value = s.supplier_id ?? '';
    if(form.querySelector('[name="remote_id"]')) form.querySelector('[name="remote_id"]').value = s.remote_id ?? '';

        // API source preselect (provider + remote service)
    ensureApiUI(body);
    body.querySelector('[name="source"]')?.addEventListener('change', ()=> ensureApiUI(body));
    await loadApiProviders(body);

    try {
      const sourceVal = Number(s.source || 1);
      const pid = String(s.supplier_id ?? '').trim();
      const rid = String(s.remote_id ?? '').trim();

      const apiProviderSel = body.querySelector('#apiProviderSelect');
      const apiServiceSel  = body.querySelector('#apiServiceSelect');

      if (sourceVal === 2 && pid && apiProviderSel) {
        apiProviderSel.value = pid;
        await loadProviderServices(body, pid, serviceType);

        if (apiServiceSel && rid) {
          const opt = Array.from(apiServiceSel.options).find(o => String(o.value) === rid);
          if (opt) {
            apiServiceSel.value = opt.value;
            apiServiceSel.dispatchEvent(new Event('change'));
          }
        }
      }
    } catch (_) {
      // ignore API preload errors
    }


    // main_field -> fill selects/inputs
    const mf = s.main_field || {};
    const mfType = (mf.type || (mf.label ? 'text' : '') || '').toString();
    const mfAllowed = (mf.rules?.allowed || '').toString();
    const mfMin = mf.rules?.minimum ?? '';
    const mfMax = mf.rules?.maximum ?? '';
    const mfLabel = (mf.label?.fallback || mf.label?.en || '').toString();

    if(form.querySelector('#mainFieldType') && mfType) form.querySelector('#mainFieldType').value = mfType;
    if(form.querySelector('#allowedChars') && mfAllowed) form.querySelector('#allowedChars').value = mfAllowed;
    if(form.querySelector('#minChars')) form.querySelector('#minChars').value = String(mfMin);
    if(form.querySelector('#maxChars')) form.querySelector('#maxChars').value = String(mfMax);
    if(form.querySelector('#mainFieldLabel') && mfLabel) form.querySelector('#mainFieldLabel').value = mfLabel;

    // If file service: allowed_extensions preview
    const params = s.params || {};
    if(form.querySelector('#allowedExtensionsPreview') && params.allowed_extensions){
      form.querySelector('#allowedExtensionsPreview').value = String(params.allowed_extensions);
    }

    // Fill custom fields from params.custom_fields
    const custom = Array.isArray(params.custom_fields) ? params.custom_fields : [];
    if(custom.length){
      // convert local format -> remote-like format for your hooks
      const additionalFields = custom.map(cf => ({
        fieldname: cf.name ?? '',
        fieldtype: cf.field_type ?? cf.type ?? 'text',
        required: (cf.required ? 'on' : ''),
        description: cf.description ?? '',
        fieldoptions: cf.options ?? '',
      }));

      const hooks = resolveHooks(serviceType);
      hooks.apply?.(body, additionalFields);
      openGeneralTab();
    }

    // Fill group prices if returned
    try{
      const gp = Array.isArray(s.group_prices) ? s.group_prices : [];
      if(gp.length){
        // after pricing table built, apply values
        setTimeout(()=>{
          gp.forEach(row=>{
            const gid = String(row.group_id || '');
            if(!gid) return;
            const priceInput = body.querySelector(`[name="group_prices[${gid}][price]"]`);
            const discInput  = body.querySelector(`[name="group_prices[${gid}][discount]"]`);
            const typeSel    = body.querySelector(`[name="group_prices[${gid}][discount_type]"]`);
            if(priceInput) { priceInput.value = Number(row.price||0).toFixed(4); priceInput.dataset.autoPrice = '0'; }
            if(discInput)  discInput.value = Number(row.discount||0).toFixed(4);
            if(typeSel)    typeSel.value = String(row.discount_type||1);
            // trigger updateFinal if exists
            priceInput?.dispatchEvent(new Event('input'));
            discInput?.dispatchEvent(new Event('input'));
            typeSel?.dispatchEvent(new Event('change'));
          });
        }, 300);
      }
    }catch(e){}

    // init price previews
    const priceHelper = initPrice(body);
    priceHelper.setCost(Number(s.cost||0));

    // show modal and init summernote
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      if (typeof window.initSummernoteIn === 'function') await window.initSummernoteIn(body);
    };
    modalEl.addEventListener('shown.bs.modal', onShown);

    const onHidden = () => {
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      window.destroySummernoteIn?.(body);
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);

    modal.show();
  }

  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-edit-service]');
    if(!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    await openEditService(btn);
  });

  
})();
</script>
  @endpush
@endonce
