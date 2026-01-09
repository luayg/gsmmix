{{-- admin/services/groups/edit.blade.php --}}
@extends('layouts.admin')

@section('title', 'Edit service group')

@section('content')
<div class="container py-4">
  <h1 class="h4 mb-3">Edit service group</h1>

  <form action="{{ route('admin.services.groups.update', $group->id) }}" method="POST">
    @csrf @method('PUT')
    @include('admin.services.groups.partials.form', ['group' => $group])

    <div class="mt-3">
      <button class="btn btn-primary">Update</button>
      <a href="{{ route('admin.services.groups.index') }}" class="btn btn-light">Back</a>
    </div>
  </form>
</div>
@endsection
