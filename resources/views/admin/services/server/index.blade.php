{{-- resources/views/admin/services/server/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Server Services')

@section('content')
<div class="container-fluid py-2">
  <div class="card shadow-sm">
    <div class="card-body">
      @include('admin.services._index', ['rows' => $rows, 'kind' => 'server'])
    </div>
  </div>

  {{-- ✅ REQUIRED by service-modal.js --}}
  <template id="serviceCreateTpl">
    @include('admin.services.server._modal_create')
  </template>
</div>
@endsection