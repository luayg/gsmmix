{{-- resources/views/admin/partials/service-modal.blade.php --}}
@once
  @push('styles')
    <style>
      #serviceModal .modal-dialog{width:96vw;max-width:min(1400px,96vw);margin:1rem auto}
      #serviceModal .modal-content{display:flex;flex-direction:column;max-height:96dvh;border-radius:.6rem;overflow:hidden}
      #serviceModal .modal-header{background:#3bb37a;color:#fff;padding:.75rem 1rem;border:0;display:flex;align-items:center;gap:1rem}
      #serviceModal .modal-title{font-weight:600}
      #serviceModal .modal-body{flex:1 1 auto;overflow:auto;padding:1rem;background:#fff}

      #serviceModal .tabs-top{display:flex;gap:.5rem;margin-left:auto}
      #serviceModal .tabs-top button{
        border:0;background:#ffffff22;color:#fff;
        padding:.35rem .8rem;border-radius:.35rem;font-size:.85rem
      }
      #serviceModal .tabs-top button.active{background:#fff;color:#000}

      #serviceModal .badge-box{display:flex;gap:.4rem;align-items:center;margin-left:1rem}
      #serviceModal .badge-box .badge{background:#111;color:#fff;padding:.35rem .55rem;border-radius:.35rem;font-size:.75rem}

      #serviceModal .tab-pane{display:none}
      #serviceModal .tab-pane.active{display:block}

      /* Custom fields blocks style */
      .cf-block{border:1px solid #e5e5e5;border-radius:.35rem;padding:.75rem;background:#f9fafb;position:relative}
      .cf-remove{position:absolute;top:.4rem;right:.4rem;border:0;background:#ff4d4f;color:#fff;border-radius:.25rem;width:22px;height:22px;line-height:20px}
      .cf-title{font-weight:600;margin-bottom:.4rem}
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

    /* ✅ Tabs */
    function initTabs(scope){
      const btns  = document.querySelectorAll('#serviceModal .tab-btn');
      const panes = scope.querySelectorAll('.tab-pane');

      function activate(tab){
        btns.forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
        panes.forEach(p => p.classList.toggle('active', p.dataset.tab === tab));
      }
      btns.forEach(btn => btn.onclick = ()=> activate(btn.dataset.tab));
      activate('general');
    }

    /* ✅ Helpers */
    const loadCssOnce=(id,href)=>{
      if(document.getElementById(id)) return;
      const l=document.createElement('link'); l.id=id; l.rel='stylesheet'; l.href=href;
      document.head.appendChild(l);
    };
    const loadScriptOnce=(id,src)=>new Promise((res,rej)=>{
      if(document.getElementById(id)) return res();
      const s=document.createElement('script'); s.id=id; s.src=src; s.async=false;
      s.onload=res; s.onerror=rej; document.body.appendChild(s);
    });

    async function ensureSummernote(){
      loadCssOnce('sn-css','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');
      if(!window.jQuery || !window.jQuery.fn?.summernote){
        await loadScriptOnce('jq-371','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
        window.$=window.jQuery;
        await loadScriptOnce('sn-js','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');
      }
    }

    function slugify(text){
      return String(text||'')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g,'-')
        .replace(/^-+|-+$/g,'');
    }

    /* ✅ Price calc */
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
        document.getElementById('badgePrice').innerText = 'Price: ' + price.toFixed(4) + ' Credits';
      }

      [cost,profit,pType].forEach(el=> el && el.addEventListener('input', recalc));
      recalc();

      return { setCost(v){ if(cost) cost.value=Number(v||0).toFixed(4); recalc(); } };
    }

    /* ✅ Load groups + build pricing table + fill dropdown */
    function loadGroups(body, serviceType, defaultPrice){

      fetch("{{ route('admin.services.groups.options') }}?type=" + encodeURIComponent(serviceType))
        .then(r => r.json())
        .then(rows => {

          // ✅ 1) fill group dropdown
          const sel = body.querySelector('[name="group_id"]');
          if(sel){
            sel.innerHTML =
              `<option value="">Group</option>` +
              rows.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
          }

          // ✅ 2) build pricing table in Additional tab
          const wrap = body.querySelector('#groupsPricingWrap');
          if(!wrap) return;

          const html = rows.map(g => `
            <div class="p-3 border-bottom">
              <div class="fw-bold bg-light border rounded px-3 py-2 mb-2">${g.name}</div>

              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small">Price</label>
                  <div class="input-group">
                    <input type="number" step="0.0001"
                           class="form-control group-price"
                           data-group="${g.id}"
                           value="${Number(defaultPrice||0).toFixed(4)}">
                    <span class="input-group-text">Credits</span>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label small">Discount</label>
                  <div class="input-group">
                    <input type="number" step="0.0001"
                           class="form-control group-discount"
                           data-group="${g.id}"
                           value="0.0000">

                    <select class="form-select group-discount-type"
                            data-group="${g.id}"
                            style="max-width:110px;">
                      <option value="1" selected>Credits</option>
                      <option value="2">%</option>
                    </select>

                    <button type="button" class="btn btn-light btn-reset" data-group="${g.id}">
                      Reset
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `).join('');

          wrap.innerHTML = html;

          const hid = body.querySelector('#pricingTableHidden');
          function updatePricingHidden(){
            const pricing = rows.map(g=>{
              const gid = g.id;
              return {
                group_id: gid,
                price: Number(wrap.querySelector(`.group-price[data-group="${gid}"]`).value || 0),
                discount: Number(wrap.querySelector(`.group-discount[data-group="${gid}"]`).value || 0),
                discount_type: Number(wrap.querySelector(`.group-discount-type[data-group="${gid}"]`).value || 1),
              };
            });

            if(hid) hid.value = JSON.stringify(pricing);
          }

          wrap.querySelectorAll('input,select').forEach(el=>{
            el.addEventListener('input', updatePricingHidden);
            el.addEventListener('change', updatePricingHidden);
          });

          wrap.querySelectorAll('.btn-reset').forEach(btn=>{
            btn.addEventListener('click', ()=>{
              const gid = btn.dataset.group;
              wrap.querySelector(`.group-price[data-group="${gid}"]`).value = Number(defaultPrice||0).toFixed(4);
              wrap.querySelector(`.group-discount[data-group="${gid}"]`).value = "0.0000";
              wrap.querySelector(`.group-discount-type[data-group="${gid}"]`).value = "1";
              updatePricingHidden();
            });
          });

          updatePricingHidden();
        })
        .catch(err=>{
          console.error("loadGroups error:", err);
        });
    }

    /* ✅ Custom fields UI (REAL) */
    function initFieldsUI(body){

      const wrap = body.querySelector('#fieldsWrap');
      const hid  = body.querySelector('#customFieldsHidden');
      const addBtn = body.querySelector('#btnAddField');

      if(!wrap || !hid || !addBtn) return;

      function serialize(){
        const blocks = wrap.querySelectorAll('.cf-block');
        const fields = Array.from(blocks).map(b=>{
          return {
            active: b.querySelector('.cf-active')?.checked ? 1 : 0,
            name: b.querySelector('.cf-name')?.value || '',
            type: b.querySelector('.cf-type')?.value || 'text',
            input_name: b.querySelector('.cf-input')?.value || '',
            description: b.querySelector('.cf-desc')?.value || '',
            min: b.querySelector('.cf-min')?.value || '',
            max: b.querySelector('.cf-max')?.value || '',
            validation: b.querySelector('.cf-validation')?.value || '',
            required: b.querySelector('.cf-required')?.value || '0',
          };
        }).filter(f=> f.name || f.input_name);

        hid.value = JSON.stringify(fields);
      }

      function newBlock(){
        const idx = Date.now();
        const div = document.createElement('div');
        div.className = 'cf-block mb-3';
        div.innerHTML = `
          <button type="button" class="cf-remove" title="Remove">×</button>

          <div class="form-check form-switch mb-2">
            <input class="form-check-input cf-active" type="checkbox" checked>
            <label class="form-check-label">Active</label>
          </div>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small">Name</label>
              <input class="form-control cf-name">
            </div>

            <div class="col-md-6">
              <label class="form-label small">Field type</label>
              <select class="form-select cf-type">
                <option value="text">Text</option>
                <option value="number">Number</option>
                <option value="select">Select</option>
                <option value="textarea">Textarea</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small">Input name</label>
              <input class="form-control cf-input" placeholder="Machine name">
            </div>

            <div class="col-md-6">
              <label class="form-label small">Description</label>
              <input class="form-control cf-desc">
            </div>

            <div class="col-md-3">
              <label class="form-label small">Minimum</label>
              <input class="form-control cf-min" placeholder="0">
            </div>

            <div class="col-md-3">
              <label class="form-label small">Maximum</label>
              <input class="form-control cf-max" placeholder="Unlimited">
            </div>

            <div class="col-md-3">
              <label class="form-label small">Validation</label>
              <select class="form-select cf-validation">
                <option value="">None</option>
                <option value="imei">IMEI</option>
                <option value="serial">Serial</option>
                <option value="email">Email</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small">Required</label>
              <select class="form-select cf-required">
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
            </div>
          </div>
        `;

        div.querySelector('.cf-remove').onclick = ()=>{
          div.remove();
          serialize();
        };

        div.querySelectorAll('input,select').forEach(el=>{
          el.addEventListener('input', serialize);
          el.addEventListener('change', serialize);
        });

        wrap.appendChild(div);
        serialize();
      }

      addBtn.onclick = (e)=>{
        e.preventDefault();
        newBlock();
      };

      serialize();
    }

    /* ✅ OPEN modal from Clone */
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

      // summernote
      jQuery(body).find('#infoEditor').removeClass('d-none').summernote({
        placeholder:'Write description, rules, terms...',
        height:320
      });

      // clone data
      const cloneData = {
        providerId: btn.dataset.providerId || '',
        providerName: btn.dataset.providerName || '',
        remoteId: btn.dataset.remoteId || '',
        name: btn.dataset.name || '',
        credit: Number(btn.dataset.credit||0),
        time: btn.dataset.time || '',
        serviceType: btn.dataset.serviceType || 'imei'
      };

      // header info
      document.getElementById('serviceModalSubtitle').innerText =
        `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`;
      document.getElementById('badgeType').innerText =
        `Type: ${cloneData.serviceType.toUpperCase()}`;

      // fill fields
      body.querySelector('[name="supplier_id"]').value = cloneData.providerId;
      body.querySelector('[name="remote_id"]').value   = cloneData.remoteId;
      body.querySelector('[name="name"]').value        = cloneData.name;
      body.querySelector('[name="time"]').value        = cloneData.time;
      body.querySelector('[name="cost"]').value        = cloneData.credit.toFixed(4);
      body.querySelector('[name="profit"]').value      = '0.0000';
      body.querySelector('[name="alias"]').value       = slugify(cloneData.name);
      body.querySelector('[name="type"]').value        = cloneData.serviceType;

      const priceHelper = initPrice(body);
      priceHelper.setCost(cloneData.credit);

      /* ✅ IMPORTANT: load groups + pricing table */
      loadGroups(body, cloneData.serviceType, cloneData.credit);

      /* ✅ IMPORTANT: init fields UI */
      initFieldsUI(body);

      bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
    }, true);

    /* ✅ Ajax submit */
    document.addEventListener('submit', async (ev)=>{
      const form = ev.target.closest('#serviceModal form[data-ajax="1"]');
      if(!form) return;
      ev.preventDefault();

      // summernote -> hidden
      if(window.jQuery?.fn?.summernote){
        const html = jQuery(form).find('#infoEditor').summernote('code');
        form.querySelector('#infoHidden').value = html;
      }

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
