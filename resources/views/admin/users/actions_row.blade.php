@php
  $viewUrl = route('admin.users.modal.view',$u);
  $editUrl = route('admin.users.modal.edit',$u);
  $delUrl  = route('admin.users.modal.delete',$u);
  $finUrl  = route('admin.users.modal.finances',$u);
  $srvUrl  = route('admin.users.modal.services',$u);
@endphp

<div class="btn-group btn-group-sm">
  <button class="btn btn-primary js-open-modal" data-url="{{ $viewUrl }}"><i class="fas fa-eye"></i> View</button>
  <button class="btn btn-info js-open-modal"    data-url="{{ $finUrl }}"><i class="fas fa-wallet"></i> Finances</button>

  <div class="btn-group">
    <button class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown">Manage services</button>
    <ul class="dropdown-menu">
      <li><button class="dropdown-item js-open-modal" data-url="{{ $srvUrl }}#imei">IMEI services</button></li>
      <li><button class="dropdown-item js-open-modal" data-url="{{ $srvUrl }}#server">Server services</button></li>
      <li><button class="dropdown-item js-open-modal" data-url="{{ $srvUrl }}#file">File services</button></li>
    </ul>
  </div>

  <button class="btn btn-warning js-open-modal" data-url="{{ $editUrl }}"><i class="fas fa-edit"></i> Edit</button>
  <button class="btn btn-danger  js-open-modal" data-url="{{ $delUrl }}"><i class="fas fa-trash"></i> Delete</button>
</div>
