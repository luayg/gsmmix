{{-- [انسخ] --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top" style="height: var(--navbar-height)">
  <div class="container-fluid">
    <button id="btnToggleSidebar" class="btn btn-outline-light d-lg-none me-2">
  <i class="fas fa-bars"></i>
</button>


    

    <a class="navbar-brand d-flex align-items-center" href="{{ route('admin.dashboard') }}">
      
      <span>GSM MIX</span>
    </a>

    <div id="smartSearchWrapper" class="mx-auto d-none d-lg-block" style="position:relative; width:40%;">
      <input id="smartSearchBox" type="text" class="form-control" placeholder="Smart search">
      <div id="smartSearchResults" class="dropdown-menu w-100"></div>
    </div>

    <ul class="navbar-nav ml-auto align-items-center">
      <li class="nav-item mr-2">
        <a class="btn btn-outline-light position-relative" href="javascript:void(0)" aria-label="Notifications">
          <i class="far fa-bell"></i>
          <span class="badge badge-danger position-absolute" style="top:-6px; right:-6px;">4</span>
        </a>
      </li>

      <li class="nav-item dropdown mr-2">
        <a class="btn btn-outline-light dropdown-toggle" href="#" id="userMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          Administrator
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userMenu">
          <a class="dropdown-item" href="#">Profile</a>
          <a class="dropdown-item" href="#">Account Settings</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="#">Logout</a>
        </div>
      </li>

      <li class="nav-item dropdown">
        <a class="btn btn-outline-light dropdown-toggle" href="#" id="langMenu" data-toggle="dropdown">EN</a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="langMenu">
          <a class="dropdown-item" href="#">EN</a>
          <a class="dropdown-item" href="#">AR</a>
        </div>
      </li>
    </ul>
  </div>
</nav>
