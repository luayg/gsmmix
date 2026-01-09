@extends('layouts.admin')
@section('title','Assign roles')

@section('content')
<div class="card"><div class="card-body">
  <h5 class="mb-3">User: {{ $user->name }} ({{ $user->email }})</h5>

  <form method="POST" action="{{ route('admin.users.roles.sync',$user) }}">
    @csrf
    <div class="form-group">
      <label>Roles</label>
      <select name="roles[]" id="rolesSelect" class="form-control" multiple style="width:100%">
        @foreach($roles as $r)
          <option value="{{ $r->name }}" {{ in_array($r->id,$userRoleIds) ? 'selected':'' }}>
            {{ $r->name }}
          </option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-success">Save</button>
    <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Back</a>
  </form>
</div></div>

<script type="module">
import $ from 'jquery';
import 'select2/dist/js/select2.full.js';
import 'select2/dist/css/select2.min.css';
$('#rolesSelect').select2({placeholder:'Pick roles'});
</script>
@endsection
