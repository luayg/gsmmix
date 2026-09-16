<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Models\ApiProvider;
use App\Services\Orders\ProductOrderService;
use App\Support\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use App\Rules\SafeOrderFile;

class ProductOrdersController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|in:waiting,inprogress,success,rejected,cancelled',
            'provider' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:10,25,50,75,100,500,1000',
        ]);
        $perPage = (int) ($data['per_page'] ?? 10);
        $query = ProductOrder::query()->with(['product', 'user'])->orderByDesc('id')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['q'] ?? null, fn ($query, $q) => $query->where(function ($query) use ($q): void {
                $query->where('email', 'like', '%' . $q . '%')->orWhere('device', 'like', '%' . $q . '%')
                    ->when(ctype_digit((string) $q), fn ($query) => $query->orWhere('id', (int) $q))
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', '%' . $q . '%'));
            }));

        if (!empty($data['provider'])) {
            $providerId = (int) $data['provider'];
            $query->where(function ($where) use ($providerId): void {
                foreach (ProductService::TYPES as $type) {
                    $orderModel = ProductService::orderModel($type);
                    $ids = $orderModel::query()->where('supplier_id', $providerId)->select('id');
                    $where->orWhere(fn ($part) => $part->where('service_order_type', $type)
                        ->whereIn('service_order_id', $ids));
                }
            });
        }

        $rows = $query->paginate($perPage)->withQueryString();
        $providerNames = Schema::hasTable('api_providers') ? ApiProvider::query()->pluck('name', 'id') : collect();
        foreach ($rows->getCollection()->groupBy('service_order_type') as $type => $orders) {
            if (!in_array($type, ProductService::TYPES, true)) continue;
            $orderModel = ProductService::orderModel($type);
            $linked = $orderModel::query()->whereIn('id', $orders->pluck('service_order_id')->filter())
                ->get(['id', 'supplier_id', 'remote_id'])->keyBy('id');
            foreach ($orders as $order) {
                $serviceOrder = $linked->get($order->service_order_id);
                $order->setAttribute('provider_name', $serviceOrder?->supplier_id
                    ? ($providerNames[$serviceOrder->supplier_id] ?? '—') : 'Manual');
                $order->setAttribute('remote_reference', $serviceOrder?->remote_id);
            }
        }
        foreach ($rows as $order) {
            if (!$order->getAttribute('provider_name')) $order->setAttribute('provider_name', 'Manual');
        }
        $providers = Schema::hasTable('api_providers')
            ? ApiProvider::query()->orderBy('name')->get(['id', 'name']) : collect();
        return view('admin.orders.product.index', compact('rows', 'providers', 'perPage'));
    }

    private function formData(): array
    {
        return [
            'users' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']),
            'products' => Product::query()->where('active', true)->orderBy('name')->get(),
            'requestUid' => (string) Str::uuid(),
        ];
    }

    public function create()
    {
        return view('admin.orders.product.create', $this->formData());
    }

    public function modalCreate()
    {
        return view('admin.orders.product._form', $this->formData());
    }

    public function store(Request $request, ProductOrderService $orders)
    {
        $data = $request->validate([
            'request_uid' => 'required|uuid', 'user_id' => 'required|integer|exists:users,id',
            'product_id' => 'required|integer|exists:products,id',
            'device' => 'nullable|string|max:2000',
            'quantity' => 'nullable|integer|min:1|max:1000000000',
            'required' => 'nullable|array',
            'file' => ['nullable','file','max:51200',new SafeOrderFile],
            'comments' => 'nullable|string|max:5000',
        ]);
        $data['ip'] = $request->ip();
        try {
            $order = $orders->create($data, (int) $request->user()->id);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['request_uid' => 'This submission was already used. Reopen the order form for a new order.']);
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $order->id, 'status' => $order->status]);
        }
        return redirect()->route('admin.orders.product.show', $order)->with('ok', 'Product order saved.');
    }

    public function show(ProductOrder $order)
    {
        $order->load(['product', 'user', 'localSource']);
        return view('admin.orders.product.show', compact('order'));
    }

    public function modalView(ProductOrder $order)
    {
        $order->load(['product', 'user', 'localSource']);

        return view('admin.orders.product.modals.view', compact('order'));
    }

    public function modalEdit(ProductOrder $order)
    {
        $order->load(['product', 'user', 'localSource']);

        return view('admin.orders.product.modals.edit', compact('order'));
    }

    public function update(Request $request, ProductOrder $order, ProductOrderService $orders)
    {
        $data = $request->validate([
            'status' => 'required|in:waiting,inprogress,success,rejected,cancelled',
            'comments' => 'sometimes|nullable|string|max:5000',
            'response' => 'sometimes|nullable|string|max:20000',
        ]);
        $orders->update($order->id, $data);
        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('ok', 'Product order updated.');
    }
}
