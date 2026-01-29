{{-- resources/views/admin/partials/service-modal.blade.php --}}

@once
  @push('scripts')
  <script>
  (function(){

    // ==========================================================
    // Helpers (وجودها عندك مسبقاً غالباً - اتركها كما هي إن كانت موجودة)
    // ==========================================================
    const clean = (s)=> String(s ?? '').replace(/<[^>]*>/g,'').trim();
    const escAttr = (s)=> String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const slugify = (s)=> clean(s).toLowerCase()
      .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').substring(0,190);

    function initTabs(scope){
      // لو عندك tabs في template، خليه كما هو عندك
      // (أبقيته بسيط لتجنب كسر أي شيء)
    }

    async function ensureSummernote(){
      // موجود عندك أصلاً - اتركه كما هو لو عندك loader
      return;
    }
    async function ensureSelect2(){
      // موجود عندك أصلاً - اتركه كما هو لو عندك loader
      return;
    }

    // ==========================================================
    // API UI helpers (موجودة عندك أصلاً في نفس الملف)
    // ==========================================================
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

      const res = await fetch("{{ route('admin.apis.options') }}", { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if(!res.ok) return;

      const rows = await res.json().catch(()=>[]);
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

      const res = await fetch(url.toString(), { headers:{'X-Requested-With':'XMLHttpRequest'} });
      if(!res.ok){
        sel.innerHTML = `<option value="">Failed to load</option>`;
        return;
      }

      const rows = await res.json().catch(()=>[]);
      if(!Array.isArray(rows) || rows.length === 0){
        sel.innerHTML = `<option value="">No services found</option>`;
        return;
      }

      sel.innerHTML = `<option value="">Select service...</option>` + rows.map(s=>{
        const rid  = clean(s.remote_id ?? s.id ?? s.service_id);
        const name = clean(s.name);
        const time = clean(s.time ?? s.delivery_time);

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

    // ==========================================================
    // بعد إنشاء الخدمة: اجعل زر الصف يصبح Added/Disabled
    // (هذا عندك أصلاً – تركته كما هو تقريباً)
    // ==========================================================
    function markCloneAsAdded(remoteId){
      const rid = String(remoteId || '').trim();
      if(!rid) return;

      const esc = (v) => {
        try { return CSS && CSS.escape ? CSS.escape(v) : v.replace(/["\\]/g, '\\$&'); }
        catch(e){ return v.replace(/["\\]/g, '\\$&'); }
      };

      const selectors = [
        `#svcTable tr[data-remote-id="${esc(rid)}"]`,
        `#servicesTable tr[data-remote-id="${esc(rid)}"]`,
        `tr[data-remote-id="${esc(rid)}"]`,
      ];

      let row = null;
      for (const sel of selectors){
        row = document.querySelector(sel);
        if(row) break;
      }
      if(!row) return;

      const btn =
        row.querySelector('.clone-btn') ||
        row.querySelector('[data-create-service]') ||
        row.querySelector('button');

      if(btn){
        btn.disabled = true;
        btn.classList.remove('btn-success','btn-secondary','btn-danger','btn-warning','btn-info','btn-dark','btn-primary');
        btn.classList.add('btn-outline-primary');
        btn.innerText = 'Added ✅';
        btn.removeAttribute('data-create-service');
      }
    }

    // ==========================================================
    // ✅ أهم إضافة: cache عالمي حتى لو الـ Wizard انفتح بعد الحدث
    // ==========================================================
    function rememberAdded(providerId, kind, remoteId){
      const p = String(providerId || '').trim();
      const k = String(kind || '').trim();
      const r = String(remoteId || '').trim();
      if(!p || !k || !r) return;

      window.__gsmmixAdded = window.__gsmmixAdded || {};
      window.__gsmmixAdded[`${p}:${k}:${r}`] = true;
    }

    // ==========================================================
    // فتح مودال الـ Clone/Create (نفس فكرتك الحالية)
    // ==========================================================
    document.addEventListener('click', async (ev)=>{
      const btn = ev.target.closest('[data-create-service]');
      if(!btn) return;

      const body = document.getElementById('serviceModalBody');
      const tpl  = document.getElementById('serviceCreateTpl');
      if(!tpl) return alert('Template not found');

      body.innerHTML = tpl.innerHTML;

      // تشغيل scripts داخل template (موجود عندك)
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
      await ensureSummernote();
      await ensureSelect2();

      if (window.jQuery) {
        try { jQuery(body).find('#infoEditor').summernote({ placeholder:'Description, notes, terms…', height:320 }); } catch(e){}
      }

      const providerId = btn.dataset.providerId;
      const remoteId   = btn.dataset.remoteId;

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

      // Groups options
      fetch("{{ route('admin.services.groups.options') }}?type="+encodeURIComponent(cloneData.serviceType))
        .then(r=>r.json())
        .then(rows=>{
          const sel = body.querySelector('[name="group_id"]');
          if(sel){
            sel.innerHTML = `<option value="">Group</option>` +
              (Array.isArray(rows) ? rows.map(g=>`<option value="${g.id}">${clean(g.name)}</option>`).join('') : '');
          }
        });

      ensureApiUI(body);
      body.querySelector('[name="source"]')?.addEventListener('change', ()=> ensureApiUI(body));
      try{ await loadApiProviders(body); }catch(e){}

      const apiProviderSel = body.querySelector('#apiProviderSelect');
      const apiServiceSel  = body.querySelector('#apiServiceSelect');

      if (isClone && apiProviderSel) {
        const pid = String(cloneData.providerId || '').trim();

        if (pid && !apiProviderSel.querySelector(`option[value="${pid.replace(/"/g,'\\"')}"]`)) {
          const opt = document.createElement('option');
          opt.value = pid;
          opt.textContent = cloneData.providerName || ('Provider #' + pid);
          apiProviderSel.appendChild(opt);
        }

        apiProviderSel.value = pid;
        apiProviderSel.dispatchEvent(new Event('change'));
        apiProviderSel.disabled = true;

        try { await loadProviderServices(body, pid, cloneData.serviceType); } catch(e) {}

        if (apiServiceSel) {
          const opt2 = Array.from(apiServiceSel.options).find(o => String(o.value) === String(cloneData.remoteId));
          if (opt2) {
            apiServiceSel.value = opt2.value;
            apiServiceSel.dispatchEvent(new Event('change'));
          }
        }
      }

      apiProviderSel?.addEventListener('change', async ()=>{
        if (apiProviderSel.disabled) return;

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
        }
      });

      bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
    });

    // ==========================================================
    // Submit (أضفنا rememberAdded هنا)
    // ==========================================================
    document.addEventListener('submit', async (ev)=>{
      const form = ev.target;
      if(!form || !form.matches('#serviceModal form')) return;

      ev.preventDefault();

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
          const rid = form.querySelector('[name="remote_id"]')?.value;
          markCloneAsAdded(rid);

          const provider_id = form.querySelector('[name="supplier_id"]')?.value || '';
          const kind = form.querySelector('[name="type"]')?.value || '';

          // ✅ NEW: خزّنها في الذاكرة (حتى لو الـ wizard انفتح بعدين)
          rememberAdded(provider_id, kind, rid);

          // ✅ موجود عندك: تبليغ مباشر لو listener موجود
          if (rid && provider_id && kind) {
            window.dispatchEvent(new CustomEvent('gsmmix:service-created', {
              detail: { provider_id, kind, remote_id: rid }
            }));
          }

          bootstrap.Modal.getInstance(document.getElementById('serviceModal'))?.hide();

          if (window.showToast) window.showToast('success', '✅ Service created successfully', { title: 'Done' });
          else alert('✅ Service created successfully');

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
