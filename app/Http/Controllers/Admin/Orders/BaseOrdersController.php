<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $orderModel;

    /** @var class-string<Model> */
    protected string $serviceModel;

    protected string $viewPrefix;   // imei | server | file | product
    protected string $routePrefix;  // admin.orders.imei | admin.orders.server | ...

    public function index(Request $r)
    {
        /** @var \Illuminate\Database\Eloquent\Builder $q */
        $q = app($this->orderModel)->newQuery()
            ->with(['service', 'provider'])
            ->orderByDesc('id');

        // Search: IMEI / remote_id / email
        if ($r->filled('q')) {
            $term = trim((string)$r->q);
            $q->where(function ($w) use ($term) {
                $w->where('device', 'like', "%{$term}%")
                  ->orWhere('remote_id', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        // Status filter
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }

        // Provider filter
        if ($r->filled('provider_id')) {
            $q->where('supplier_id', (int)$r->provider_id);
        }

        $rows = $q->paginate(20)->withQueryString();

        // dropdowns
        $services  = app($this->serviceModel)->newQuery()->orderByDesc('id')->limit(500)->get();
        $providers = ApiProvider::query()->orderByDesc('id')->get();

        // ✅ أهم شيء: تمرير routePrefix / viewPrefix للـ view
        return view("admin.orders.{$this->viewPrefix}.index", [
            'rows'       => $rows,
            'services'   => $services,
            'providers'  => $providers,
            'routePrefix'=> $this->routePrefix,
            'viewPrefix' => $this->viewPrefix,
        ]);
    }

    /** مودال إنشاء طلب */
    public function modalCreate()
    {
        $services  = app($this->serviceModel)->newQuery()->orderByDesc('id')->limit(500)->get();
        $providers = ApiProvider::query()->orderByDesc('id')->get();

        return view('admin.orders._modals.create', [
            'services'    => $services,
            'providers'   => $providers,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
        ]);
    }

    /** مودال عرض */
    public function modalView($id)
    {
        $row = app($this->orderModel)->newQuery()->with(['service','provider'])->findOrFail($id);

        return view('admin.orders._modals.view', [
            'row'         => $row,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
        ]);
    }

    /** مودال تعديل */
    public function modalEdit($id)
    {
        $row = app($this->orderModel)->newQuery()->with(['service','provider'])->findOrFail($id);

        return view('admin.orders._modals.edit', [
            'row'         => $row,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
        ]);
    }
}
