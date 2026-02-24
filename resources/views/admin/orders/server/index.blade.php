{{-- resources/views/admin/orders/server/index.blade.php --}}
@extends('layouts.admin')

@vite(['resources/js/orders-imei-edit.js'])

@section('content')
  @include('admin.orders._index')
@endsection
