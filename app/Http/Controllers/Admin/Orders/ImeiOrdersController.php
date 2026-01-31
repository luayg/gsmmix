<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Http\Request;

class ImeiOrdersController extends Controller
{
    private string $routePrefix = 'admin.orders.imei';

    public function index(Request $r)
    {
        $q = trim((string)$r->get('q', ''));
        $status = trim((string)$r->get('status', ''));
        $supplierId = $r->get('supplier_id');

        $rows = ImeiOrder::query()
            ->with(['service', 'provider'])
            ->orderByDesc('id');

        if ($q !== '') {
            $rows->where(function ($w) use ($q) {
                $w->where('device', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('remote_id', 'like', "%{$q}%");
            });
        }

        if ($status !== '') {
            $rows->where('status', $status);
        }

        if ($supplierId !== null && $supplierId !== '') {
            $rows->where('supplier_id', (int)$supplierId);
        }

        $rows = $rows->paginate(20)->withQueryString();

        $services  = ImeiService::query()->orderByDesc('id')->limit(500)->get();
        $providers = ApiProvider::query()->orderBy('name')->get();

        return view('admin.orders.imei.index', compact('rows', 'services', 'providers'));
    }

    public function modalCreate()
    {
        $services  = ImeiService::query()->orderByDesc('id')->limit(500)->get();
        $users     = User::query()->orderByDesc('id')->limit(500)->get();
        $routePrefix = $this->routePrefix;

        return view('admin.orders._modals.create', compact('services', 'users', 'routePrefix'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:imei_services,id'],
            'device'     => ['required', 'string', 'max:255'],
            'comments'   => ['nullable', 'string'],
        ]);

        $user = User::findOrFail((int)$data['user_id']);
        $service = ImeiService::findOrFail((int)$data['service_id']);

        $order = ImeiOrder::create([
            'device'     => $data['device'],
            'status'     => 'waiting',
            'comments'   => $data['comments'] ?? null,
            'user_id'    => $user->id,
            'email'      => $user->email,
            'service_id' => $service->id,
            'supplier_id'=> $service->supplier_id, // المزود المرتبط بالخدمة
            'api_order'  => (bool)($service->supplier_id && $service->remote_id),
            'processing' => false,
            'ip'         => $r->ip(),
        ]);

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Order created.');
    }

    public function modalView(ImeiOrder $order)
    {
        $row = $order->load(['service', 'provider']);
        return view('admin.orders._modals.view', compact('row'));
    }

    public function modalEdit(ImeiOrder $order)
    {
        $row = $order->load(['service', 'provider']);
        $routePrefix = $this->routePrefix;
        return view('admin.orders._modals.edit', compact('row', 'routePrefix'));
    }

    public function update(Request $r, ImeiOrder $order)
    {
        $data = $r->validate([
            'status' => ['required', 'in:waiting,inprogress,success,rejected,cancelled'],
            'reply'  => ['nullable', 'string'],
        ]);

        $order->status   = $data['status'];
        $order->response = $data['reply'] ?? $order->response;
        if (!empty($data['reply'])) {
            $order->replied_at = now();
        }
        $order->save();

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Saved.');
    }
}
