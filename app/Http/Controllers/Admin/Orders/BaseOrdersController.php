<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;        // ImeiOrder / ServerOrder / FileOrder / ProductOrder
    protected string $kind;         // imei | server | file | product
    protected string $viewFolder;   // admin.orders.imei | server | file | product
    protected string $routePrefix;  // admin.orders.imei | server | file | product

    /** @return class-string<Model>|null */
    protected function serviceModel(): ?string { return null; } // ImeiService/ServerService/FileService
    protected function deviceFieldLabel(): string { return 'Device'; } // IMEI / Serial / Email...
    protected function deviceFieldName(): string { return 'device'; }  // device

    protected function baseQuery()
    {
        /** @var Model $m */
        $m = app($this->model);

        // أغلب موديلاتك فيها relations: service(), provider()
        return $m->newQuery()
            ->with(['service', 'provider'])
            ->orderByDesc('id');
    }

    public function index(Request $r)
    {
        $q = $this->baseQuery();

        $term = trim((string)$r->get('q', ''));
        if ($term !== '') {
            $q->where(function ($w) use ($term) {
                $w->where('device', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('remote_id', 'like', "%{$term}%");
            });
        }

        $status = trim((string)$r->get('status', ''));
        if ($status !== '') {
            $q->where('status', $status);
        }

        $supplierId = (int)$r->get('supplier_id', 0);
        if ($supplierId > 0) {
            $q->where('supplier_id', $supplierId);
        }

        $rows = $q->paginate(20)->withQueryString();

        $providers = ApiProvider::query()->orderBy('name')->get();

        $services = [];
        $serviceModel = $this->serviceModel();
        if ($serviceModel) {
            $services = app($serviceModel)->newQuery()
                ->orderByDesc('id')
                ->limit(800)
                ->get();
        }

        return view("admin.orders.{$this->kind}.index", [
            'rows'        => $rows,
            'providers'   => $providers,
            'services'    => $services,
            'routePrefix' => $this->routePrefix,
            'kind'        => $this->kind,
            'deviceLabel' => $this->deviceFieldLabel(),
            'deviceName'  => $this->deviceFieldName(),
        ]);
    }

    public function modalCreate()
    {
        $providers = ApiProvider::query()->orderBy('name')->get();

        $services = [];
        $serviceModel = $this->serviceModel();
        if ($serviceModel) {
            $services = app($serviceModel)->newQuery()
                ->orderByDesc('id')
                ->limit(800)
                ->get();
        }

        return view('admin.orders._modals.create', [
            'routePrefix' => $this->routePrefix,
            'kind'        => $this->kind,
            'providers'   => $providers,
            'services'    => $services,
            'deviceLabel' => $this->deviceFieldLabel(),
            'deviceName'  => $this->deviceFieldName(),
        ]);
    }

    public function modalView($order)
    {
        /** @var Model $row */
        $row = app($this->model)->newQuery()->with(['service','provider'])->findOrFail($order);

        return view('admin.orders._modals.view', [
            'row'         => $row,
            'routePrefix' => $this->routePrefix,
            'kind'        => $this->kind,
            'deviceLabel' => $this->deviceFieldLabel(),
        ]);
    }

    public function modalEdit($order)
    {
        /** @var Model $row */
        $row = app($this->model)->newQuery()->with(['service','provider'])->findOrFail($order);

        return view('admin.orders._modals.edit', [
            'row'         => $row,
            'routePrefix' => $this->routePrefix,
            'kind'        => $this->kind,
            'deviceLabel' => $this->deviceFieldLabel(),
        ]);
    }

    public function store(Request $r)
    {
        $serviceModel = $this->serviceModel();

        $rules = [
            'user_id'    => ['nullable','integer'],
            'email'      => ['nullable','string','max:255'],
            'service_id' => ['nullable','integer'],
            'comments'   => ['nullable','string'],
            'device'     => ['required','string','max:255'],
        ];

        // server has quantity sometimes
        if ($this->kind === 'server') {
            $rules['quantity'] = ['nullable','integer','min:1'];
        }

        $v = $r->validate($rules);

        // نحدد supplier من الخدمة لو موجودة (أفضل من أن يختارها اليوزر)
        $supplierId = null;
        $remoteId   = null;
        $apiOrder   = false;

        $svc = null;
        if ($serviceModel && !empty($v['service_id'])) {
            $svc = app($serviceModel)->newQuery()->find($v['service_id']);
        }

        if ($svc) {
            $supplierId = $svc->supplier_id ?? null;
            $remoteId   = $svc->remote_id ?? null;
            $apiOrder   = (int)$supplierId > 0 && !empty($remoteId);
        }

        /** @var Model $row */
        $row = app($this->model)->create([
            'device'     => $v['device'],
            'quantity'   => $v['quantity'] ?? null,
            'status'     => 'waiting',
            'comments'   => $v['comments'] ?? null,
            'user_id'    => $v['user_id'] ?? null,
            'email'      => $v['email'] ?? null,
            'service_id' => $v['service_id'] ?? null,
            'supplier_id'=> $supplierId,
            'api_order'  => $apiOrder ? 1 : 0,
            'ip'         => $r->ip(),
            'params'     => null,
            'request'    => null,
            'response'   => null,
            'processing' => 0,
        ]);

        // ✅ إرسال تلقائي إذا الطلب API (بدون زر Send)
        if ($apiOrder) {
            /** @var \App\Services\Orders\OrderDispatcher $dispatcher */
            $dispatcher = app(\App\Services\Orders\OrderDispatcher::class);
            $dispatcher->dispatchNow($this->kind, $row->id);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Created',
        ]);
    }

    public function update(Request $r, $order)
    {
        /** @var Model $row */
        $row = app($this->model)->newQuery()->findOrFail($order);

        $v = $r->validate([
            'status'   => ['required','in:waiting,inprogress,success,rejected,cancelled'],
            'reply'    => ['nullable','string'],
            'comments' => ['nullable','string'],
        ]);

        $row->status = $v['status'];
        $row->comments = $v['comments'] ?? $row->comments;

        // reply عند نجاح/رفض… نخزنه داخل response لو تحب، أو حقل منفصل (في DB عندك response/request موجودة)
        if (array_key_exists('reply', $v)) {
            $row->response = $v['reply'];
            $row->replied_at = now();
        }

        $row->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Saved',
        ]);
    }
}
