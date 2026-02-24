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
  #serviceModal .info-line{margin:0 0 .28rem;line-height:1.45}
  #serviceModal .info-label{font-weight:600;color:#334155}
  #serviceModal .info-value{color:#111827}
  #serviceModal .info-badge{display:inline-block;padding:.12rem .52rem;border-radius:999px;font-size:.62rem;font-weight:700;line-height:1;color:#fff;vertical-align:middle;letter-spacing:.2px;text-transform:uppercase}
  #serviceModal .info-badge--green{background:#4caf50}
  #serviceModal .info-badge--red{background:#ef4444}
  #serviceModal .info-badge--amber{background:#f59e0b}
  #serviceModal .info-badge--gray{background:#6b7280}
  #serviceModal .info-image{display:block;max-width:220px;max-height:220px;object-fit:contain;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;padding:.25rem;margin:.35rem 0}
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

{{-- ✅ IMPORTANT: Global templates (so Edit works everywhere, not only inside /admin/apis modal) --}}
<template id="serviceCreateTpl_imei">@includeIf('admin.services.imei._modal_create')</template>
<template id="serviceCreateTpl_server">@includeIf('admin.services.server._modal_create')</template>
<template id="serviceCreateTpl_file">@includeIf('admin.services.file._modal_create')</template>
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

  const decodeB64Unicode = (v) => {
    try {
      if (!v) return '';
      return decodeURIComponent(Array.prototype.map.call(atob(String(v)), (c) =>
        '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)
      ).join(''));
    } catch (_) {
      return '';
    }
  };

  function normalizeInfo(value){
    if (value === undefined || value === null) return '';
    if (typeof value === 'object') {
      return clean(value.fallback ?? value.en ?? value.info ?? value.description ?? '');
    }

    const raw = clean(value).trim();
    if (!raw) return '';

    if ((raw.startsWith('{') && raw.endsWith('}')) || (raw.startsWith('[') && raw.endsWith(']'))) {
      try {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          return clean(parsed.fallback ?? parsed.en ?? parsed.info ?? parsed.description ?? raw);
        }
      } catch (_) {}
    }

    return raw;
  }

  function readInfoFromData(el){
    if (!el) return '';
    const b64 = clean(el.dataset.infoB64 || el.getAttribute('data-info-b64') || '');
    const b64Text = normalizeInfo(decodeB64Unicode(b64));
    if (b64Text) return b64Text;

    return normalizeInfo(el.dataset.info || el.getAttribute('data-info') || '');
  }


  const htmlEscape = (v) => clean(v)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

  const absolutizeProviderRelativeImages = (html) => {
    const base = String(window.__serviceModalProviderBaseUrl || '').trim().replace(/\/$/, '');
    if (!base) return html;

    return String(html || '').replace(/(<img[^>]+src\s*=\s*["'])(\/[^"'>\s]+)(["'][^>]*>)/gi, (_, p1, p2, p3) => {
      return `${p1}${base}${p2}${p3}`;
    });
  };

  const normalizeProviderHtml = (html) => {
    let out = String(html || '');
    if (!out) return '';

    out = absolutizeProviderRelativeImages(out);

    // Keep provider formatting but force consistent image display in editor preview.
    out = out.replace(/<img([^>]*)>/gi, (full, attrs) => {
      const hasClass = /class\s*=/.test(attrs);
      const hasStyle = /style\s*=/.test(attrs);
      const classPart = hasClass
        ? attrs.replace(/class\s*=\s*(["'])(.*?)/i, (m, q, cls) => `class=${q}${cls} info-image${q}`)
        : `${attrs} class="info-image"`;
      const stylePart = hasStyle
        ? classPart
        : `${classPart} style="max-width:220px;height:auto;display:block;margin:.35rem 0;object-fit:contain;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff;padding:.25rem;"`;
      return `<img${stylePart}>`;
    });

    return out;
  };

  function beautifyRemoteInfo(raw){
    const text = normalizeInfo(raw);
    if (!text) return '';

    const renderStatusBadge = (value, label = '') => {
      const v = value.trim();
      const vl = v.toLowerCase();
      const ll = String(label || '').toLowerCase();
      let cls = 'info-badge info-badge--gray';

      const findMyPhoneContext = ll.includes('find my iphone');

      if (findMyPhoneContext && vl === 'on') {
        cls = 'info-badge info-badge--red';
      } else if (findMyPhoneContext && vl === 'off') {
        cls = 'info-badge info-badge--green';
      } else if (['yes','active','activated','clean','on','unlocked'].includes(vl)) {
        cls = 'info-badge info-badge--green';
      } else if (['no','expired','blocked','blacklisted','off','locked','not active','lost mode'].includes(vl)) {
        cls = 'info-badge info-badge--red';
      } else if (['unknown','n/a','pending'].includes(vl)) {
        cls = 'info-badge info-badge--amber';
      }

      return `<span class="${cls}">${htmlEscape(v.toUpperCase())}</span>`;
    };

    const decorateStatusInHtml = (html) => {
      return String(html || '').replace(
        /(\:\s*)(yes|no|clean|locked|unlocked|active|activated|not\s+active|on|off|expired|pending|unknown|n\/a|lost\s+mode)(?=(?:<|\n|\r|\s*$))/gi,
        (_, prefix, word) => `${prefix}${renderStatusBadge(word)}`
      );
    };

    // If already HTML from provider/db, keep formatting and decorate status words.
    if (/<[^>]+>/.test(text)) return decorateStatusInHtml(normalizeProviderHtml(text));

    const extractImageUrl = (value) => {
      const v = clean(value).trim();
      if (!v) return '';

      const htmlImg = v.match(/<img[^>]+src\s*=\s*["']?([^"'\s>]+)/i);
      if (htmlImg?.[1]) return htmlImg[1];

      const direct = v.match(/(https?:\/\/[^\s"'<>]+|\/\/[^\s"'<>]+|\/[^\s"'<>]+|data:image\/[^\s"'<>]+)/i);
      if (!direct?.[1]) return '';

      const rawUrl = direct[1];
      if (rawUrl.startsWith('//')) return `https:${rawUrl}`;
      if (rawUrl.startsWith('/')) {
        const base = String(window.__serviceModalProviderBaseUrl || '').trim().replace(/\/$/, '');
        return base ? `${base}${rawUrl}` : rawUrl;
      }
      return rawUrl;
    };

    const renderValue = (value, label = "") => {
      const v = value.trim();
      if (/^(yes|no|active|activated|clean|on|unlocked|expired|blocked|blacklisted|off|locked|not active|lost mode|unknown|n\/a|pending)$/i.test(v)) {
        return renderStatusBadge(v, label);
      }
      const imgUrl = extractImageUrl(v);
      if (imgUrl) {
        return `<img class="info-image" src="${htmlEscape(imgUrl)}" alt="info image">`;
      }
      return `<span class="info-value">${htmlEscape(v)}</span>`;
    };

    const renderLine = (line) => {
      const i = line.indexOf(':');
      if (i <= 0) {
        const rawLine = line.trim();
        const imgUrl = extractImageUrl(rawLine);
        if (imgUrl) return `<div class="info-line"><img class="info-image" src="${htmlEscape(imgUrl)}" alt="info image"></div>`;
        return `<div class="info-line"><span class="info-value">${htmlEscape(line)}</span></div>`;
      }
      const label = line.slice(0, i).trim();
      const value = line.slice(i + 1).trim();
      return `<div class="info-line"><span class="info-label">${htmlEscape(label)}:</span> ${renderValue(value, label)}</div>`;
    };

    // Normalize noisy separators from providers.
    let prepared = text
      .replace(/\r\n?/g, '\n')
      .replace(/\u00a0/g, ' ')
      .replace(/\s{2,}/g, ' ')
      .trim();

    // Force line break before common section starters even when glued to previous text.
    const starters = [
      'The key components of this check include',
      'Other data points include',
      'Enhanced with',
      'Comparison',
      'Model', 'IMEI Number', 'MEID Number', 'Serial Number',
      'Activation Status', 'Warranty Status', 'Telephone Technical Support',
      'Repairs and Service Coverage', 'Repairs and Service Expiration Date',
      'AppleCare Eligible', 'Valid Purchase Date', 'Registered Device',
      'Replaced by Apple', 'Loaner Device', 'Purchase Date', 'Purchase Country',
      'Blacklist Status', 'iCloud Status', 'SIM-Lock Status', 'Find My iPhone',
      'Demo Unit', 'Carrier', 'Manufacturer Date'
    ];
    starters.forEach((label) => {
      const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const rx = new RegExp(`(?!^)${escaped}\\s*:`, 'gi');
      prepared = prepared.replace(rx, (m) => `\n${m}`);
    });

    // Primary parser: extract label:value pairs from dense blobs.
    const pairRegex = /([A-Za-z][A-Za-z0-9'()\/\- ]{2,60}):\s*([^:]+?)(?=(?:[A-Za-z][A-Za-z0-9'()\/\- ]{2,60}:\s)|$)/g;
    const pairs = [];
    for (const m of prepared.matchAll(pairRegex)) {
      const label = clean(m[1]).trim();
      let value = clean(m[2]).trim();
      value = value.replace(/[|]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
      if (label && value) pairs.push(`${label}: ${value}`);
    }

    if (pairs.length >= 4) {
      return pairs.map(renderLine).join('');
    }

    // Fallback: split by newlines and long comma chains.
    const lines = prepared
      .split('\n')
      .flatMap((line) => {
        const l = line.trim();
        if (!l) return [];
        return l.split(/,\s+(?=[A-Z][a-zA-Z ]{2,30}:)/g).map(x => x.trim()).filter(Boolean);
      });

    return lines.map(renderLine).join('');
  }


  function getCreateTpl(serviceType){
    const t = String(serviceType || '').toLowerCase().trim();
    return document.getElementById(`serviceCreateTpl_${t}`) || document.getElementById('serviceCreateTpl');
  }

  function runInjectedScripts(container){
    Array.from(container.querySelectorAll('script')).forEach(old => {
      const s = document.createElement('script');
      for (const attr of old.attributes) s.setAttribute(attr.name, attr.value);
      s.text = old.textContent || '';
      old.parentNode?.removeChild(old);
      container.appendChild(s);
    });
  }

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

  async function loadServiceGroups(scope, serviceType, selectedId = null){
    const sel = scope.querySelector('[name="group_id"]');
    if(!sel) return;

    try{
      const url = new URL("{{ route('admin.services.groups.options') }}", window.location.origin);
      if(serviceType) url.searchParams.set('type', String(serviceType).toLowerCase());

      const res = await fetch(url.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} });
      const rows = await res.json().catch(()=>[]);

      sel.innerHTML = `<option value="">Group</option>` +
        (Array.isArray(rows) ? rows.map(g=>`<option value="${g.id}">${clean(g.name)}</option>`).join('') : '');

      if(selectedId !== null && selectedId !== undefined && String(selectedId).trim() !== ''){
        sel.value = String(selectedId);
      }
    }catch(e){
      // ignore
    }
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
      (Array.isArray(rows) ? rows.map(r=>`<option value="${r.id}">${clean(r.name)}</option>`).join('') : '' );
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

      const allowExt = clean(
        s.allow_extension ?? s.allow_extensions ?? s.ALLOW_EXTENSION ?? s.extensions ?? s.EXTENSIONS ?? ''
      );

      const info = normalizeInfo(s.info ?? s.INFO ?? s.description ?? s.DESCRIPTION ?? '');
      const timeTxt = time ? ` — ${time}` : '';
      const ridTxt  = rid ? ` (#${rid})` : '';
      return `<option value="${rid}"
        data-name="${escAttr(name)}"
        data-credit="${creditTxt}"
        data-time="${escAttr(time)}"
        data-info="${escAttr(info)}"
        data-info-b64="${escAttr(btoa(unescape(encodeURIComponent(info))))}"
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

  function resolveHooks(serviceType){
    const t = String(serviceType || '').toLowerCase().trim();
    const apply  = window[`__${t}ServiceApplyRemoteFields__`] || window.__serverServiceApplyRemoteFields__ || null;
    const setMain= window[`__${t}ServiceSetMainField__`]      || window.__serverServiceSetMainField__      || null;
    const setExt = window[`__${t}ServiceSetAllowedExtensions__`] || null;
    return { apply, setMain, setExt };
  }

  // ==========================================================
  // ✅ CREATE / CLONE SERVICE
  // ==========================================================
  document.addEventListener('click', async (e)=>{
    const btn = e.target.closest('[data-create-service]');
    if(!btn) return;
    e.preventDefault();

    const modalEl = document.getElementById('serviceModal');
    const body = document.getElementById('serviceModalBody');

    const serviceType = (btn.dataset.serviceType || 'imei').toLowerCase();
    const tpl = getCreateTpl(serviceType);
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;
    runInjectedScripts(body);

    initTabs(body);
    openGeneralTab();

    const providerId = btn.dataset.providerId;
    const remoteId   = btn.dataset.remoteId;

    const isClone = (providerId && providerId !== 'undefined' && remoteId && remoteId !== 'undefined');
    const providerName = btn.dataset.providerName || document.querySelector('.card-header h5')?.textContent?.split('|')?.[0]?.trim() || '—';
    window.__serviceModalProviderBaseUrl = (btn.dataset.providerBaseUrl || '').trim();

    const cloneData = {
      providerId: isClone ? providerId : '',
      providerName,
      remoteId: isClone ? remoteId : '',
      groupName: clean(btn.dataset.groupName || ''),
      name: btn.dataset.name || '',
      credit: Number(btn.dataset.credit || 0),
      time: btn.dataset.time || '',
      info: readInfoFromData(btn) || '',
      serviceType
    };

    // ✅ set info early (and keep it before summernote init)
    const infoHidden0 = body.querySelector('#infoHidden');
    if (infoHidden0) infoHidden0.value = clean(cloneData.info);
    if (typeof window.setSummernoteHtmlIn === 'function') window.setSummernoteHtmlIn(body, beautifyRemoteInfo(cloneData.info));

    const hooks = resolveHooks(cloneData.serviceType);

    const afFromBtn = parseJsonAttr(btn.dataset.additionalFields || btn.getAttribute('data-additional-fields') || '');
    if (Array.isArray(afFromBtn) && afFromBtn.length) {
      hooks.apply?.(body, afFromBtn);
      const mf = guessMainFieldFromRemoteFields(afFromBtn);
      hooks.setMain?.(body, mf.type, mf.label);
      openGeneralTab();
    }

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

    await loadServiceGroups(body, cloneData.serviceType, null);

    const userGroups = await loadUserGroups();
    buildPricingTable(body, userGroups);

    ensureApiUI(body);
    body.querySelector('[name="source"]')?.addEventListener('change', ()=> ensureApiUI(body));
    await loadApiProviders(body);

    const apiProviderSel = body.querySelector('#apiProviderSelect');
    const apiServiceSel  = body.querySelector('#apiServiceSelect');

    const handleApiServiceChange = ()=>{
      const opt = apiServiceSel?.selectedOptions?.[0];
      if(!opt || !opt.value) return;

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

      // ✅ IMPORTANT FIX:
      // If API option info is empty (old backend), fallback to clone button info.
      let info = readInfoFromData(opt);
      if (!info && isClone && String(opt.value) === String(cloneData.remoteId)) {
        info = normalizeInfo(cloneData.info);
      }

      const infoHidden = body.querySelector('#infoHidden');
      if (infoHidden) infoHidden.value = info;
      if (typeof window.setSummernoteHtmlIn === 'function') window.setSummernoteHtmlIn(body, beautifyRemoteInfo(info));

      if(name){ body.querySelector('[name="name"]').value = name; body.querySelector('[name="alias"]').value = slugify(name); }
      if(time) body.querySelector('[name="time"]').value = time;

      if(Number.isFinite(credit) && credit >= 0){
        body.querySelector('[name="cost"]').value = credit.toFixed(4);
        priceHelper.setCost(credit);
      }

      // ✅ FIX: correct attribute name (allow-extensions)
      const allowExt = clean(opt.dataset.allowExtensions || opt.getAttribute('data-allow-extensions') || '');
      hooks.setExt?.(body, allowExt);

      const af = parseJsonAttr(opt.dataset.additionalFields || opt.getAttribute('data-additional-fields') || '');
      if (Array.isArray(af) && af.length) {
        hooks.apply?.(body, af);
        const mf = guessMainFieldFromRemoteFields(af);
        hooks.setMain?.(body, mf.type, mf.label);
        openGeneralTab();
      }
    };

    apiProviderSel?.addEventListener('change', async ()=>{
      if (apiProviderSel.disabled) return;
      const pid = apiProviderSel.value;
      if(!pid){ apiServiceSel.innerHTML = `<option value="">Select service...</option>`; return; }
      await loadProviderServices(body, pid, cloneData.serviceType);
      handleApiServiceChange();
    });

    apiServiceSel?.addEventListener('change', handleApiServiceChange);

    if (isClone && apiProviderSel) {
      const pid = String(cloneData.providerId || '').trim();
      if (pid && !apiProviderSel.querySelector(`option[value="${pid.replace(/"/g,'\\"')}"]`)) {
        const opt = document.createElement('option');
        opt.value = pid;
        opt.textContent = cloneData.providerName || ('Provider #' + pid);
        apiProviderSel.appendChild(opt);
      }
      apiProviderSel.value = pid;
      apiProviderSel.disabled = true;

      await loadProviderServices(body, pid, cloneData.serviceType);

      const opt2 = Array.from(apiServiceSel.options).find(o => String(o.value) === String(cloneData.remoteId));
      if (opt2) {
        apiServiceSel.value = opt2.value;
        handleApiServiceChange();
      }
    }



    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      if (typeof window.initSummernoteIn === 'function') {
        await window.initSummernoteIn(body);
        const ih = body.querySelector('#infoHidden');
        if (ih && typeof window.setSummernoteHtmlIn === 'function') window.setSummernoteHtmlIn(body, beautifyRemoteInfo(ih.value || ''));
      } else {
        console.error('Missing global summernote script: initSummernoteIn');
      }
    };
    modalEl.addEventListener('shown.bs.modal', onShown);

    const onHidden = () => {
      modalEl.removeEventListener('hidden.bs.modal', onHidden);
      window.destroySummernoteIn?.(body);
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);

    modal.show();
  });

  // ==========================================================
  // ✅ SAVE (CREATE/UPDATE) SERVICE
  // ==========================================================
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
        markCloneAsAdded(rid);

        const providerId = (form.querySelector('[name="supplier_id"]')?.value || '').trim();
        const kind       = (form.querySelector('[name="type"]')?.value || '').trim().toLowerCase();

        if (providerId && kind && rid) {
          window.__gsmmixAdded = window.__gsmmixAdded || {};
          window.__gsmmixAdded[`${providerId}:${kind}:${rid}`] = true;

          window.dispatchEvent(new CustomEvent('gsmmix:service-created', {
            detail: { provider_id: providerId, kind: kind, remote_id: rid }
          }));
        }

        const methodVal = String(form.querySelector('input[name="_method"]')?.value || '').toUpperCase();
        const isEditMode = methodVal === 'PUT';

        window.bootstrap.Modal.getInstance(document.getElementById('serviceModal'))?.hide();
        window.showToast?.('success', isEditMode ? '✅ Service updated successfully' : '✅ Service created successfully', { title: 'Done' });

        if (isEditMode && window.location.pathname.includes('/admin/service-management/')) {
          setTimeout(() => window.location.reload(), 200);
        }
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
  // ✅ EDIT SERVICE (works everywhere now)
  // ==========================================================
  async function openEditService(btn){
    const modalEl = document.getElementById('serviceModal');
    const body = document.getElementById('serviceModalBody');

    const jsonUrl   = btn.dataset.jsonUrl;
    const updateUrl = btn.dataset.updateUrl;
    if(!jsonUrl || !updateUrl) return alert('Missing json/update URL');

    const res = await fetch(jsonUrl, { headers:{'X-Requested-With':'XMLHttpRequest'} });
    const payload = await res.json().catch(()=>null);
    if(!res.ok || !payload?.ok) return alert(payload?.msg || 'Failed to load service');

    const s = payload.service || {};
    window.__serviceModalProviderBaseUrl = '';
    const serviceType = (btn.dataset.serviceType || s.type || 'imei').toLowerCase();

    const tpl = getCreateTpl(serviceType);
    if(!tpl) return alert('Template not found');

    body.innerHTML = tpl.innerHTML;
    runInjectedScripts(body);

    initTabs(body);
    openGeneralTab();

    document.getElementById('serviceModalSubtitle').innerText = `Edit Service #${s.id || ''}`;
    document.getElementById('badgeType').innerText = `Type: ${(serviceType || s.type || '').toUpperCase()}`;

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

    const submitBtn = form.querySelector('[type="submit"]');
    if(submitBtn) submitBtn.textContent = 'Save';

    const nameText = (typeof s.name === 'object' ? (s.name.fallback || s.name.en || '') : (s.name || ''));
    const timeText = (typeof s.time === 'object' ? (s.time.fallback || s.time.en || '') : (s.time || ''));
    const infoText = normalizeInfo(s.info ?? s.INFO ?? s.description ?? s.DESCRIPTION ?? '');

    form.querySelector('[name="name"]') && (form.querySelector('[name="name"]').value = nameText);
    form.querySelector('[name="alias"]') && (form.querySelector('[name="alias"]').value = s.alias || '');
    form.querySelector('[name="time"]') && (form.querySelector('[name="time"]').value = timeText);

    const infoHidden = form.querySelector('#infoHidden');
    if(infoHidden) infoHidden.value = infoText;
    if (typeof window.setSummernoteHtmlIn === 'function') window.setSummernoteHtmlIn(body, beautifyRemoteInfo(infoText));

    form.querySelector('[name="cost"]') && (form.querySelector('[name="cost"]').value = Number(s.cost||0).toFixed(4));
    form.querySelector('[name="profit"]') && (form.querySelector('[name="profit"]').value = Number(s.profit||0).toFixed(4));
    form.querySelector('[name="profit_type"]') && (form.querySelector('[name="profit_type"]').value = String(s.profit_type||1));

    if(form.querySelector('[name="source"]')){
      form.querySelector('[name="source"]').value = String(s.source || 1);
    }

    if(form.querySelector('[name="supplier_id"]')) form.querySelector('[name="supplier_id"]').value = s.supplier_id ?? '';
    if(form.querySelector('[name="remote_id"]')) form.querySelector('[name="remote_id"]').value = s.remote_id ?? '';

    await loadServiceGroups(body, serviceType || s.type || 'imei', s.group_id ?? null);

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
    } catch (_) {}

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

    const params = s.params || {};
    if(form.querySelector('#allowedExtensionsPreview') && params.allowed_extensions){
      form.querySelector('#allowedExtensionsPreview').value = String(params.allowed_extensions);
    }

    const custom = Array.isArray(params.custom_fields) ? params.custom_fields : [];
    if(custom.length){
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

    const userGroups = await loadUserGroups();
    buildPricingTable(body, userGroups);

    try{
      const gp = Array.isArray(s.group_prices) ? s.group_prices : [];
      if(gp.length){
        setTimeout(()=>{
          gp.forEach(row=>{
            const gid = String(row.group_id || '');
            if(!gid) return;
            const priceInput = body.querySelector(`[name="group_prices[${gid}][price]"]`);
            const discInput  = body.querySelector(`[name="group_prices[${gid}][discount]"]`);
            const typeSel    = body.querySelector(`[name="group_prices[${gid}][discount_type]"]`);
            if(priceInput) { priceInput.dataset.autoPrice = '1'; }
            if(discInput)  discInput.value = Number(row.discount||0).toFixed(4);
            if(typeSel)    typeSel.value = String(row.discount_type||1);
            discInput?.dispatchEvent(new Event('input'));
            typeSel?.dispatchEvent(new Event('change'));
          });
        }, 300);
      }
    }catch(e){}

    const priceHelper = initPrice(body);
    priceHelper.setCost(Number(s.cost||0));

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    const onShown = async () => {
      modalEl.removeEventListener('shown.bs.modal', onShown);
      if (typeof window.initSummernoteIn === 'function') {
        await window.initSummernoteIn(body);
        const ih = body.querySelector('#infoHidden');
        if (ih && typeof window.setSummernoteHtmlIn === 'function') window.setSummernoteHtmlIn(body, beautifyRemoteInfo(ih.value || ''));
      }
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
