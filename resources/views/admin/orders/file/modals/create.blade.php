@php
  $storeUrl = route('admin.orders.file.store');
  $deviceLabel = 'Device (optional)';
  $devicePlaceholder = 'Optional';
  $showQuantity = false;
@endphp
@include('admin.orders._modals.create', compact('storeUrl','deviceLabel','devicePlaceholder','showQuantity','services','providers'))
