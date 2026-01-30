@php
  $storeUrl = route('admin.orders.server.store');
  $deviceLabel = 'Device (Email/Serial/etc)';
  $devicePlaceholder = 'Enter required value';
  $showQuantity = true;
@endphp
@include('admin.orders._modals.create', compact('storeUrl','deviceLabel','devicePlaceholder','showQuantity','services','providers'))
