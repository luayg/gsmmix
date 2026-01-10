{{-- resources/views/admin/partials/service-modal.blade.php --}}
@once
@push('styles')
<style>
  #serviceModal .modal-dialog{width:96vw;max-width:1400px;margin:1rem auto}
  #serviceModal .modal-content{max-height:96dvh;border-radius:.75rem;overflow:hidden}
  #serviceModal .modal-header{
    background:#3cb878;color:#fff;border:0;
    display:flex;align-items:center;justify-content:space-between;
    padding:.8rem 1rem;
  }
  #serviceModal .modal-header .left-info{
    line-height:1.2
  }
  #serviceModal .modal-title{font-weight:700;font-size:1.05rem;margin:0}
  #serviceModal .modal-subtitle{opacity:.9;font-size:.85rem}
  #serviceModal .modal-body{padding:0 !important;background:#fff;overflow:auto}
  #serviceModal .nav-tabs{
    border:0;
    gap:.35rem;
  }
  #serviceModal .nav-tabs .nav-link{
    background:rgba(255,255,255,.12);
    color:#fff;border:0;border-radius:.35rem;
    padding:.28rem .7rem;font-size:.85rem;
  }
  #serviceModal .nav-tabs .nav-link.active{
    background:#fff;color:#333;font-weight:700;
  }
  #serviceModal .badge-chip{
    background:#111;color:#fff;border-radius:.35rem;
    padding:.3rem .55rem;font-size:.8rem;
    margin-left:.35rem;
  }
  #serviceModal .badge-chip.green{background:#1b8f5a;}
  #serviceModal .tab-pane{padding:1rem;}
  #serviceModal .note-editor.note-frame .note-editable{min-height:320px}
  #serviceModal .select2-container{width:100%!important;z-index:2055}
  #serviceModal .select2-dropdown{z-index:2056}
</style>
@endpush

@push('modals')
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">

        <div class="left-info">
          <h5 class="modal-title">Create service</h5>
          <div class="modal-subtitle">
            Provider: <span id="cloneProviderName">—</span> • Remote ID: <span id="cloneRemoteIdLabel">—</span>
          </div>
        </div>

        <div class="d-flex align-items-center gap-2">

          {{-- Tabs only here ✅ --}}
          <ul class="nav nav-tabs" id="svcTabs" role="tablist">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabGeneral" type="button">General</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAdditional" type="button">Additional</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMeta" type="button">Meta</button>
            </li>
          </ul>

          {{-- badges --}}
          <span class="badge-chip" id="cloneTypeBadge">Type: —</span>
          <span class="badge-chip green" id="clonePriceBadge">Price: 0.0000 Credits</span>

          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
      </div>

      <div class="modal-body">
        <div class="tab-content">

          <div class="tab-pane fade show active" id="tabGeneral">
            <div id="serviceModalGeneral"></div>
          </div>

          <div class="tab-pane fade" id="tabAdditional">
            <div id="serviceModalAdditional"></div>
          </div>

          <div class="tab-pane fade" id="tabMeta">
            <div id="serviceModalMeta"></div>
          </div>

        </div>
      </div>

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
    if(document.getElementById(id)) return res();
    const s=document.createElement('script');
    s.id=id; s.src=src; s.async=false;
    s.onload=res; s.onerror=rej;
    document.body.appendChild(s);
  });

  async function ensureSummernote(){
    loadCssOnce('sn-css','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css');
    if(!window.jQuery) await loadScriptOnce('jq','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
    if(!window.jQuery.fn.summernote){
      await loadScriptOnce('sn-js','https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js');
    }
  }

  async function ensureSelect2(){
    if(!window.jQuery) await loadScriptOnce('jq','https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
    if(!$.fn.select2){
      loadCssOnce('s2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
      await loadScriptOnce('s2-js','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
    }
  }

  // slug helper
  function slugify(text){
    return String(text||'')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g,'-')
      .replace(/(^-|-$)/g,'');
  }

  // ✅ init select2 pickers
  async function initApiPickers(scope){
    await ensureSelect2();
    const $=window.jQuery;
    const $modal=$('#serviceModal');

    const $prov=$(scope).find('.js-api-provider');
    const $svc=$(scope).find('.js-api-service');
    const $source=$(scope).find('[name="source"]');
    const $type=$(scope).find('[name="type"]');

    // toggle api block
    const apiBlock=scope.querySelector('.js-api-block');
    const toggle=()=>{
      if(!apiBlock) return;
      apiBlock.classList.toggle('d-none', ($source.val()||'')!=='api');
    };
    toggle();
    $source.on('change',toggle);

    // providers
    $prov.select2({
      dropdownParent:$modal,
      width:'100%',
      placeholder:'API connection',
      ajax:{
        url:"{{ route('admin.apis.options') }}",
        delay:150,
        data:(params)=>({q:params.term||''}),
        processResults:(rows)=>({results:rows.map(r=>({id:r.id,text:r.name}))})
      }
    });

    // services
    $svc.select2({
      dropdownParent:$modal,
      width:'100%',
      placeholder:'API service',
      minimumInputLength:0,
      ajax:{
        url:"{{ route('admin.services.clone.provider_services') }}",
        delay:200,
        data:(params)=>({
          type:($type.val()||'imei'),
          provider_id:$prov.val()||'',
          q:params.term||''
        }),
        processResults:(rows)=>({
          results:rows.map(s=>({
            id:s.id,
            text:`${(s.text||s.name||'Service')} — ${Number(s.credit||0).toFixed(4)} Credits`,
            credit:Number(s.credit||0)
          }))
        })
      }
    });

    return {$prov,$svc};
  }

  // open clone modal
  document.addEventListener('click', async (e)=>{
    const btn=e.target.closest('[data-create-service]');
    if(!btn) return;
    e.preventDefault();

    // close any open modal
    document.querySelectorAll('.modal.show').forEach(m=>{
      bootstrap.Modal.getInstance(m)?.hide();
    });

    const tpl=document.getElementById('serviceCreateTpl');
    if(!tpl){ alert('Template not found'); return; }

    // insert template into General only ✅
    document.getElementById('serviceModalGeneral').innerHTML = tpl.innerHTML;

    // Additional + Meta placeholders
    document.getElementById('serviceModalAdditional').innerHTML = `
      <div class="text-center text-muted py-4">
        Loading Additional (Fields + Groups)…
      </div>
    `;

    document.getElementById('serviceModalMeta').innerHTML = `
      <div class="text-center text-muted py-4">
        Loading Meta fields…
      </div>
    `;

    const body=document.getElementById('serviceModalGeneral');

    // ensure editors
    await ensureSummernote();
    await ensureSelect2();

    // init summernote
    if(window.initModalCreateSummernote) window.initModalCreateSummernote(body);

    const pickers=await initApiPickers(body);

    // values
    const providerId = btn.dataset.providerId || '';
    const providerName = btn.dataset.providerName || '—';
    const remoteId = btn.dataset.remoteId || '';
    const name = btn.dataset.name || '';
    const credit = Number(btn.dataset.credit||0);
    const type = btn.dataset.serviceType || 'imei';

    // top header info
    document.getElementById('cloneProviderName').innerText = providerName;
    document.getElementById('cloneRemoteIdLabel').innerText = remoteId;
    document.getElementById('cloneTypeBadge').innerText = 'Type: ' + type.toUpperCase();
    document.getElementById('clonePriceBadge').innerText = 'Price: ' + credit.toFixed(4) + ' Credits';

    // fill form
    body.querySelector('[name="name"]').value = name;
    body.querySelector('[name="alias"]').value = slugify(name);
    body.querySelector('[name="type"]').value = type;
    body.querySelector('[name="cost"]').value = credit.toFixed(4);
    body.querySelector('[name="source"]').value = 'api';

    // hidden remote fields
    const supplier = body.querySelector('#cloneSupplierId');
    const remote = body.querySelector('#cloneRemoteId');
    if(supplier) supplier.value = providerId;
    if(remote) remote.value = remoteId;

    // select provider in select2
    if(providerId){
      const opt = new Option(providerName, providerId, true, true);
      pickers.$prov.append(opt).trigger('change');
    }

    // select service in select2
    if(remoteId){
      const txt = `${name} — ${credit.toFixed(4)} Credits`;
      const optS = new Option(txt, remoteId, true, true);
      pickers.$svc.append(optS).trigger('change');
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
  });

})();
</script>
@endpush
@endonce
