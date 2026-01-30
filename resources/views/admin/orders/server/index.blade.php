@php
  $title = 'Server Orders';
  $newModalUrl = route('admin.orders.server.modal.create');
  $viewUrl = fn($id)=> route('admin.orders.server.modal.view', $id);
  $editUrl = fn($id)=> route('admin.orders.server.modal.edit', $id);
  $sendUrl = fn($id)=> route('admin.orders.server.send', $id);
  $refreshUrl = fn($id)=> route('admin.orders.server.refresh', $id);
@endphp
@include('admin.orders._index', compact('title','newModalUrl','viewUrl','editUrl','sendUrl','refreshUrl','rows','services','providers'))
