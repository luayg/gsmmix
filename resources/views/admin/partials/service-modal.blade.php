{{-- resources/views/admin/partials/service-modal.blade.php --}}
@once
  @push('styles')
    <style>
      #serviceModal .modal-dialog{width:96vw;max-width:min(1200px,96vw);margin:1rem auto}
      #serviceModal .modal-content{display:flex;flex-direction:column;max-height:96dvh;border-radius:.8rem;overflow:hidden;box-shadow:0 10px 35px rgba(0,0,0,.18)}
      #serviceModal .modal-header{background:#0d6efd;color:#fff;padding:.65rem .95rem;border:0}
      #serviceModal .modal-title{font-weight:600}
      #serviceModal .btn-close{filter:invert(1);opacity:.9}
      #serviceModal .modal-body{flex:1 1 auto;padding:14px !important;overflow-y:auto !important;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y;scrollbar-width:none}
      #serviceModal .modal-body::-webkit-scrollbar{width:0;height:0}
      #serviceModal .modal-body .row{margin-left:0 !important;margin-right:0 !important}
      #serviceModal .modal-body .col,#serviceModal .modal-body [class^="col-"]{padding-left:.35cm;padding-right:.35cm}
      #serviceModal .note-editor.note-frame .note-editable{min-height:320px}
      #serviceModal .select2-container{width:100% !important;z-index:2055}
      #serviceModal .select2-dropdown{z-index:2056}
      #serviceModal .select2-container--open .select2-dropdown--above{top:100% !important;bottom:auto !important}
    </style>
  @endpush

  @push('modals')
  <div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create service</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

    // ✅ تعبئة من الخدمة المنسوخة
    window.prefillFromRemote=function(rootEl,data){
      const root=rootEl instanceof HTMLElement?rootEl:document;
      const qs=(sel)=>root.querySelector(sel);
      const setVal=(name,val)=>{
        const el=qs(`[name="${name}"]`);
        if(el){
          el.value=(val??'');
          el.dispatchEvent(new Event('change'));
        }
      };

      setVal('name',data.name);
      setVal('alias',data.remote_id);
      setVal('time',data.time);
      setVal('type',data.service_type || 'imei');
      setVal('cost',data.credit ?? 0);
      setVal('profit',0);
      setVal('source','api');

      // ✅ اجبار تفعيل
      const active=qs('[name="active"]');
      if(active) active.checked=true;

      // ✅ Summernote Info
      if(data.info!=null){
        if(window.jQuery?.fn?.summernote && jQuery('#infoEditor').length){
          jQuery('#infoEditor').summernote('code',data.info);
          const hid=qs('#infoHidden'); if(hid) hid.value=data.info;
        }else{
          const infoEl=qs('[name="info"]'); if(infoEl) infoEl.value=data.info;
        }
      }
    };

    // ✅ السعر = cost + profit
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
        const perc=(pType?.value=='2'||(pType?.value||'').toLowerCase()==='percent');
        const price= perc?(c+(c*p/100)):(c+p);
        if(pricePreview) pricePreview.value=fmt(price);
        if(convPreview)  convPreview.value=fmt(price);
      };

      [cost,profit,pType].forEach(el=>el && el.addEventListener('input',recalc));
      recalc();

      return {
        recalc,
        setCost:(v)=>{
          if(cost){
            cost.value=(v??0);
            cost.dispatchEvent(new Event('input'));
          }
        }
      };
    }

    // ✅ Select2 + API + Groups
    async function initApiPickers(scope){
      await ensureSelect2();
      const $=window.jQuery;
      const $modal=$('#serviceModal');

      const $kind=$(scope).find('[name="type"]');
      const $prov=$(scope).find('.js-api-provider');
      const $service=$(scope).find('.js-api-service');
      const $groupSel=$(scope).find('[name="group_id"]');
      const $source=$(scope).find('[name="source"]');

      // API block
      let apiBlockEl=scope.querySelector('.js-api-block');

      function toggleApiBlock(){
        if(!apiBlockEl) apiBlockEl=scope.querySelector('.js-api-block');
        if(!apiBlockEl) return;
        const val=($source.val()||'').toString().toLowerCase();
        apiBlockEl.classList.toggle('d-none', val!=='api');
      }
      toggleApiBlock();
      $source.on('change',toggleApiBlock);

      // ✅ Providers
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

      // ✅ Groups
      function loadGroups(){
        const kind=($kind.val()||'').toLowerCase();
        fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(kind))
          .then(r=>r.json()).then(rows=>{
            const html=['<option value="">Group</option>'].concat(
              rows.map(g=>`<option value="${g.id}">${g.name}</option>`)
            ).join('');
            $groupSel.html(html);
          }).catch(()=>{});
      }
      loadGroups();
      $kind.on('change',loadGroups);

      // ✅ Services
      const priceHelper=initPriceAuto(scope);

      $service.select2({
        dropdownParent:$modal,
        width:'100%',
        placeholder:'API service',
        minimumInputLength:0,
        ajax:{
          url:"{{ route('admin.services.clone.provider_services') }}",
          delay:200,
          data:(params)=>({
            type:($kind.val()||'imei'),
            provider_id:$prov.val()||'',
            q:params?.term||''
          }),
          processResults:(rows)=>({
            results:rows.map(s=>({
              id:s.id,
              text:`${(s.text||s.name||'Service')}${(s.credit!=null?` — ${Number(s.credit).toFixed(4)}`:'')}`,
              price:Number(s.credit||0)
            }))
          })
        }
      });

      $service.on('select2:select',function(e){
        const data=e.params.data;
        if(typeof data?.price==='number'){
          priceHelper.setCost(data.price.toFixed(4));
        }
      });

      $prov.on('select2:select',()=>{ $service.val(null).trigger('change'); });
    }

    // ✅ فتح مودال الإنشاء (Clone) + ✅ اغلاق أي مودال مفتوح أولاً
    document.addEventListener('click', async (e)=>{
      const btn=e.target.closest('[data-create-service]');
      if(!btn) return;

      e.preventDefault();

      // ✅ اغلق أي مودال مفتوح قبل فتح مودال الخدمة (حل الواجهتين)
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

      try{
        await ensureSummernote();
        await ensureSelect2();

        if(typeof window.initModalCreateSummernote==='function'){
          window.initModalCreateSummernote(body);
        }

        await initApiPickers(body);

      }catch(err){
        console.error(err);
        alert('Failed to load editor');
      }

      const prefill={
        service_type:btn.dataset.serviceType||'imei',
        provider_id :btn.dataset.providerId||'',
        remote_id   :btn.dataset.remoteId||'',
        name        :btn.dataset.name||'',
        credit      :btn.dataset.credit||btn.dataset.price||'0',
        time        :btn.dataset.time||'',
        info        :btn.dataset.info||''
      };

      window.prefillFromRemote(body, prefill);

      const modalEl=document.getElementById('serviceModal');
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    // ✅ Ajax Submit Create
    document.addEventListener('submit', async (ev)=>{
      const form=ev.target.closest('#serviceModal form[data-ajax="1"]');
      if(!form) return;

      ev.preventDefault();

      form.querySelectorAll('.is-invalid').forEach(el=>el.classList.remove('is-invalid'));
      form.querySelectorAll('.invalid-feedback').forEach(el=>el.remove());

      if(window.jQuery?.fn?.summernote && jQuery('#infoEditor').length){
        const html=jQuery('#infoEditor').summernote('code');
        const hid=form.querySelector('#infoHidden');
        if(hid) hid.value=html;
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

        if(res.status===422){
          const {errors}=await res.json();
          for(const [field,msgs] of Object.entries(errors||{})){
            const el=form.querySelector(`[name="${field}"]`);
            if(!el) continue;
            el.classList.add('is-invalid');
            const fb=document.createElement('div');
            fb.className='invalid-feedback';
            fb.innerText=(msgs||[]).join(' ');
            el.insertAdjacentElement('afterend',fb);
          }
          return;
        }

        if(res.ok){
          bootstrap.Modal.getInstance(document.getElementById('serviceModal'))?.hide();
          location.reload();
        }else{
          alert('Failed to save service');
        }

      }catch(e){
        btn?.removeAttribute('disabled');
        alert('Network error, please try again.');
      }
    });

  })();
  </script>
  @endpush
@endonce
