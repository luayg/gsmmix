@extends('layouts.admin')
@section('title','File Orders')

@section('content')
  <div class="container-fluid py-3">
    <h4 class="mb-3">File Orders</h4>

    @include('admin.orders._index', [
      'title'       => 'File Orders',
      'rows'        => $rows,
      'services'    => $services,
      'providers'   => $providers,
      'routePrefix' => 'admin.orders.file',
    ])
  </div>
@endsection
