{{-- admin/services/groups/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Service groups')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Service groups</h1>
    <a href="{{ route('admin.services.groups.create') }}" class="btn btn-primary btn-sm">
      <i class="fas fa-plus"></i> New group
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if(isset($rows) && count($rows))
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Type</th>
            <th>Active</th>
            <th>Ordering</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->name ?? '' }}</td>
            <td class="text-uppercase">{{ $r->type ?? '' }}</td>
            <td>
              @if(($r->active ?? false))
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-secondary">Inactive</span>
              @endif
            </td>
            <td>{{ $r->ordering ?? '' }}</td>
            <td class="text-end">
              <a href="{{ route('admin.services.groups.edit', $r->id) }}" class="btn btn-sm btn-warning">Edit</a>
              <form action="{{ route('admin.services.groups.destroy', $r->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Delete this group?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    @if(method_exists($rows, 'links'))
      <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
    @endif
  @else
    <div class="alert alert-info mb-0">No groups yet.</div>
  @endif
</div>
@endsection
