@extends('layouts.admin')
@section('title','Product Orders')

@section('content')
<h4 class="mb-3">Product Orders</h4>

<div class="card">
  <div class="card-body">
    <div class="alert alert-info mb-3">
      Product orders is a placeholder (will be implemented later).
    </div>

    <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>ID</th><th>Status</th><th>Price</th><th>Date</th></tr></thead>
        <tbody>
        @foreach($rows as $r)
          <tr>
            <td>#{{ $r->id }}</td>
            <td>{{ $r->status }}</td>
            <td>{{ $r->price }}</td>
            <td>{{ $r->created_at }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

    {{ $rows->links() }}
  </div>
</div>
@endsection
