{{-- resources/views/admin/orders/smm/index.blade.php --}}
@extends('layouts.admin')

@vite(['resources/js/orders-imei-edit.js'])

@section('title', 'SMM Orders')

@section('content')
  @include('admin.orders._index')
@endsection
