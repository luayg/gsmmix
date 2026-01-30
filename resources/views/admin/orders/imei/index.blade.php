@php
  $title = 'IMEI Orders';
  $newModalUrl = route('admin.orders.imei.modal.create');
  $viewUrl = fn($id)=> route('admin.orders.imei.modal.view', $id);
  $editUrl = fn($id)=> route('admin.orders.imei.modal.edit', $id);
  $sendUrl = fn($id)=> route('admin.orders.imei.send', $id);
  $refreshUrl = fn($id)=> route('admin.orders.imei.refresh', $id);
@endphp
@include('admin.orders._index', compact('title','newModalUrl','viewUrl','editUrl','sendUrl','refreshUrl','rows','services','providers'))
