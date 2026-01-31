@extends('layouts.admin')
@section('title','Server Orders')

@section('content')
  <div class="container-fluid py-3">
    <h4 class="mb-3">Server Orders</h4>

    @include('admin.orders._index', [
      'title'       => 'Server Orders',
      'rows'        => $rows,
      'services'    => $services,
      'providers'   => $providers,
      'routePrefix' => 'admin.orders.server',
    ])
  </div>
@endsection
