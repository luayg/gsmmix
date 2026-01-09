@extends('admin.layout')
@section('content')
<div class="card">
  <div class="card-header"><h5 class="mb-0">{{ $provider->name }} | View</h5></div>
  <div class="card-body">
    <dl class="row">
      <dt class="col-sm-3">Type</dt><dd class="col-sm-9 text-uppercase">{{ $provider->type }}</dd>
      <dt class="col-sm-3">URL</dt><dd class="col-sm-9">{{ $provider->url }}</dd>
      <dt class="col-sm-3">Username</dt><dd class="col-sm-9">{{ $provider->username }}</dd>
      <dt class="col-sm-3">Active</dt><dd class="col-sm-9">{!! $provider->active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</dd>
      <dt class="col-sm-3">Synced</dt><dd class="col-sm-9">{!! $provider->synced ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</dd>
      <dt class="col-sm-3">Auto sync</dt><dd class="col-sm-9">{!! $provider->auto_sync ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</dd>
      <dt class="col-sm-3">Balance</dt><dd class="col-sm-9">${{ number_format($provider->balance,2) }}</dd>
    </dl>
  </div>
</div>
@endsection
