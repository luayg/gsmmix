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
          .then(html => { content.innerHTML = html; })
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
