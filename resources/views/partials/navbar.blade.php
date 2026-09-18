<nav class="navbar admin-navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
  <div class="container-fluid">
    <button id="btnToggleSidebar" class="btn btn-outline-light d-lg-none me-2" type="button" aria-label="Toggle sidebar">
      <i class="fas fa-bars"></i>
    </button>

    <a class="navbar-brand admin-brand d-flex align-items-center" href="{{ route('admin.dashboard') }}">
      @include('shared.brand')
    </a>

    <div id="smartSearchWrapper" class="mx-auto d-none d-lg-block" style="position:relative;width:40%">
      <input id="smartSearchBox" type="text" class="form-control" placeholder="Smart search">
      <div id="smartSearchResults" class="dropdown-menu w-100"></div>
    </div>

    <ul class="navbar-nav ms-auto flex-row align-items-center gap-2">
      <li class="nav-item">
        <a class="btn btn-outline-light position-relative" href="{{ route('admin.logs.activity') }}" aria-label="Notifications" title="Activity">
          <i class="far fa-bell"></i>
        </a>
      </li>

      <li class="nav-item">
        <button type="button" class="btn btn-outline-light admin-theme-toggle" id="adminThemeToggle"
                aria-label="Switch to light mode" title="Switch to light mode">
          <i class="fas fa-sun" aria-hidden="true"></i>
          <span class="d-none d-xl-inline ms-1">Light</span>
        </button>
      </li>

      <li class="nav-item dropdown">
        <button class="btn btn-outline-light dropdown-toggle" type="button" id="userMenu"
                data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-user-shield me-1"></i>{{ auth()->user()->name ?? 'Administrator' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
          <li><a class="dropdown-item" href="{{ route('admin.account.edit') }}"><i class="fas fa-user me-2"></i>{{ $t('admin.menu.profile','My profile') }}</a></li>
          <li><a class="dropdown-item" href="{{ route('admin.account.edit') }}#password"><i class="fas fa-key me-2"></i>{{ $t('admin.menu.password','Change password') }}</a></li>
          @can('settings.view')
          <li><a class="dropdown-item" href="{{ route('admin.settings.general') }}"><i class="fas fa-sliders-h me-2"></i>{{ $t('admin.menu.general_settings','General settings') }}</a></li>
          @endcan
          <li><a class="dropdown-item" href="{{ route('admin.account.edit') }}"><i class="fas fa-shield-halved me-2"></i>{{ $t('admin.menu.security','Security') }}</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit"><i class="fas fa-right-from-bracket me-2"></i>{{ $t('nav.logout','Logout') }}</button></form></li>
        </ul>
      </li>

      <li class="nav-item dropdown">
        <button class="btn btn-outline-light dropdown-toggle" type="button" id="langMenu"
                data-bs-toggle="dropdown" aria-expanded="false">
          <span class="me-1">{{ $currentLanguage?->flag }}</span>{{ strtoupper($currentLanguage?->code ?? 'EN') }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end language-menu" aria-labelledby="langMenu">
          @foreach($activeLanguages as $language)
          <li><form method="POST" action="{{ route('locale.update', $language->code) }}">@csrf<button type="submit" class="dropdown-item d-flex align-items-center gap-2 {{ app()->getLocale()===$language->locale?'active':'' }}"><span>{{ $language->flag }}</span><span>{{ $language->native_name }}</span></button></form></li>
          @endforeach
          @can('settings.view')
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('admin.settings.languages') }}"><i class="fas fa-language me-2"></i>{{ $t('admin.menu.manage_languages','Manage languages') }}</a></li>
          @endcan
        </ul>
      </li>
    </ul>
  </div>
</nav>
