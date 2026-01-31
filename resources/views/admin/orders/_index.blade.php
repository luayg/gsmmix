@php
    // ✅ Fix: if controller didn't pass $routePrefix, derive it from current route name.
    // Example current route name: admin.orders.imei.index  => routePrefix: admin.orders.imei
    $rn = request()->route()?->getName() ?? '';
    $routePrefix = $routePrefix ?? ($rn ? \Illuminate\Support\Str::beforeLast($rn, '.index') : 'admin.orders.imei');

    // Optional: title fallback (if not passed)
    $pageTitle = $pageTitle ?? match (true) {
        str_contains($routePrefix, 'admin.orders.imei')   => 'IMEI Orders',
        str_contains($routePrefix, 'admin.orders.server') => 'Server Orders',
        str_contains($routePrefix, 'admin.orders.file')   => 'File Orders',
        str_contains($routePrefix, 'admin.orders.product')=> 'Product Orders',
        default => 'Orders',
    };

    // ✅ status list exactly كما طلبت
    $statusList = ['waiting','inprogress','success','rejected','cancelled'];
@endphp

<div class="d-flex align-items-center gap-2 mb-3">
    <h4 class="m-0">{{ $pageTitle }}</h4>

    <button class="btn btn-success js-open-modal"
            data-url="{{ route($routePrefix.'.modal.create') }}"
            data-title="New order">
        New order
    </button>
</div>

<form class="mb-3 d-flex gap-2" method="get">
    <input type="text" class="form-control" name="q" value="{{ request('q') }}"
           placeholder="Search: IMEI / remote id / email">

    <select class="form-select" name="status" style="max-width:220px;">
        <option value="">All status</option>
        @foreach($statusList as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
        @endforeach
    </select>

    <select class="form-select" name="provider" style="max-width:240px;">
        <option value="">All providers</option>
        @foreach(($providers ?? []) as $p)
            <option value="{{ $p->id }}" @selected((string)request('provider')===(string)$p->id)>
                {{ $p->name }}
            </option>
        @endforeach
    </select>

    <button class="btn btn-primary" type="submit">Go</button>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead>
        <tr>
            <th style="width:70px;">ID</th>
            <th style="width:170px;">Date</th>
            <th style="width:220px;">Device</th>
            <th>Service</th>
            <th style="width:160px;">Provider</th>
            <th style="width:130px;">Status</th>
            <th style="width:160px;">Actions</th>
        </tr>
        </thead>

        <tbody>
        @forelse(($rows ?? []) as $row)
            <tr>
                <td>#{{ $row->id }}</td>
                <td>{{ optional($row->created_at)->format('Y-m-d H:i') }}</td>

                <td>
                    {{-- IMEI: device  | Server: device/email/sn etc | File: device --}}
                    {{ $row->device ?? '—' }}
                    @if(!empty($row->quantity))
                        <span class="text-muted">x{{ $row->quantity }}</span>
                    @endif
                </td>

                <td>{{ $row->service?->name_json['en'] ?? $row->service?->name ?? '—' }}</td>

                <td>{{ $row->provider?->name ?? '—' }}</td>

                <td>
                    @php
                        $st = strtolower((string)($row->status ?? 'waiting'));
                        $badge = match ($st) {
                            'waiting'    => 'bg-secondary',
                            'inprogress' => 'bg-info',
                            'success'    => 'bg-success',
                            'rejected'   => 'bg-danger',
                            'cancelled'  => 'bg-dark',
                            default      => 'bg-secondary',
                        };
                    @endphp
                    <span class="badge {{ $badge }}">{{ strtoupper($st) }}</span>

                    {{-- remote reference --}}
                    @if(!empty($row->remote_id))
                        <div class="small text-muted">Ref: {{ $row->remote_id }}</div>
                    @endif
                </td>

                <td class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary js-open-modal"
                            data-url="{{ route($routePrefix.'.modal.view', $row->id) }}"
                            data-title="Order #{{ $row->id }} | View">
                        View
                    </button>

                    <button class="btn btn-sm btn-warning js-open-modal"
                            data-url="{{ route($routePrefix.'.modal.edit', $row->id) }}"
                            data-title="Order #{{ $row->id }} | Edit">
                        Edit
                    </button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No orders</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if(isset($rows) && method_exists($rows, 'links'))
    <div class="mt-3">{{ $rows->links() }}</div>
@endif
