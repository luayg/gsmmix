{{-- resources/views/layouts/admin-standalone.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Admin')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- نفس Assets تمامًا مثل layouts/admin.blade.php --}}
  @vite([
    'resources/css/bundle.css',
    'resources/css/admin-theme.css',
    'resources/css/admin.css',
    'resources/js/admin.js',
  ])

  @include('global.summernote.script')
  
  {{-- مودال Create service (مرة واحدة) --}}
  @include('admin.partials.service-modal')
  {{-- styles stacks --}}
  @stack('styles')

  <style>
    body{ background:#f8f9fa; }
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

<body>
  <main class="p-3">
    @yield('content')
  </main>

  {{-- Global Ajax Modal (لو احتجته) --}}
  <div class="modal fade" id="ajaxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content"></div>
    </div>
  </div>

  <div aria-live="polite" aria-atomic="true" class="toast-stack" id="toastStack"></div>

  @stack('modals')
  @stack('scripts')
</body>
</html>