@extends('layouts.admin')
@section('title','Edit permissions')

@section('content')
<div class="card"><div class="card-body">
  <h5 class="mb-3">Role: {{ $role->name }}</h5>
  <form method="POST" action="{{ route('admin.roles.syncPerms',$role) }}">
    @csrf
    <div class="form-group">
      <label>Permissions</label>
      <select name="permissions[]" id="permSelect" class="form-control" multiple style="width:100%">
        @foreach($perms as $p)
          <option value="{{ $p->name }}" {{ in_array($p->id,$own) ? 'selected':'' }}>
            {{ $p->name }}
          </option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-success">Save</button>
    <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">Back</a>
  </form>
</div></div>

<script type="module">
import $ from 'jquery';
import 'select2/dist/js/select2.full.js';
import 'select2/dist/css/select2.min.css';
$('#permSelect').select2({placeholder:'Pick permissions'});
</script>
@endsection
