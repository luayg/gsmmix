{{-- resources/views/admin/services/smm/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'SMM Services')

@section('content')
<div class="container-fluid py-2">
  <div class="card shadow-sm">
    <div class="card-body">
      @include('admin.services._index', ['rows' => $rows, 'kind' => 'smm'])
    </div>
  </div>

  {{-- REQUIRED by service-modal.js --}}
  <template id="serviceCreateTpl">
    @include('admin.services.smm._modal_create')
  </template>
</div>
@endsection