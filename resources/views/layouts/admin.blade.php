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
    'resources/css/admin-theme.css',
    'resources/css/admin.css',
    'resources/js/admin.js',
    
  ])

  {{-- حمّل المودال المشترك مرة واحدة فقط --}}
  @include('admin.partials.service-modal')

  {{-- مكان لستايلات الصفحات + أي ستايل يدفعه المودال الموحّد --}}
  @stack('styles')

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

  {{-- ===== Global Ajax Modal ===== --}}
  <div class="modal fade" id="ajaxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content"></div>
    </div>
  </div>

  {{-- ===== Toasts stack (top-right) ===== --}}
  <div aria-live="polite" aria-atomic="true" class="toast-stack" id="toastStack"></div>

  {{-- أطبع المودالات والسكربتات المدفوعة عبر @push --}}
  @stack('modals')
  @stack('scripts')
</body>
</html>