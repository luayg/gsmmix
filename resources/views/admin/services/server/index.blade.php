@extends('layouts.admin')

@section('title', 'Server services')

@section('content')
  @include('admin.services._index', get_defined_vars())

  <template id="serviceCreateTpl">
    @include('admin.services.server._modal_create')
  </template>
@endsection
