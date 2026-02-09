@extends('layouts.admin')

@section('title', 'Remote Server Services')

@section('content')
  <div class="container-fluid">
    @include('admin.api.remote.server.modal')
  </div>
@endsection
