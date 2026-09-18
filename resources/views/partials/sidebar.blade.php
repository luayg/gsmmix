{{-- resources/views/partials/sidebar.blade.php --}}
@php
  /**
   * helper: يطابق أي routeIs
   */
  $routeIsAny = static function (array $patterns): bool {
    foreach ($patterns as $p) if (request()->routeIs($p)) return true;
    return false;
  };

  use Illuminate\Support\Facades\Route as R;

  // روابط مرنة: إن وُجد اسم روت نستخدمه، وإلا نستخدم المسار العامل new service-management
  $groupsHref = R::has('admin.services.groups.index')
      ? route('admin.services.groups.index')
      : url('/admin/service-management/services-groups');

  $imeiHref = R::has('admin.services.imei.index')
      ? route('admin.services.imei.index')
      : url('/admin/service-management/imei-services');

  $serverHref = R::has('admin.services.server.index')
      ? route('admin.services.server.index')
      : url('/admin/service-management/server-services');

  $fileHref = R::has('admin.services.file.index')
      ? route('admin.services.file.index')
      : url('/admin/service-management/file-services');

  $smmHref = R::has('admin.services.smm.index')
      ? route('admin.services.smm.index')
      : url('/admin/service-management/smm-services');

  // افتح مجموعة الخدمات إذا كنا على أي صفحة ضمن service-management أو الخدمات بأسمائها القديمة
  $servicesOpen =
        request()->is('admin/service-management/*')
     || $routeIsAny([
          'admin.services.groups.*',
          'admin.services.imei.*',
          'admin.services.server.*',
          'admin.services.file.*',
          'admin.services.smm.*'
        ]);
@endphp

<nav id="adminSidebar" class="admin-sidebar sidebar bg-dark">
  <ul class="nav flex-column mb-4" id="sidebarAccordion">

    {{-- Dashboard --}}
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
         href="{{ route('admin.dashboard') }}">
        <i class="fas fa-tachometer-alt"></i> <span>{{ $t('admin.nav.dashboard','Dashboard') }}</span>
      </a>
    </li>

    {{-- User Management --}}
    @php
      $open = $routeIsAny(['admin.users.*','admin.groups.*','admin.roles.*','admin.permissions.*']);
    @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mUser"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mUser">
        <span><i class="fas fa-users-cog"></i> {{ $t('admin.nav.user_management','User Management') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mUser" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="fas fa-user"></i> {{ $t('admin.nav.users','Users') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}" href="{{ route('admin.groups.index') }}"><i class="fas fa-users"></i> {{ $t('admin.nav.groups','Groups') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}"><i class="fas fa-user-shield"></i> {{ $t('admin.nav.roles','Roles') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" href="{{ route('admin.permissions.index') }}"><i class="fas fa-key"></i> {{ $t('admin.nav.permissions','Permissions') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Order Management --}}
    @php
      $open = $routeIsAny([
        'admin.orders.imei.*',
        'admin.orders.server.*',
        'admin.orders.file.*',
        'admin.orders.smm.*',
        'admin.orders.product.*'
      ]);
    @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mOrders"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mOrders">
        <span><i class="fas fa-shopping-cart"></i> {{ $t('admin.nav.order_management','Order Management') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mOrders" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.orders.imei.*') ? 'active' : '' }}" href="{{ route('admin.orders.imei.index') }}"><i class="fas fa-mobile-alt"></i> {{ $t('admin.nav.imei_orders','IMEI Orders') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.server.*') ? 'active' : '' }}" href="{{ route('admin.orders.server.index') }}"><i class="fas fa-server"></i> {{ $t('admin.nav.server_orders','Server Orders') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.file.*') ? 'active' : '' }}" href="{{ route('admin.orders.file.index') }}"><i class="fas fa-file"></i> {{ $t('admin.nav.file_orders','File Orders') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.smm.*') ? 'active' : '' }}" href="{{ route('admin.orders.smm.index') }}"><i class="fas fa-share-alt"></i> {{ $t('admin.nav.smm_orders','SMM Orders') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.product.*') ? 'active' : '' }}" href="{{ route('admin.orders.product.index') }}"><i class="fas fa-box-open"></i> {{ $t('admin.nav.product_orders','Product orders') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Service Management (مهم: روابط service-management الجديدة) --}}
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mServices"
         aria-expanded="{{ $servicesOpen ? 'true' : 'false' }}" aria-controls="mServices">
        <span><i class="fas fa-cogs"></i> {{ $t('admin.nav.service_management','Service Management') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>

      <div id="mServices" class="collapse {{ $servicesOpen ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">

          <li>
            <a class="nav-link {{ request()->is('admin/service-management/services-groups*') || request()->routeIs('admin.services.groups.*') ? 'active' : '' }}"
               href="{{ $groupsHref }}">
              <i class="fas fa-layer-group"></i> {{ $t('admin.nav.service_groups','Services groups') }}
            </a>
          </li>

          <li>
            <a class="nav-link {{ request()->is('admin/service-management/imei-services*') || request()->routeIs('admin.services.imei.*') ? 'active' : '' }}"
               href="{{ $imeiHref }}">
              <i class="fas fa-mobile-alt"></i> {{ $t('admin.nav.imei_services','IMEI Service') }}
            </a>
          </li>

          <li>
            <a class="nav-link {{ request()->is('admin/service-management/server-services*') || request()->routeIs('admin.services.server.*') ? 'active' : '' }}"
               href="{{ $serverHref }}">
              <i class="fas fa-server"></i> {{ $t('admin.nav.server_services','Server Service') }}
            </a>
          </li>

          <li>
            <a class="nav-link {{ request()->is('admin/service-management/file-services*') || request()->routeIs('admin.services.file.*') ? 'active' : '' }}"
               href="{{ $fileHref }}">
              <i class="fas fa-file-alt"></i> {{ $t('admin.nav.file_services','File Service') }}
            </a>
          </li>

          <li>
            <a class="nav-link {{ request()->is('admin/service-management/smm-services*') || request()->routeIs('admin.services.smm.*') ? 'active' : '' }}"
               href="{{ $smmHref }}">
              <i class="fas fa-share-alt"></i> {{ $t('admin.nav.smm_services','SMM Service') }}
            </a>
          </li>
        </ul>
      </div>
    </li>

    {{-- Retail store --}}
    @php $open = $routeIsAny(['admin.store.categories.*','admin.store.products.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mStore"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mStore">
        <span><i class="fas fa-store"></i> {{ $t('admin.nav.retail_store','Retail store') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mStore" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.store.categories.*') ? 'active' : '' }}" href="{{ route('admin.store.categories.index') }}"><i class="fas fa-tags"></i> {{ $t('admin.nav.product_categories','Product categories') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.store.products.*') ? 'active' : '' }}" href="{{ route('admin.store.products.index') }}"><i class="fas fa-boxes"></i> {{ $t('admin.nav.products','Products') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Local sources --}}
    @php $open = $routeIsAny(['admin.sources.*','admin.replies.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mLocal"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mLocal">
        <span><i class="fas fa-plug"></i> {{ $t('admin.nav.local_sources','Local sources') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mLocal" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.sources.*') ? 'active' : '' }}" href="{{ route('admin.sources.index') }}"><i class="fas fa-link"></i> {{ $t('admin.nav.sources','Sources') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.replies.*') ? 'active' : '' }}" href="{{ route('admin.replies.index') }}"><i class="fas fa-reply"></i> {{ $t('admin.nav.replies','Replies') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Downloads --}}
    @php $open = $routeIsAny(['admin.downloads.categories.*','admin.downloads.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mDownloads"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mDownloads">
        <span><i class="fas fa-download"></i> {{ $t('admin.nav.downloads','Downloads') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mDownloads" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.downloads.categories.*') ? 'active' : '' }}" href="{{ route('admin.downloads.categories.index') }}"><i class="fas fa-folder-open"></i> {{ $t('admin.nav.download_categories','Download categories') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.downloads.*') ? 'active' : '' }}" href="{{ route('admin.downloads.index') }}"><i class="fas fa-cloud-download-alt"></i> {{ $t('admin.nav.downloads','Downloads') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Finances --}}
    @php
      $open = $routeIsAny(['admin.finances.invoices.*','admin.finances.payment-reviews.*','admin.finances.statements.*','admin.finances.transactions.*']);
      $manualPaymentReviewCount = \Illuminate\Support\Facades\Schema::hasTable('payment_transactions')
          && \Illuminate\Support\Facades\Schema::hasTable('payment_gateways')
          && \Illuminate\Support\Facades\Schema::hasColumn('payment_gateways', 'is_system') ? \App\Models\PaymentTransaction::query()
          ->where('status', 'review')
          ->whereHas('gateway', fn ($query) => $query->where('is_system', false))
          ->count() : 0;
    @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mFin"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mFin">
        <span><i class="fas fa-wallet"></i> {{ $t('admin.nav.finances','Finances') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mFin" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.finances.invoices.*') ? 'active' : '' }}" href="{{ route('admin.finances.invoices.index') }}"><i class="fas fa-file-invoice-dollar"></i> {{ $t('admin.nav.invoices','Invoices') }}</a></li>
          <li><a class="nav-link d-flex align-items-center {{ request()->routeIs('admin.finances.payment-reviews.*') ? 'active' : '' }}" href="{{ route('admin.finances.payment-reviews.index') }}"><i class="fas fa-receipt"></i> <span>{{ $t('admin.nav.payment_reviews','Payment reviews') }}</span>@if($manualPaymentReviewCount)<span class="badge bg-warning text-dark ms-auto">{{ $manualPaymentReviewCount }}</span>@endif</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.finances.statements.*') ? 'active' : '' }}" href="{{ route('admin.finances.statements.index') }}"><i class="fas fa-receipt"></i> {{ $t('admin.nav.statements','Statements') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.finances.transactions.*') ? 'active' : '' }}" href="{{ route('admin.finances.transactions.index') }}"><i class="fas fa-exchange-alt"></i> {{ $t('admin.nav.transactions','Transactions') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- API Management (direct) --}}
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('admin.apis.*') ? 'active' : '' }}" href="{{ route('admin.apis.index') }}">
        <i class="fas fa-code"></i> <span>{{ $t('admin.nav.api_management','API Management') }}</span>
      </a>
    </li>

    {{-- Settings --}}
    @php $open = $routeIsAny(['admin.settings.general','admin.settings.banners*','admin.settings.resellers*','admin.settings.mail','admin.settings.payment*','admin.settings.languages*','admin.settings.currencies*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mSettings"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mSettings">
        <span><i class="fas fa-cog"></i> {{ $t('admin.nav.settings','Settings') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mSettings" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.settings.general') ? 'active' : '' }}" href="{{ route('admin.settings.general') }}"><i class="fas fa-sliders-h"></i> {{ $t('admin.nav.general_settings','General settings') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.banners*') ? 'active' : '' }}" href="{{ route('admin.settings.banners') }}"><i class="fas fa-images"></i> {{ $t('admin.nav.home_banners','Home banners') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.resellers*') ? 'active' : '' }}" href="{{ route('admin.settings.resellers') }}"><i class="fab fa-whatsapp"></i> {{ $t('admin.nav.resellers','Top-up resellers') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.mail') ? 'active' : '' }}" href="{{ route('admin.settings.mail') }}"><i class="fas fa-envelope"></i> {{ $t('admin.nav.mail_settings','Mail settings') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.payment*') ? 'active' : '' }}" href="{{ route('admin.settings.payment') }}"><i class="fas fa-credit-card"></i> {{ $t('admin.nav.payment_settings','Payment settings') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.languages*') ? 'active' : '' }}" href="{{ route('admin.settings.languages') }}"><i class="fas fa-language"></i> {{ $t('admin.nav.languages','Languages') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.currencies*') ? 'active' : '' }}" href="{{ route('admin.settings.currencies') }}"><i class="fas fa-coins"></i> {{ $t('admin.nav.currencies','Currencies') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Page management (direct) --}}
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">
        <i class="fas fa-file-alt"></i> <span>{{ $t('admin.nav.page_management','Page management') }}</span>
      </a>
    </li>

    {{-- System --}}
    @php $open = $routeIsAny(['admin.system.filemanager','admin.system.update','admin.system.maintenance','admin.system.backups']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mSystem"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mSystem">
        <span><i class="fas fa-cogs"></i> {{ $t('admin.nav.system','System') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mSystem" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.system.filemanager') ? 'active' : '' }}" href="{{ route('admin.system.filemanager') }}"><i class="fas fa-folder"></i> {{ $t('admin.nav.file_manager','File manager') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.system.update') ? 'active' : '' }}" href="{{ route('admin.system.update') }}"><i class="fas fa-sync-alt"></i> {{ $t('admin.nav.update','Update') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.system.maintenance') ? 'active' : '' }}" href="{{ route('admin.system.maintenance') }}"><i class="fas fa-tools"></i> {{ $t('admin.nav.maintenance','Maintenance mode') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.system.backups') ? 'active' : '' }}" href="{{ route('admin.system.backups') }}"><i class="fas fa-database"></i> {{ $t('admin.nav.backups','Backups') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Reports --}}
    @php $open = $routeIsAny(['admin.reports.users','admin.reports.services','admin.reports.products']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mReports"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mReports">
        <span><i class="fas fa-chart-line"></i> {{ $t('admin.nav.reports','Reports') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mReports" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.reports.users') ? 'active' : '' }}" href="{{ route('admin.reports.users') }}"><i class="fas fa-user-check"></i> {{ $t('admin.nav.user_reports','User reports') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.reports.services') ? 'active' : '' }}" href="{{ route('admin.reports.services') }}"><i class="fas fa-clipboard-list"></i> {{ $t('admin.nav.service_reports','Service reports') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.reports.products') ? 'active' : '' }}" href="{{ route('admin.reports.products') }}"><i class="fas fa-box"></i> {{ $t('admin.nav.product_reports','Product reports') }}</a></li>
        </ul>
      </div>
    </li>

    {{-- Logs --}}
    @php $open = $routeIsAny(['admin.logs.access','admin.logs.activity','admin.logs.error']); @endphp
    <li class="nav-item mb-3">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mLogs"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mLogs">
        <span><i class="fas fa-scroll"></i> {{ $t('admin.nav.logs','Logs') }}</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mLogs" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.logs.access') ? 'active' : '' }}" href="{{ route('admin.logs.access') }}"><i class="fas fa-key"></i> {{ $t('admin.nav.access_logs','Access logs') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.logs.activity') ? 'active' : '' }}" href="{{ route('admin.logs.activity') }}"><i class="fas fa-clipboard-check"></i> {{ $t('admin.nav.activity_logs','Activity logs') }}</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.logs.error') ? 'active' : '' }}" href="{{ route('admin.logs.error') }}"><i class="fas fa-bug"></i> {{ $t('admin.nav.error_logs','Error logs') }}</a></li>
        </ul>
      </div>
    </li>

  </ul>
</nav>
