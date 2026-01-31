@extends('layouts.admin')

@section('title', 'File Orders')

@section('content')
  @include('admin.orders._index', [
    'pageTitle' => 'File Orders',
  ])
@endsection
