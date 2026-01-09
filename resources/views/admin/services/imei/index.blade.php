@extends('layouts.admin')

@section('title', 'IMEI services')

@section('content')
  {{-- جدول الخدمات (البارشيال فيه الزر) --}}
  @include('admin.services._index', get_defined_vars())

  {{-- قالب المودال فقط --}}
  <template id="serviceCreateTpl">
    @include('admin.services.imei._modal_create')
  </template>
@endsection
