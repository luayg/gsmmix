{{-- resources/views/admin/partials/service-modal.blade.php --}}
@once
  @push('styles')
    <style>
      #serviceModal .modal-dialog{width:96vw;max-width:min(1400px,96vw);margin:1rem auto}
      #serviceModal .modal-content{display:flex;flex-direction:column;max-height:96dvh;border-radius:.6rem;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,.18)}
      #serviceModal .modal-header{
        background:#37b37e;
        color:#fff;
        padding:.8rem 1rem;
        border:0;
        display:flex;
        align-items:center;
        justify-content:space-between
      }

      #serviceModal .modal-title{font-weight:600;margin:0}
      #serviceModal .btn-close{filter:invert(1);opacity:.9}

      #serviceModal .modal-body{
        flex:1 1 auto;
        padding:14px !important;
        overflow-y:auto !important;
        overflow-x:hidden;
      }

      #serviceModal .service-tabs .btn{
        background:rgba(255,255,255,.12);
        border:0;
        color:#fff;
        font-weight:600;
        margin-left:6px;
        padding:.35rem .8rem;
        border-radius:.35rem;
      }
      #serviceModal .service-tabs .btn.active{
        background:#fff;
        color:#37b37e;
      }

      #serviceModal .badge-pill{
        background:#111;
        padding:.4rem .8rem;
        border-radius:.4rem;
        font-weight:600;
        color:#fff;
        margin-left:8px;
      }

      #serviceModal .select2-container{width:100% !important;z-index:2055}
      #serviceModal .select2-dropdown{z-index:2056}
      #serviceModal .note-editor.note-frame .note-editable{min-height:340px}
    </style>
  @endpush

  @push('modals')
  <div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">

        {{-- ✅ Header Green Bar --}}
        <div class="modal-header">

          <div>
            <h5 class="modal-title" id="serviceModalTitle">Create service</h5>
            <small id="serviceModalSubTitle" style="opacity:.85; font-size:12px;">
              Provider: — • Remote ID: —
            </small>
          </div>

          <div class="d-flex align-items-center">
            <div class="service-tabs">
              <button type="button" class="btn active" data-tab="general">General</button>
              <button type="button" class="btn" data-tab="additional">Additional</button>
              <button type="button" class="btn" data-tab="meta">Meta</button>
            </div>

            <span class="badge-pill" id="serviceModalTypeBadge">Type: IMEI</span>
            <span class="badge-pill" id="serviceModalPriceBadge">Price: 0.0000 Credits</span>

            <button type="button" class="btn-close ms-3" data-bs-dismiss="modal"></button>
          </div>

        </div>

        <div class="modal-body" id="serviceModalBody"></div>

      </div>
    </div>
  </div>
  @endpush


  @push('scripts')
  <script>
  (function(){

    const loadCssOnce=(id,href)=>{
      if(document.getElementById(id)) return;
      const l=document.createElement('link');
      l.id=id; l.rel='stylesheet'; l.href=href;
      document.head.appendChild(l);
    };

    const loadScriptOnce=(id,src)=>new Promise((res,rej)=>{
      const e=document.getElementById(id);
      if(e) return res();
      const s=document.createElement('script');
      s.id=id; s.src=src; s.async=false;
      s.onload=res; s.onerror=rej;
      document.body.appendChild(s);
    });

    async function ensureSummernote(){
      loadCssOnce('sn-lite-css','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');
      if(!window.jQuery || !window.jQuery.fn?.summernote){
        await loadScriptOnce('jq-371','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
        window.$=window.jQuery;
        await loadScriptOnce('sn-lite-js','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');
      }
    }

    async function ensureSelect2(){
      if(!window.jQuery) await loadScriptOnce('jq-371','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
      if(!$.fn?.select2){
        loadCssOnce('sel2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        await loadScriptOnce('sel2-js','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
      }
    }

    function initTabs(scope){
      const tabs = document.querySelectorAll('#serviceModal .service-tabs .btn');
      const panes = scope.querySelectorAll('.service-tab-pane');

      tabs.forEach(btn=>{
        btn.addEventListener('click', ()=>{
          tabs.forEach(b=>b.classList.remove('active'));
          btn.classList.add('active');

          const target = btn.dataset.tab;
          panes.forEach(p=>{
            p.classList.toggle('d-none', p.dataset.tab !== target);
          });
        });
      });

      // show default
      panes.forEach(p=>p.classList.toggle('d-none', p.dataset.tab!=='general'));
    }

    // ✅ price = cost + profit
    function initPriceAuto(scope){
      const toNum=(v)=>Number(String(v||'').replace(/[^0-9.\-]/g,''))||0;
      const fmt=(n)=> (Number.isFinite(n)?n.toFixed(4):'0.0000');
      const cost=scope.querySelector('[name="cost"]');
      const profit=scope.querySelector('[name="profit"]');
      const pType=scope.querySelector('[name="profit_type"]');
      const pricePreview=scope.querySelector('#pricePreview');
      const convPreview=scope.querySelector('#convertedPricePreview');

      const recalc=()=>{
        const c=toNum(cost?.value);
        const p=toNum(profit?.value);
        const perc=(pType?.value=='2');
        const price= perc?(c+(c*p/100)):(c+p);
        if(pricePreview) pricePreview.value=fmt(price);
        if(convPreview)  convPreview.value=fmt(price);

        document.getElementById('serviceModalPriceBadge').innerText = 'Price: '+fmt(price)+' Credits';
      };

      [cost,profit,pType].forEach(el=>el && el.addEventListener('input',recalc));
      recalc();

      return { recalc };
    }


    async function initApiPickers(scope){
      await ensureSelect2();
      const $=window.jQuery;
      const $modal=$('#serviceModal');

      const $prov=$(scope).find('.js-api-provider');
      const $service=$(scope).find('.js-api-service');
      const $source=$(scope).find('[name="source"]');
      let apiBlockEl=scope.querySelector('.js-api-block');

      function toggleApiBlock(){
        if(!apiBlockEl) apiBlockEl=scope.querySelector('.js-api-block');
        if(!apiBlockEl) return;
        const val=($source.val()||'').toString().toLowerCase();
        apiBlockEl.classList.toggle('d-none', val!=='api');
      }
      toggleApiBlock();
      $source.on('change',toggleApiBlock);

      $prov.select2({
        dropdownParent:$modal,
        width:'100%',
        placeholder:'API connection',
        ajax:{
          url:"{{ route('admin.apis.options') }}",
          delay:150,
          data:(params)=>({q:params?.term||''}),
          processResults:(rows)=>({results:rows.map(r=>({id:r.id,text:r.name}))})
        }
      });

      $service.select2({
        dropdownParent:$modal,
        width:'100%',
        placeholder:'API service'
      });

      return { $prov, $service };
    }


    document.addEventListener('click', async (e)=>{
      const btn=e.target.closest('[data-create-service]');
      if(!btn) return;

      e.preventDefault();

      document.querySelectorAll('.modal.show').forEach(m=>{
        const inst = bootstrap.Modal.getInstance(m);
        if(inst) inst.hide();
      });

      const body=document.getElementById('serviceModalBody');
      const tpl=document.getElementById('serviceCreateTpl');
      if(!body || !tpl){
        alert('Create modal template not found');
        return;
      }

      body.innerHTML=tpl.innerHTML;

      await ensureSummernote();
      await ensureSelect2();

      if(typeof window.initModalCreateSummernote==='function'){
        window.initModalCreateSummernote(body);
      }

      initTabs(body);
      const priceHelper = initPriceAuto(body);
      const pickers = await initApiPickers(body);

      // fill fields
      const name   = btn.dataset.name || '';
      const remote = btn.dataset.remoteId || '';
      const time   = btn.dataset.time || '';
      const credit = Number(btn.dataset.credit || 0);
      const providerId = btn.dataset.providerId || '';
      const providerName = btn.dataset.providerName || '';

      document.getElementById('serviceModalSubTitle').innerText =
        "Provider: "+providerName+" • Remote ID: "+remote;

      body.querySelector('[name="name"]').value = name;
      body.querySelector('[name="alias"]').value = remote;
      body.querySelector('[name="time"]').value = time;
      body.querySelector('[name="cost"]').value = credit.toFixed(4);
      body.querySelector('[name="source"]').value = 'api';

      document.getElementById('serviceModalTypeBadge').innerText = "Type: "+(btn.dataset.serviceType||'imei').toUpperCase();
      document.getElementById('serviceModalPriceBadge').innerText = "Price: "+credit.toFixed(4)+" Credits";

      // set provider selected
      if(providerId){
        const opt = new Option(providerName, providerId, true, true);
        pickers.$prov.append(opt).trigger('change');
      }

      // set service selected (remote id)
      const optS = new Option(name+" — "+credit.toFixed(4), remote, true, true);
      pickers.$service.append(optS).trigger('change');

      bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
    });


    document.addEventListener('submit', async (ev)=>{
      const form=ev.target.closest('#serviceModal form[data-ajax="1"]');
      if(!form) return;
      ev.preventDefault();

      if(window.jQuery?.fn?.summernote && jQuery('#infoEditor').length){
        const html=jQuery('#infoEditor').summernote('code');
        form.querySelector('#infoHidden').value=html;
      }

      const btn=form.querySelector('[type="submit"]');
      btn?.setAttribute('disabled',true);

      try{
        const res=await fetch(form.action,{
          method:form.method,
          headers:{
            'X-Requested-With':'XMLHttpRequest',
            'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''
          },
          body:new FormData(form)
        });

        btn?.removeAttribute('disabled');

        if(res.ok){
          bootstrap.Modal.getInstance(document.getElementById('serviceModal'))?.hide();
          location.reload();
        }else{
          alert('Failed to save service');
        }

      }catch(e){
        btn?.removeAttribute('disabled');
        alert('Network error');
      }
    });

  })();
  </script>
  @endpush
@endonce
