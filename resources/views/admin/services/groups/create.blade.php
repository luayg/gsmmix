{{-- admin/services/groups/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Create service group')

@section('content')
<div class="container py-4">
  <h1 class="h4 mb-3">Create service group</h1>

  <form action="{{ route('admin.services.groups.store') }}" method="POST">
    @csrf
    @include('admin.services.groups.partials.form', ['group' => null])

    <div class="mt-3">
      <button class="btn btn-primary">Save</button>
      <a href="{{ route('admin.services.groups.index') }}" class="btn btn-light">Cancel</a>
    </div>
  </form>
</div>
@endsection
