@extends('layouts.admin')
@section('title','Users')

@section('content')
<div class="card" id="usersPage"
     data-url-data="{{ route('admin.users.data') }}"
     data-url-roles="{{ route('admin.users.roles') }}"
     data-url-groups="{{ route('admin.users.groups') }}"
     {{-- نمرّر رابط إنشاء مودال للمفتاح الاختياري في الـ JS --}}
     data-url-create="{{ route('admin.users.modal.create') }}"
>
  <div class="card-body">

    {{-- الشريط العلوي يُحقن بالـ JS (users-index.js) داخل .card-body قبل الجدول --}}
    {{-- سيظهر فيه: PageLength + ColVis + Reset + Export + Special + Create + Search --}}

    <div class="table-responsive">
      <table id="usersTable" class="table table-striped table-bordered w-100">
        <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Username</th>
          <th>Roles</th>
          <th>Group</th>
          <th>Balance</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
        </thead>
      </table>
    </div>
  </div>
</div>
@endsection
