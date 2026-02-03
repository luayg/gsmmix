{{-- resources/views/admin/orders/_index.blade.php --}}

@php
/**
 * هذا الملف يستخدمه index لكل أنواع الطلبات.
 * لازم يكون عنده routePrefix دائماً.
 * Controller لازم يمرر $routePrefix (مثال: admin.orders.imei)
 */
$routePrefix = $routePrefix ?? 'admin.orders.imei';
$title = $title ?? 'Orders';
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">{{ $title }}</h4>

    <button class="btn btn-success js-open-modal"
            data-url="{{ route($routePrefix . '.modal.create') }}">
        New order
    </button>
</div>

<div class="card">
    <div class="card-body">
        <table id="ordersTable" class="table table-striped table-bordered w-100">
            <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Device</th>
                <th>Service</th>
                <th>Provider</th>
                <th>Status</th>
                <th style="width:150px;">Actions</th>
            </tr>
            </thead>
            <tbody>
            {{-- DataTable / Ajax عندك يعبّيها --}}
            </tbody>
        </table>
    </div>
</div>
