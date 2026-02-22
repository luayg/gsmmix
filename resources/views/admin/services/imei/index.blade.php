{{-- resources/views/admin/services/imei/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'IMEI Services')

@section('content')
<div class="container-fluid py-2">
  <div class="card shadow-sm">
    <div class="card-body">
      @include('admin.services._index', ['rows' => $rows, 'kind' => 'imei'])
    </div>
  </div>

  {{-- ✅ REQUIRED by service-modal.js --}}
  <template id="serviceCreateTpl">
    @include('admin.services.imei._modal_create')
  </template>
</div>
@endsection