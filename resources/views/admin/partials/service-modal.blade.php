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
      #serviceModal .tabs-top button{border:0;background:#ffffff22;color:#fff;padding:.35rem .8rem;border-radius:.35rem;font-size:.85rem}
      #serviceModal .tabs-top button.active{background:#fff;color:#000}
      #serviceModal .badge-box{display:flex;gap:.4rem;align-items:center;margin-left:1rem}
      #serviceModal .badge-box .badge{background:#111;color:#fff;padding:.35rem .55rem;border-radius:.35rem;font-size:.75rem}
      #serviceModal .tab-pane{display:none}
      #serviceModal .tab-pane.active{display:block}
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

    // ✅ Tabs logic (FIXED: always keep one pane active)
    function initTabs(scope){
      const btns = document.querySelectorAll('#serviceModal .tab-btn');

      function activate(tab){
        btns.forEach(b=>b.classList.toggle('active', b.dataset.tab === tab));
        const panes = scope.querySelectorAll('.tab-pane');
        panes.forEach(p=>p.classList.toggle('active', p.dataset.tab === tab));

        // ✅ fallback: إذا لم يجد pane للتاب، خلي general فعال
        const anyActive = Array.from(panes).some(p=>p.classList.contains('active'));
        if(!anyActive){
          panes.forEach(p=>p.classList.toggle('active', p.dataset.tab === 'general'));
          btns.forEach(b=>b.classList.toggle('active', b.dataset.tab === 'general'));
        }
      }

      btns.forEach(btn=>{
        btn.onclick = ()=> activate(btn.dataset.tab);
      });

      // default
      activate('general');
    }

    // ---------- ✅ Summernote ----------
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

    // ✅ Price calc
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

      return {
        setCost(v){
          if(cost){
            cost.value = Number(v||0).toFixed(4);
          }
          recalc();
        }
      };
    }

    // ✅ Alias from name
    function slugify(text){
      return String(text||'')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g,'-')
        .replace(/^-+|-+$/g,'');
    }

    // ✅ Init API dropdowns
    async function initApi(scope, cloneData){
      await ensureSelect2();
      const $ = window.jQuery;
      const $modal = $('#serviceModal');

      const $prov = $(scope).find('.js-api-provider');
      const $srv  = $(scope).find('.js-api-service');

      $prov.select2({
        dropdownParent:$modal,
        width:'100%',
        placeholder:'API connection',
        ajax:{
          url:"{{ route('admin.apis.options') }}",
          delay:150,
          data:(p)=>({q:p.term||''}),
          processResults:(rows)=>({results:rows.map(r=>({id:r.id,text:r.name}))})
        }
      });

      $srv.select2({
        dropdownParent:$modal,
        width:'100%',
        placeholder:'API service',
        ajax:{
          url:"{{ route('admin.services.clone.provider_services') }}",
          delay:150,
          data:(p)=>({
            provider_id:$prov.val()||'',
            type:(cloneData.serviceType||'imei'),
            q:p.term||''
          }),
          processResults:(rows)=>({
            results:rows.map(x=>({
              id:x.id,
              text:(x.text||x.name||'Service') + ' — ' + Number(x.credit||0).toFixed(4),
              credit:Number(x.credit||0)
            }))
          })
        }
      });

      return { $prov, $srv };
    }

    // ✅ Load groups
    function loadGroups(scope, type){
      fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(type))
        .then(r=>r.json())
        .then(rows=>{
          const sel = scope.querySelector('[name="group_id"]');
          if(!sel) return;
          sel.innerHTML = `<option value="">Group</option>` + rows.map(g=>`<option value="${g.id}">${g.name}</option>`).join('');
        });
    }

    // ✅ Open modal on Clone
    document.addEventListener('click', async (e)=>{
      const btn = e.target.closest('[data-create-service]');
      if(!btn) return;

      e.preventDefault();

      const body = document.getElementById('serviceModalBody');
      const tpl = document.getElementById('serviceCreateTpl');
      if(!tpl) return alert('Template not found');

      body.innerHTML = tpl.innerHTML;

      // ✅ Init tabs AFTER template loaded
      initTabs(body);

      await ensureSummernote();
      await ensureSelect2();

      // ✅ Summernote always on Info tab
      jQuery(body).find('#infoEditor').summernote({ placeholder:'Description, notes, terms…', height:320 });

      const cloneData = {
        providerId: btn.dataset.providerId,
        providerName: btn.dataset.providerName,
        remoteId: btn.dataset.remoteId,
        name: btn.dataset.name,
        credit: Number(btn.dataset.credit||0),
        time: btn.dataset.time,
        serviceType: btn.dataset.serviceType || 'imei'
      };

      // Header
      document.getElementById('serviceModalSubtitle').innerText =
        `Provider: ${cloneData.providerName} | Remote ID: ${cloneData.remoteId}`;

      document.getElementById('badgeType').innerText =
        `Type: ${cloneData.serviceType.toUpperCase()}`;

      // Fill fields
      body.querySelector('[name="supplier_id"]').value = cloneData.providerId;
      body.querySelector('[name="remote_id"]').value = cloneData.remoteId;
      body.querySelector('[name="name"]').value = cloneData.name;
      body.querySelector('[name="time"]').value = cloneData.time;
      body.querySelector('[name="cost"]').value = cloneData.credit.toFixed(4);
      body.querySelector('[name="profit"]').value = '0.0000';
      body.querySelector('[name="source"]').value = 2;
      body.querySelector('[name="type"]').value = cloneData.serviceType;

      // Alias auto
      body.querySelector('[name="alias"]').value = slugify(cloneData.name);

      // Price helper
      const priceHelper = initPrice(body);
      priceHelper.setCost(cloneData.credit);

      // Groups
      loadGroups(body, cloneData.serviceType);

      // Init API dropdowns
      const api = await initApi(body, cloneData);

      // preselect provider
      const optProv = new Option(cloneData.providerName, cloneData.providerId, true, true);
      api.$prov.append(optProv).trigger('change');

      // preselect service
      const srvText = `${cloneData.name} — ${cloneData.credit.toFixed(4)}`;
      const optSrv = new Option(srvText, cloneData.remoteId, true, true);
      api.$srv.append(optSrv).trigger('change');

      bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
    });

    // ✅ Ajax submit
    document.addEventListener('submit', async (ev)=>{
      const form = ev.target.closest('#serviceModal form[data-ajax="1"]');
      if(!form) return;
      ev.preventDefault();

      form.querySelector('#infoHidden').value = jQuery(form).find('#infoEditor').summernote('code');

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
