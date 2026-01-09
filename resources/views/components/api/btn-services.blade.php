@props(['provider'])
<div class="btn-group">
  <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
    Services
  </button>
  <ul class="dropdown-menu">
    <li><a href="#" class="dropdown-item js-open-modal"
           data-url="{{ route('admin.apis.services.imei', $provider) }}">IMEI services</a></li>
    <li><a href="#" class="dropdown-item js-open-modal"
           data-url="{{ route('admin.apis.services.server', $provider) }}">Server services</a></li>
    <li><a href="#" class="dropdown-item js-open-modal"
           data-url="{{ route('admin.apis.services.file', $provider) }}">File services</a></li>
  </ul>
</div>
