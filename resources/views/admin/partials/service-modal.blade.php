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

  // ✅ FALLBACK: لو initModalEditors غير موجودة لكن summernote موجود
  function ensureInitModalEditorsFallback(){
    try{
      if (window.initModalEditors) return;

      if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.summernote !== 'function') return;

      window.initModalEditors = async function(scope){
        const $ = window.jQuery;
        const $scope = $(scope || document);

        $scope.find('[data-summernote="1"]').each(function(){
          const $el = $(this);
          const h = Number($el.attr('data-summernote-height') || 320);

          if ($el.data('summernote')) return;

          $el.summernote({
            height: h,
            toolbar: [
              ['style', ['style']],
              ['font', ['bold', 'italic', 'underline', 'clear']],
              ['fontname', ['fontname']],
              ['fontsize', ['fontsize']],
              ['color', ['color']],
              ['para', ['ul', 'ol', 'paragraph']],
              ['table', ['table']],
              ['insert', ['link', 'picture', 'video']],
              ['view', ['codeview', 'help']]
            ]
          });
        });
      };
    }catch(e){
      // ignore
    }
  }

  async function ensureSummernote(){
    ensureInitModalEditorsFallback();

    if (!window.jQuery) {
      console.error('jQuery not found.');
      return false;
    }
    if (!window.initModalEditors) {
      console.error('initModalEditors not found (and fallback not available).');
      return false;
    }
    return true;
  }

  async function ensureSelect2(){
    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
      console.warn('Select2 not available.');
      return false;
    }
    return true;
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

  function updateFinalForRow(row){
    const priceEl = row.querySelector('[data-price]');
    const discEl  = row.querySelector('[data-discount]');
    const typeEl  = row.querySelector('[data-discount-type]');
    const outEl   = row.querySelector('[data-final]');

    const price = Number(priceEl?.value || 0);
    const disc  = Number(discEl?.value  || 0);
    const dtype = Number(typeEl?.value  || 1);

    let final = price;
    if (dtype === 2) final = price - (price * (disc/100));
    else final = price - disc;

    if (!Number.isFinite(final)) final = 0;
    if (final < 0) final = 0;

    if (outEl) outEl.textContent = final.toFixed(4);
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
      updateFinalForRow(row);
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
              <input
                type="number" step="0.0001"
                class="form-control"
                data-price
                data-auto-price="1"
                name="group_prices[${g.id}][price]"
                value="${initialServicePrice.toFixed(4)}">
              <span class="input-group-text">Credits</span>
            </div>
            <div class="small text-muted mt-1">
              Final: <span class="fw-semibold" data-final>0.0000</span> Credits
            </div>
          </div>

          <div>
            <label class="form-label">Discount</label>
            <div class="input-group">
              <input
                type="number" step="0.0001"
                class="form-control"
                data-discount
                name="group_prices[${g.id}][discount]"
                value="0.0000">
              <select class="form-select" style="max-width:110px"
                data-discount-type
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

      priceInput?.addEventListener('input', ()=>{
        priceInput.dataset.autoPrice = '0';
      });

      row.querySelector('.btn-reset').addEventListener('click', ()=>{
        const sp = calcServiceFinalPrice(scope);
        if (priceInput){
          priceInput.dataset.autoPrice = '1';
          priceInput.value = sp.toFixed(4);
        }
        if (discInput) discInput.value = "0.0000";
        if (typeSelect) typeSelect.value = "1";
        updateFinalForRow(row);
      });

      row.querySelectorAll('input,select').forEach(el=>{
        const handler = () => updateFinalForRow(row);
        el.addEventListener('input', handler);
        el.addEventListener('change', handler);
      });

      updateFinalForRow(row);
      wrap.appendChild(row);
    });
  }

  async function loadUserGroups(){
    const res = await fetch("{{ route('admin.groups.options') }}", { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const rows = await res.json().catch(()=>[]);
    if(!Array.isArray(rows)) return [];
    return rows;
  }

  // ✅ parse JSON even if it contains &quot;
  function parseJsonAttr(s){
    try{
      if(!s) return null;
      let str = String(s);
      str = str.replaceAll('&quot;', '"')
               .replaceAll('&#34;', '"')
               .replaceAll('&amp;', '&');
      return JSON.parse(str);
    }catch(e){
      return null;
    }
  }

  function guessMainFieldFromRemoteFields(fields){
    if(!Array.isArray(fields) || fields.length === 0) return { type:'serial', label:'Serial' };

    const names = fields.map(f => String(f.fieldname || f.name || '').toLowerCase().trim());
    const hasImei   = names.some(n => n.includes('imei'));
    const hasSerial = names.some(n => n.includes('serial'));
    const hasEmail  = names.some(n => n.includes('email'));

    if (hasImei)   return { type:'imei',   label:'IMEI' };
    if (hasSerial) return { type:'serial', label:'Serial' };
    if (hasEmail && fields.length === 1) return { type:'email', label:'Email' };

    const first = fields[0];
    const lab = String(first.fieldname || first.name || 'Text').trim();
    return { type:'text', label: lab || 'Text' };
  }

  function ensureRequiredFields(form){
    const ensureHidden = (name, value) => {
      let el = form.querySelector(`[name="${name}"]`);
      if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = name;
        form.appendChild(el);
      }
      if (el.value === '' || el.value === null || el.value === undefined) {
        el.value = (value ?? '');
      }
      return el;
    };

    const nameVal = clean(form.querySelector('[name="name"]')?.value || '');
    ensureHidden('name_en', nameVal);

    const mainFieldVal = clean(
      form.querySelector('[name="main_type"]')?.value ||
      form.querySelector('[name="main_field_type"]')?.value ||
      ''
    );
    ensureHidden('main_type', mainFieldVal);

    const typeVal = clean(form.querySelector('[name="type"]')?.value || '');
    if (typeVal) ensureHidden('type', typeVal);
  }

  // ====== CLICK CLONE ======
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-create-service]');
    if(!btn) return;

    e.preventDefault();

    const body = document.getElementById('serviceModalBody');
    const tpl  = document.getElementById('serviceCreateTpl');
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;

    // run injected scripts
    (function runInjectedScripts(container){
      const scripts = Array.from(container.querySelectorAll('script'));
      scripts.forEach(old => {
        const s = document.createElement('script');
        for (const attr of old.attributes) s.setAttribute(attr.name, attr.value);
        s.text = old.textContent || '';
        old.parentNode?.removeChild(old);
        container.appendChild(s);
      });
    })(body);

    initTabs(body);

    const okSn = await ensureSummernote();
    await ensureSelect2();

    if (okSn) {
      const infoEl = body.querySelector('#infoEditor');
      if (infoEl) {
        infoEl.classList.remove('d-none');
        infoEl.setAttribute('data-summernote', '1');
        infoEl.setAttribute('data-summernote-height', '320');
        await window.initModalEditors(body);
      }
    }

    const providerId = btn.dataset.providerId;
    const remoteId   = btn.dataset.remoteId;

    // ✅ additional_fields from button (if exists)
    const afFromBtn = parseJsonAttr(btn.dataset.additionalFields || btn.getAttribute('data-additional-fields') || '');

    if (Array.isArray(afFromBtn) && afFromBtn.length) {
      if (typeof window.__serverServiceApplyRemoteFields__ === 'function') {
        window.__serverServiceApplyRemoteFields__(body, afFromBtn);
      }
      const mf = guessMainFieldFromRemoteFields(afFromBtn);
      if (typeof window.__serverServiceSetMainField__ === 'function') {
        window.__serverServiceSetMainField__(body, mf.type, mf.label);
      }
      const btnAdditional = document.querySelector('#serviceModal .tab-btn[data-tab="additional"]');
      btnAdditional?.click();
    }

    const isClone = (providerId !== undefined && providerId !== '' && providerId !== 'undefined'
                  && remoteId   !== undefined && remoteId   !== '' && remoteId   !== 'undefined');

    const providerName =
      btn.dataset.providerName ||
      document.querySelector('.card-header h5')?.textContent?.split('|')?.[0]?.trim() ||
      '—';

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
      isClone ? `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`
              : `Provider: — | Remote ID: —`;

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
    syncGroupPricesFromService(body);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
  });

  // ====== SUBMIT ======
  document.addEventListener('submit', async (ev)=>{
    const form = ev.target;
    if(!form || !form.matches('#serviceModal form')) return;

    ev.preventDefault();
    ensureRequiredFields(form);

    // ✅ write HTML from editor to hidden
    try{
      const infoEditor = window.jQuery ? window.jQuery(form).find('#infoEditor') : null;
      if (infoEditor && infoEditor.length && infoEditor.summernote) {
        const html = infoEditor.summernote('code');
        const hidden = form.querySelector('#infoHidden');
        if (hidden) hidden.value = html;
      }
    }catch(e){}

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
        const json = await res.json().catch(()=>({}));
        alert(Object.values(json.errors||{}).flat().join("\n"));
        return;
      }

      if(res.ok){
        bootstrap.Modal.getInstance(document.getElementById('serviceModal'))?.hide();
        return;
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
