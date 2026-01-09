{{-- resources/views/partials/sidebar.blade.php --}}
@php
  /**
   * helper: يطابق أي routeIs
   */
  function route_is_any(array $patterns): bool {
    foreach ($patterns as $p) if (request()->routeIs($p)) return true;
    return false;
  }

  /**
   * helper: يُرجع 'active' إذا طابقنا routeIs أو path (request()->is)
   */
  function nav_active(array $patterns): string {
    foreach ($patterns as $p) {
      if (request()->routeIs($p) || request()->is($p)) return 'active';
    }
    return '';
  }

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

  // افتح مجموعة الخدمات إذا كنا على أي صفحة ضمن service-management أو الخدمات بأسمائها القديمة
  $servicesOpen =
        request()->is('admin/service-management/*')
     || route_is_any(['admin.services.groups.*','admin.services.imei.*','admin.services.server.*','admin.services.file.*']);
@endphp

<nav id="adminSidebar" class="admin-sidebar sidebar bg-dark">
  <ul class="nav flex-column mb-4" id="sidebarAccordion">

    {{-- Dashboard --}}
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
         href="{{ route('admin.dashboard') }}">
        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
      </a>
    </li>

    {{-- User Management --}}
    @php
      $open = route_is_any(['admin.users.*','admin.groups.*','admin.roles.*','admin.permissions.*']);
    @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mUser"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mUser">
        <span><i class="fas fa-users-cog"></i> User Management</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mUser" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="fas fa-user"></i> Users</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}" href="{{ route('admin.groups.index') }}"><i class="fas fa-users"></i> Groups</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}"><i class="fas fa-user-shield"></i> Roles</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" href="{{ route('admin.permissions.index') }}"><i class="fas fa-key"></i> Permissions</a></li>
        </ul>
      </div>
    </li>

    {{-- Order Management --}}
    @php $open = route_is_any(['admin.orders.imei.*','admin.orders.server.*','admin.orders.file.*','admin.orders.product.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mOrders"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mOrders">
        <span><i class="fas fa-shopping-cart"></i> Order Management</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mOrders" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.orders.imei.*') ? 'active' : '' }}" href="{{ route('admin.orders.imei.index') }}"><i class="fas fa-mobile-alt"></i> IMEI Orders</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.server.*') ? 'active' : '' }}" href="{{ route('admin.orders.server.index') }}"><i class="fas fa-server"></i> Server Orders</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.file.*') ? 'active' : '' }}" href="{{ route('admin.orders.file.index') }}"><i class="fas fa-file"></i> File Orders</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.orders.product.*') ? 'active' : '' }}" href="{{ route('admin.orders.product.index') }}"><i class="fas fa-box-open"></i> Product orders</a></li>
        </ul>
      </div>
    </li>

    {{-- Service Management (مهم: روابط service-management الجديدة) --}}
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mServices"
         aria-expanded="{{ $servicesOpen ? 'true' : 'false' }}" aria-controls="mServices">
        <span><i class="fas fa-cogs"></i> Service Management</span>
        <i class="fas fa-chevron-down small"></i>
      </a>

      <div id="mServices" class="collapse {{ $servicesOpen ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">

          <li>
            <a class="nav-link {{ nav_active(['admin/service-management/services-groups*','admin.services.groups.*']) }}"
               href="{{ $groupsHref }}">
              <i class="fas fa-layer-group"></i> Services groups
            </a>
          </li>

          <li>
            <a class="nav-link {{ nav_active(['admin/service-management/imei-services*','admin.services.imei.*']) }}"
               href="{{ $imeiHref }}">
              <i class="fas fa-mobile-alt"></i> IMEI Service
            </a>
          </li>

          <li>
            <a class="nav-link {{ nav_active(['admin/service-management/server-services*','admin.services.server.*']) }}"
               href="{{ $serverHref }}">
              <i class="fas fa-server"></i> Server Service
            </a>
          </li>

          <li>
            <a class="nav-link {{ nav_active(['admin/service-management/file-services*','admin.services.file.*']) }}"
               href="{{ $fileHref }}">
              <i class="fas fa-file-alt"></i> File Service
            </a>
          </li>
        </ul>
      </div>
    </li>

    {{-- Retail store --}}
    @php $open = route_is_any(['admin.store.categories.*','admin.store.products.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mStore"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mStore">
        <span><i class="fas fa-store"></i> Retail store</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mStore" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.store.categories.*') ? 'active' : '' }}" href="{{ route('admin.store.categories.index') }}"><i class="fas fa-tags"></i> Product categories</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.store.products.*') ? 'active' : '' }}" href="{{ route('admin.store.products.index') }}"><i class="fas fa-boxes"></i> Products</a></li>
        </ul>
      </div>
    </li>

    {{-- Local sources --}}
    @php $open = route_is_any(['admin.sources.*','admin.replies.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mLocal"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mLocal">
        <span><i class="fas fa-plug"></i> Local sources</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mLocal" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.sources.*') ? 'active' : '' }}" href="{{ route('admin.sources.index') }}"><i class="fas fa-link"></i> Sources</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.replies.*') ? 'active' : '' }}" href="{{ route('admin.replies.index') }}"><i class="fas fa-reply"></i> Replies</a></li>
        </ul>
      </div>
    </li>

    {{-- Downloads --}}
    @php $open = route_is_any(['admin.downloads.categories.*','admin.downloads.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mDownloads"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mDownloads">
        <span><i class="fas fa-download"></i> Downloads</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mDownloads" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.downloads.categories.*') ? 'active' : '' }}" href="{{ route('admin.downloads.categories.index') }}"><i class="fas fa-folder-open"></i> Download categories</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.downloads.*') ? 'active' : '' }}" href="{{ route('admin.downloads.index') }}"><i class="fas fa-cloud-download-alt"></i> Downloads</a></li>
        </ul>
      </div>
    </li>

    {{-- Finances --}}
    @php $open = route_is_any(['admin.finances.invoices.*','admin.finances.statements.*','admin.finances.transactions.*']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mFin"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mFin">
        <span><i class="fas fa-wallet"></i> Finances</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mFin" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.finances.invoices.*') ? 'active' : '' }}" href="{{ route('admin.finances.invoices.index') }}"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.finances.statements.*') ? 'active' : '' }}" href="{{ route('admin.finances.statements.index') }}"><i class="fas fa-receipt"></i> Statements</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.finances.transactions.*') ? 'active' : '' }}" href="{{ route('admin.finances.transactions.index') }}"><i class="fas fa-exchange-alt"></i> Transactions</a></li>
        </ul>
      </div>
    </li>

    {{-- API Management (direct) --}}
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('admin.apis.*') ? 'active' : '' }}" href="{{ route('admin.apis.index') }}">
        <i class="fas fa-code"></i> <span>API Management</span>
      </a>
    </li>

    {{-- Settings --}}
    @php $open = route_is_any(['admin.settings.general','admin.settings.mail','admin.settings.payment','admin.settings.languages','admin.settings.currencies']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mSettings"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mSettings">
        <span><i class="fas fa-cog"></i> Settings</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mSettings" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.settings.general') ? 'active' : '' }}" href="{{ route('admin.settings.general') }}"><i class="fas fa-sliders-h"></i> General settings</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.mail') ? 'active' : '' }}" href="{{ route('admin.settings.mail') }}"><i class="fas fa-envelope"></i> Mail settings</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.payment') ? 'active' : '' }}" href="{{ route('admin.settings.payment') }}"><i class="fas fa-credit-card"></i> Payment settings</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.languages') ? 'active' : '' }}" href="{{ route('admin.settings.languages') }}"><i class="fas fa-language"></i> Languages</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.settings.currencies') ? 'active' : '' }}" href="{{ route('admin.settings.currencies') }}"><i class="fas fa-coins"></i> Currencies</a></li>
        </ul>
      </div>
    </li>

    {{-- Page management (direct) --}}
    <li class="nav-item">
      <a class="nav-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}" href="{{ route('admin.pages.index') }}">
        <i class="fas fa-file-alt"></i> <span>Page management</span>
      </a>
    </li>

    {{-- System --}}
    @php $open = route_is_any(['admin.system.filemanager','admin.system.update','admin.system.maintenance','admin.system.backups']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mSystem"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mSystem">
        <span><i class="fas fa-cogs"></i> System</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mSystem" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.system.filemanager') ? 'active' : '' }}" href="{{ route('admin.system.filemanager') }}"><i class="fas fa-folder"></i> File manager</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.system.update') ? 'active' : '' }}" href="{{ route('admin.system.update') }}"><i class="fas fa-sync-alt"></i> Update</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.system.maintenance') ? 'active' : '' }}" href="{{ route('admin.system.maintenance') }}"><i class="fas fa-tools"></i> Maintenance mode</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.system.backups') ? 'active' : '' }}" href="{{ route('admin.system.backups') }}"><i class="fas fa-database"></i> Backups</a></li>
        </ul>
      </div>
    </li>

    {{-- Reports --}}
    @php $open = route_is_any(['admin.reports.users','admin.reports.services','admin.reports.products']); @endphp
    <li class="nav-item">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mReports"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mReports">
        <span><i class="fas fa-chart-line"></i> Reports</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mReports" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.reports.users') ? 'active' : '' }}" href="{{ route('admin.reports.users') }}"><i class="fas fa-user-check"></i> User reports</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.reports.services') ? 'active' : '' }}" href="{{ route('admin.reports.services') }}"><i class="fas fa-clipboard-list"></i> Service reports</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.reports.products') ? 'active' : '' }}" href="{{ route('admin.reports.products') }}"><i class="fas fa-box"></i> Product reports</a></li>
        </ul>
      </div>
    </li>

    {{-- Logs --}}
    @php $open = route_is_any(['admin.logs.access','admin.logs.activity','admin.logs.error']); @endphp
    <li class="nav-item mb-3">
      <a class="nav-link d-flex align-items-center justify-content-between"
         href="javascript:void(0)"
         data-bs-toggle="collapse" data-bs-target="#mLogs"
         aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="mLogs">
        <span><i class="fas fa-scroll"></i> Logs</span>
        <i class="fas fa-chevron-down small"></i>
      </a>
      <div id="mLogs" class="collapse {{ $open ? 'show' : '' }}" data-bs-parent="#sidebarAccordion">
        <ul class="nav flex-column">
          <li><a class="nav-link {{ request()->routeIs('admin.logs.access') ? 'active' : '' }}" href="{{ route('admin.logs.access') }}"><i class="fas fa-key"></i> Access logs</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.logs.activity') ? 'active' : '' }}" href="{{ route('admin.logs.activity') }}"><i class="fas fa-clipboard-check"></i> Activity logs</a></li>
          <li><a class="nav-link {{ request()->routeIs('admin.logs.error') ? 'active' : '' }}" href="{{ route('admin.logs.error') }}"><i class="fas fa-bug"></i> Error logs</a></li>
        </ul>
      </div>
    </li>

  </ul>
</nav>
