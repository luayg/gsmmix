<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\FileOrder;
use App\Models\FileService;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Http\Request;

class FileOrdersController extends Controller
{
    private string $routePrefix = 'admin.orders.file';

    public function index(Request $r)
    {
        $q = trim((string)$r->get('q', ''));
        $status = trim((string)$r->get('status', ''));
        $supplierId = $r->get('supplier_id');

        $rows = FileOrder::query()
            ->with(['service', 'provider'])
            ->orderByDesc('id');

        if ($q !== '') {
            $rows->where(function ($w) use ($q) {
                $w->where('device', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('remote_id', 'like', "%{$q}%");
            });
        }

        if ($status !== '') $rows->where('status', $status);
        if ($supplierId !== null && $supplierId !== '') $rows->where('supplier_id', (int)$supplierId);

        $rows = $rows->paginate(20)->withQueryString();

        $services  = FileService::query()->orderByDesc('id')->limit(500)->get();
        $providers = ApiProvider::query()->orderBy('name')->get();

        return view('admin.orders.file.index', compact('rows', 'services', 'providers'));
    }

    public function modalCreate()
    {
        $services  = FileService::query()->orderByDesc('id')->limit(500)->get();
        $users     = User::query()->orderByDesc('id')->limit(500)->get();
        $routePrefix = $this->routePrefix;

        return view('admin.orders._modals.create', compact('services', 'users', 'routePrefix'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'service_id' => ['required', 'integer', 'exists:file_services,id'],
            'device'     => ['required', 'string', 'max:255'],
            'comments'   => ['nullable', 'string'],
        ]);

        $user = User::findOrFail((int)$data['user_id']);
        $service = FileService::findOrFail((int)$data['service_id']);

        FileOrder::create([
            'device'     => $data['device'],
            'status'     => 'waiting',
            'comments'   => $data['comments'] ?? null,
            'user_id'    => $user->id,
            'email'      => $user->email,
            'service_id' => $service->id,
            'supplier_id'=> $service->supplier_id,
            'api_order'  => (bool)($service->supplier_id && $service->remote_id),
            'processing' => false,
            'ip'         => $r->ip(),
        ]);

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Order created.');
    }

    public function modalView(FileOrder $order)
    {
        $row = $order->load(['service', 'provider']);
        return view('admin.orders._modals.view', compact('row'));
    }

    public function modalEdit(FileOrder $order)
    {
        $row = $order->load(['service', 'provider']);
        $routePrefix = $this->routePrefix;
        return view('admin.orders._modals.edit', compact('row', 'routePrefix'));
    }

    public function update(Request $r, FileOrder $order)
    {
        $data = $r->validate([
            'status' => ['required', 'in:waiting,inprogress,success,rejected,cancelled'],
            'reply'  => ['nullable', 'string'],
        ]);

        $order->status   = $data['status'];
        $order->response = $data['reply'] ?? $order->response;
        if (!empty($data['reply'])) $order->replied_at = now();
        $order->save();

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Saved.');
    }
}
