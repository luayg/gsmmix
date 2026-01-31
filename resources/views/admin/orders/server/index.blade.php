@extends('layouts.admin')

@section('title', 'Server Orders')

@section('content')
  @include('admin.orders._index', [
    'pageTitle' => 'Server Orders',
  ])
@endsection
