<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Services\Orders\DhruOrderGateway;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $orderModel;
    /** @var class-string<Model> */
    protected string $serviceModel;

    protected string $kind; // imei|server|file
    protected string $viewPrefix; // admin.orders.imei ...
    protected string $routePrefix; // admin.orders.imei ...

    public function __construct(protected DhruOrderGateway $dhru)
    {
    }

    public function index(Request $r)
    {
        $q = trim((string)$r->get('q', ''));
        $status = trim((string)$r->get('status', ''));
        $provider = trim((string)$r->get('provider', ''));

        $rows = ($this->orderModel)::query()
            ->with(['service', 'provider'])
            ->orderByDesc('id');

        if ($q !== '') {
            $rows->where(function ($w) use ($q) {
                $w->where('device', 'like', "%{$q}%")
                  ->orWhere('remote_id', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($status !== '') $rows->where('status', $status);
        if ($provider !== '') $rows->where('supplier_id', (int)$provider);

        $rows = $rows->paginate(20)->withQueryString();

        $services = ($this->serviceModel)::query()
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        $providers = ApiProvider::query()->orderByDesc('id')->get();

        return view($this->viewPrefix.'.index', compact('rows','services','providers'));
    }

    public function modalCreate()
    {
        $services = ($this->serviceModel)::query()->orderByDesc('id')->limit(2000)->get();
        $providers = ApiProvider::query()->orderByDesc('id')->get();
        return view($this->viewPrefix.'.modals.create', compact('services','providers'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id'    => 'nullable|integer',
            'email'      => 'nullable|string|max:255',
            'service_id' => 'required|integer',
            'device'     => 'nullable|string|max:255',
            'comments'   => 'nullable|string',
            'quantity'   => 'nullable|integer',
        ]);

        /** @var Model $service */
        $service = ($this->serviceModel)::query()->findOrFail((int)$data['service_id']);

        $supplierId = (int)($service->supplier_id ?? 0);
        $remoteServiceId = $service->remote_id ?? null;

        $price = (float)($service->price ?? 0);
        $cost  = (float)($service->cost ?? 0);

        $order = ($this->orderModel)::query()->create([
            'user_id'     => $data['user_id'] ?? null,
            'email'       => $data['email'] ?? null,
            'service_id'  => (int)$service->id,
            'supplier_id' => $supplierId > 0 ? $supplierId : null,

            'device'      => $data['device'] ?? null,
            'quantity'    => $data['quantity'] ?? null,
            'comments'    => $data['comments'] ?? null,
            'ip'          => (string)$r->ip(),

            'price'       => $price,
            'order_price' => $cost,
            'profit'      => max(0, $price - $cost),

            'status'      => 'WAITING',
            'api_order'   => ($supplierId > 0 && !empty($remoteServiceId)) ? true : false,
            'params'      => null,
        ]);

        // ✅ إرسال تلقائي إذا الخدمة مرتبطة بـ API
        if ($order->api_order) {
            $this->sendNow($order);
            $order->refresh();
        } else {
            $order->status = 'MANUAL';
            $order->save();
        }

        return response()->json(['ok'=>true,'id'=>$order->id,'status'=>$order->status]);
    }

    public function modalView($id)
    {
        $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);
        $parsed = $this->parsedResponse($row);
        return view($this->viewPrefix.'.modals.view', compact('row','parsed'));
    }

    public function modalEdit($id)
    {
        $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);
        $parsed = $this->parsedResponse($row);
        return view($this->viewPrefix.'.modals.edit', compact('row','parsed'));
    }

    public function update(Request $r, $id)
    {
        $row = ($this->orderModel)::query()->findOrFail($id);

        $data = $r->validate([
            'status'   => 'required|string|max:50',
            'comments' => 'nullable|string',
            'response' => 'nullable|string',
        ]);

        // ✅ تغيير status يدوي لا يرسل للـ provider (مثل ما طلبت)
        $row->status = $data['status'];
        $row->comments = $data['comments'] ?? $row->comments;

        // لو admin كتب reply يدوي
        if (!empty($data['response'])) {
            $row->response = $data['response'];
            $row->replied_at = now();
        }

        $row->save();

        return response()->json(['ok'=>true]);
    }

    public function send($id)
    {
        $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);
        $this->sendNow($row);
        $row->refresh();
        return response()->json(['ok'=>true,'status'=>$row->status,'remote_id'=>$row->remote_id]);
    }

    public function refresh($id)
    {
        $row = ($this->orderModel)::query()->with(['provider'])->findOrFail($id);
        if (!$row->provider || empty($row->remote_id)) {
            return response()->json(['ok'=>false,'message'=>'No provider/remote id']);
        }

        if ($row->provider->type !== 'dhru') {
            return response()->json(['ok'=>false,'message'=>'Refresh not implemented for this provider type']);
        }

        $resp = $this->fetchDetailsDhru($row);
        $row->response = json_encode($resp, JSON_UNESCAPED_UNICODE);
        $row->replied_at = now();

        // لو جاء SUCCESS بنتيجة نهائية نقدر نحولها SUCCESS (حسب مزودك)
        // حاليًا نخليها INPROGRESS إلا إذا كان واضح أنها منتهية
        $row->save();

        return response()->json(['ok'=>true]);
    }

    protected function sendNow(Model $order): void
    {
        $order->refresh();

        /** @var Model|null $service */
        $service = $order->service ?? ($this->serviceModel)::query()->find($order->service_id);
        /** @var ApiProvider|null $provider */
        $provider = $order->provider ?? ApiProvider::query()->find($order->supplier_id);

        if (!$service || !$provider) {
            $order->status = 'MANUAL';
            $order->api_order = false;
            $order->save();
            return;
        }

        if (empty($service->remote_id)) {
            $order->status = 'MANUAL';
            $order->api_order = false;
            $order->save();
            return;
        }

        $order->processing = true;
        $order->status = 'INPROGRESS';
        $order->save();

        if ($provider->type !== 'dhru') {
            $order->processing = false;
            $order->status = 'FAILED';
            $order->response = json_encode(['ERROR'=>[['MESSAGE'=>'NotImplemented','FULL_DESCRIPTION'=>'Provider type not implemented yet']]], JSON_UNESCAPED_UNICODE);
            $order->save();
            return;
        }

        // ✅ DHRU: place order
        $xmlParams = $this->buildDhruXmlParams($order, $service);

        $order->request = json_encode([
            'provider_id' => $provider->id,
            'type' => $provider->type,
            'action' => $this->kind === 'file' ? 'placefileorder' : 'placeimeiorder',
            'xml' => $xmlParams,
        ], JSON_UNESCAPED_UNICODE);

        $resp = $this->placeDhru($order, $provider, $service, $xmlParams);

        $order->response = json_encode($resp, JSON_UNESCAPED_UNICODE);

        $norm = $this->dhru->normalizeStatus($resp);

        if ($norm['ok']) {
            $order->remote_id = $norm['reference_id'] ?: $order->remote_id;
            $order->status = $norm['status']; // غالبًا INPROGRESS
        } else {
            $order->status = 'FAILED';
        }

        $order->processing = false;
        $order->replied_at = now();
        $order->save();
    }

    protected function placeDhru(Model $order, ApiProvider $provider, Model $service, array $xmlParams): array
    {
        // file kind handled in child
        return $this->dhru->placeImeiOrder($provider, $xmlParams);
    }

    protected function fetchDetailsDhru(Model $order): array
    {
        // Default for imei/server
        return $this->dhru->getImeiOrder($order->provider, (string)$order->remote_id);
    }

    protected function buildDhruXmlParams(Model $order, Model $service): array
    {
        // أساسيات: ID + Device حسب نوع الخدمة
        $main = json_decode((string)($service->main_field ?? '{}'), true) ?: [];
        $mainType = strtolower((string)($main['type'] ?? 'imei'));

        $deviceVal = (string)($order->device ?? '');

        $params = [
            'ID' => (string)($service->remote_id),
        ];

        if ($mainType === 'serial' || $mainType === 'sn') {
            $params['SN'] = $deviceVal;
        } else {
            // default IMEI
            $params['IMEI'] = $deviceVal;
        }

        // إن كان عندك quantity لخدمات server
        if ($this->kind === 'server' && !empty($order->quantity)) {
            $params['QNT'] = (string)$order->quantity;
        }

        return $params;
    }

    protected function parsedResponse(Model $row): array
    {
        $raw = [];
        if (!empty($row->response)) {
            $decoded = json_decode((string)$row->response, true);
            if (is_array($decoded)) $raw = $decoded;
        }

        // extract readable message
        $msg = '';
        if (isset($raw['ERROR'])) $msg = $this->dhru->normalizeStatus($raw)['message'] ?? '';
        elseif (isset($raw['SUCCESS'])) $msg = $this->dhru->normalizeStatus($raw)['message'] ?? '';

        return [
            'message' => $msg,
            'raw' => $raw,
        ];
    }
}
