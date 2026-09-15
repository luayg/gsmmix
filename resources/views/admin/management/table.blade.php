@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="container-fluid py-4">
  <h1 class="h4">{{ $title }}</h1>
  <p class="text-muted">{{ $description }}</p>
  @if(request()->routeIs('admin.finances.*'))
  <nav class="d-flex gap-3 mb-3" aria-label="Finance pages">
    <a href="{{ route('admin.finances.index') }}">Overview</a>
    <a href="{{ route('admin.finances.statements.index') }}">Statements</a>
    <a href="{{ route('admin.finances.transactions.index') }}">Transactions</a>
  </nav>
  @if(request()->routeIs('admin.finances.statements.index', 'admin.finances.transactions.index'))
  <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <input class="form-control w-auto" type="number" min="1" name="user_id" value="{{ request('user_id') }}" aria-label="Customer ID" placeholder="Customer ID">
    @if(request()->routeIs('admin.finances.transactions.index'))
      <select class="form-select w-auto" name="direction" aria-label="Direction">
        <option value="">All directions</option>
        <option value="income" @selected(request('direction') === 'income')>Income</option>
        <option value="expense" @selected(request('direction') === 'expense')>Expense</option>
      </select>
    @endif
    <button class="btn btn-outline-primary">Filter</button>
  </form>
  @endif
  @endif
  <div class="card"><div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
      <tbody>
        @forelse($rows as $row)
          <tr>@foreach($row as $cell)<td>{{ $cell ?? '—' }}</td>@endforeach</tr>
        @empty
          <tr><td colspan="{{ count($columns) }}" class="text-muted text-center py-4">No records match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>@isset($pagination)<div class="card-footer">{{ $pagination->links() }}</div>@endisset</div>
</div>
@endsection
