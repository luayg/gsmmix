{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Admin')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- CSS/JS via Vite --}}
  @vite([
    'resources/css/bundle.css',
    'resources/css/admin-theme.css',   {{-- الثيم أولاً --}}
    'resources/css/admin.css',         {{-- رقعاتك النهائية ثانياً --}}
    'resources/js/admin.js',
  ])

  {{-- حمّل المودال المشترك مرة واحدة فقط --}}
  @include('admin.partials.service-modal')

  {{-- مكان لستايلات الصفحات + أي ستايل يدفعه المودال الموحّد --}}
  @stack('styles')

  {{-- Prevent flash before styles load --}}
  <style>html:not(.hydrated) body{visibility:hidden}</style>

  {{-- Toast stack position + z-index --}}
  <style>
    .toast-stack{
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 2000;
      display: grid;
      gap: .5rem;
      width: min(380px, 90vw);
    }
  </style>
</head>

<div class="modal fade" id="appModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" id="appModalContent">
      <div class="p-4">Loading...</div>
    </div>
  </div>
</div>

<body class="bg-light">
  {{-- Navbar + Sidebar --}}
  @include('partials.navbar')
  @include('partials.sidebar')

  <main class="content-wrapper">
    @yield('content')
  </main>

  {{-- Hydration flag --}}
  <script>requestAnimationFrame(()=>document.documentElement.classList.add('hydrated'))</script>

  {{-- ===== Global Ajax Modal ===== --}}
  <div class="modal fade" id="ajaxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content"></div>
    </div>
  </div>

  {{-- ===== Toasts stack (top-right) ===== --}}
  <div aria-live="polite" aria-atomic="true" class="toast-stack" id="toastStack"></div>

  {{-- ===== Helpers: showToast + openAjaxModal + click-delegate ===== --}}
  <script>
    (function () {
      // CSRF
      window.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      // Toast helper (مرة واحدة)
      if (!window.showToast) {
        const pickBg = (v) => ({
          success: 'bg-success text-white',
          danger : 'bg-danger text-white',
          warning: 'bg-warning',
          info   : 'bg-primary text-white'
        }[v] || 'bg-dark text-white');

        window.showToast = function (variant='success', message='Done', opts={}) {
          const id  = 't' + Date.now() + Math.random().toString(16).slice(2);
          const bg  = pickBg(variant);
          const ttl = opts.title ?? 'Notification';
          const dly = Number.isFinite(opts.delay) ? opts.delay : 3000;

          const html = `
            <div id="${id}" class="toast border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true"
                 data-bs-delay="${dly}">
              <div class="toast-header ${bg}">
                <strong class="me-auto">${ttl}</strong>
                <small class="text-white-50">now</small>
                <button type="button" class="btn-close ${bg.includes('text-white') ? 'btn-close-white' : ''}"
                        data-bs-dismiss="toast" aria-label="Close"></button>
              </div>
              <div class="toast-body">${message}</div>
            </div>`;

          const stack = document.getElementById('toastStack');
          stack.insertAdjacentHTML('beforeend', html);
          const el = document.getElementById(id);

          if (window.bootstrap?.Toast) {
            const t = new window.bootstrap.Toast(el);
            t.show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
          } else {
            el.style.opacity = '1'; el.style.display = 'block'; el.classList.add('show');
            setTimeout(() => { el.classList.remove('show'); el.remove(); }, dly);
          }
        };
      }

      // ===== ✅ Summernote initializer for Ajax modals =====
      const loadCssOnce = (id,href)=>{ if(document.getElementById(id)) return;
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

      window.initAjaxModalEditors = async function(scope){
        const root = scope instanceof Element ? scope : document;
        const els = root.querySelectorAll('textarea[data-summernote="1"]');
        if(!els.length) return;

        await ensureSummernote();

        if(!window.jQuery || !window.jQuery.fn?.summernote) return;

        window.jQuery(els).each(function(){
          const $t = window.jQuery(this);

          // إذا تم تهيئته من قبل
          if ($t.next('.note-editor').length) return;

          const h = Number($t.attr('data-summernote-height') || 420);

          $t.summernote({
            height: h,
            placeholder: 'Write reply…',
            toolbar: [
              ['style', ['style']],
              ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
              ['para', ['ul', 'ol', 'paragraph']],
              ['insert', ['link', 'picture', 'table']],
              ['view', ['codeview']]
            ],
            callbacks: {
              // ✅ إدراج صور مباشرة (Base64) بدون أي API إضافي
              onImageUpload: function(files) {
                for (const f of files) {
                  const reader = new FileReader();
                  reader.onload = (e) => {
                    $t.summernote('insertImage', e.target.result);
                  };
                  reader.readAsDataURL(f);
                }
              }
            }
          });
        });
      };

      // Ajax modal open helper
      window.openAjaxModal = function (url, {method='GET', body=null, headers={}} = {}) {
        const modalEl = document.getElementById('ajaxModal');
        const content = modalEl.querySelector('.modal-content');

        content.innerHTML = `
          <div class="p-5 text-center text-muted">
            <div class="spinner-border" role="status"></div>
            <div class="mt-3">Loading…</div>
          </div>`;

        const modal = window.bootstrap?.Modal.getOrCreateInstance(modalEl) ??
                      new (window.bootstrap.Modal)(modalEl);
        modal.show();

        const h = Object.assign({
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': window.CSRF_TOKEN
        }, headers || {});

        return fetch(url, { method, headers: h, body })
          .then(r => { if (!r.ok) throw new Error(r.status + ' ' + r.statusText); return r.text(); })
          .then(async html => {
            content.innerHTML = html;
            // ✅ فعّل المحررات بعد تحميل المودال
            try { await window.initAjaxModalEditors(content); } catch(e) { console.error(e); }
          })
          .catch(err => {
            modal.hide();
            console.error(err);
            window.showToast('danger', 'Failed to load modal content.');
          });
      };

      // Delegation for .js-open-modal
      document.addEventListener('click', function (e) {
        const a = e.target.closest('.js-open-modal');
        if (!a) return;
        e.preventDefault();
        const url = a.dataset.url || a.getAttribute('href') || '#';
        if (!url || url === '#') return;
        window.openAjaxModal(url);
      });

      // تنظيف أي بقايا للباك دروب
      document.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
      });
    })();
  </script>

  {{-- أطبع المودالات والسكربتات المدفوعة عبر @push (من المودال الموحّد وغيره) --}}
  @stack('modals')
  @stack('scripts')
</body>
</html>
