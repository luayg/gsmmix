{{-- resources/views/admin/services/file/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'File Services')

@section('content')
<div class="container-fluid py-2">
  <div class="card shadow-sm">
    <div class="card-body">
      @include('admin.services._index', ['rows' => $rows, 'kind' => 'file'])
    </div>
  </div>

  {{-- ✅ REQUIRED by service-modal.js --}}
  <template id="serviceCreateTpl">
    @include('admin.services.file._modal_create')
  </template>
</div>
@endsection