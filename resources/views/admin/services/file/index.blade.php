@extends('layouts.admin')

@section('title', 'File services')

@section('content')
  @include('admin.services._index', get_defined_vars())

  <template id="serviceCreateTpl">
    @include('admin.services.file._modal_create')
  </template>
@endsection
