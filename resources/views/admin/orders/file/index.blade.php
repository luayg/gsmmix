@php
  $title = 'File Orders';
  $newModalUrl = route('admin.orders.file.modal.create');
  $viewUrl = fn($id)=> route('admin.orders.file.modal.view', $id);
  $editUrl = fn($id)=> route('admin.orders.file.modal.edit', $id);
  $sendUrl = fn($id)=> route('admin.orders.file.send', $id);
  $refreshUrl = fn($id)=> route('admin.orders.file.refresh', $id);
@endphp
@include('admin.orders._index', compact('title','newModalUrl','viewUrl','editUrl','sendUrl','refreshUrl','rows','services','providers'))
