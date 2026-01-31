@extends('layouts.admin')
@section('title','IMEI Orders')

@section('content')
  <div class="container-fluid py-3">
    <h4 class="mb-3">IMEI Orders</h4>

    @include('admin.orders._index', [
      'title'       => 'IMEI Orders',
      'rows'        => $rows,
      'services'    => $services,
      'providers'   => $providers,
      'routePrefix' => 'admin.orders.imei',
    ])
  </div>
@endsection
