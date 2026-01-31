<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

abstract class BaseOrdersController extends Controller
{
    protected string $kind;          // imei|server|file
    protected string $orderModel;    // \App\Models\ImeiOrder::class
    protected string $serviceModel;  // \App\Models\ImeiService::class
    protected string $indexView;     // admin.orders.imei.index
    protected string $routePrefix;   // admin.orders.imei

    protected function statuses(): array
    {
        return ['waiting', 'inprogress', 'success', 'rejected', 'cancelled'];
    }

    public function index(Request $r)
    {
        $q = trim((string)$r->get('q', ''));
        $status = trim((string)$r->get('status', ''));
        $providerId = trim((string)$r->get('provider', ''));

        $model = $this->orderModel;
        $rowsQ = $model::query()->with(['service', 'provider'])->orderByDesc('id');

        if ($q !== '') {
            $rowsQ->where(function ($w) use ($q) {
                $w->where('device', 'like', "%{$q}%")
                  ->orWhere('remote_id', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }
        if ($status !== '' && in_array($status, $this->statuses(), true)) {
            $rowsQ->where('status', $status);
        }
        if ($providerId !== '') {
            $rowsQ->where('supplier_id', (int)$providerId);
        }

        $rows = $rowsQ->paginate(20)->withQueryString();

        $services = ($this->serviceModel)::query()->orderByDesc('id')->limit(500)->get();
        $providers = ApiProvider::query()->orderByDesc('id')->get();

        return view($this->indexView, compact('rows', 'services', 'providers'));
    }

    public function modalCreate()
    {
        $users = User::query()->orderByDesc('id')->limit(500)->get();
        $services = ($this->serviceModel)::query()->orderByDesc('id')->limit(500)->get();

        return view('admin.orders._modals.create', [
            'kind' => $this->kind,
            'users' => $users,
            'services' => $services,
            'storeUrl' => route($this->routePrefix . '.store'),
        ]);
    }

    public function modalView($order)
    {
        $model = $this->orderModel;
        $row = $model::query()->with(['service', 'provider'])->findOrFail($order);

        return view('admin.orders._modals.view', [
            'kind' => $this->kind,
            'row' => $row,
            'parsed' => $this->parseResponse($row->response),
        ]);
    }

    public function modalEdit($order)
    {
        $model = $this->orderModel;
        $row = $model::query()->with(['service', 'provider'])->findOrFail($order);

        return view('admin.orders._modals.edit', [
            'kind' => $this->kind,
            'row' => $row,
            'statuses' => $this->statuses(),
            'updateUrl' => route($this->routePrefix . '.update', $row->id),
            'parsed' => $this->parseResponse($row->response),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => ['nullable', 'integer'],
            'service_id' => ['required', 'integer'],
            'device' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer'],
            'comments' => ['nullable', 'string'],
        ]);

        $user = !empty($data['user_id']) ? User::find($data['user_id']) : null;
        $service = ($this->serviceModel)::findOrFail((int)$data['service_id']);

        $model = $this->orderModel;
        $order = new $model();

        $order->device = $data['device'];
        $order->comments = $data['comments'] ?? null;

        if ($this->kind === 'server') {
            $order->quantity = (int)($data['quantity'] ?? 1);
        }

        $order->user_id = $user?->id;
        $order->email = $user?->email;

        $order->service_id = $service->id;
        $order->supplier_id = $service->supplier_id ?? null;

        // حالة البداية دائمًا waiting
        $order->status = 'waiting';

        // هل هو API order؟
        $order->api_order = !empty($service->supplier_id) && !empty($service->remote_id);

        // نخزن الطلب الخام
        $order->request = json_encode([
            'kind' => $this->kind,
            'service_id' => $service->id,
            'remote_service_id' => $service->remote_id ?? null,
            'supplier_id' => $service->supplier_id ?? null,
            'device' => $order->device,
            'quantity' => $this->kind === 'server' ? $order->quantity : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $order->ip = (string)($r->ip() ?? '');
        $order->save();

        // ✅ إرسال تلقائي إذا API
        if ($order->api_order && $order->supplier_id && !empty($service->remote_id)) {
            $this->sendDhruNow($order, $service->remote_id);
            $order->refresh();
        }

        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'reload' => true]);
        }

        return redirect()->route($this->routePrefix . '.index')->with('ok', 'Order created.');
    }

    public function update(Request $r, $order)
    {
        $model = $this->orderModel;
        $row = $model::findOrFail($order);

        $data = $r->validate([
            'status' => ['required', 'string'],
            'comments' => ['nullable', 'string'],
            'response' => ['nullable', 'string'],
        ]);

        if (!in_array($data['status'], $this->statuses(), true)) {
            $data['status'] = $row->status;
        }

        $row->status = $data['status'];
        $row->comments = $data['comments'] ?? $row->comments;

        // يسمح لك تعديل/إضافة response يدويًا لو احتجت
        if (array_key_exists('response', $data) && $data['response'] !== null) {
            $row->response = $data['response'];
        }

        $row->save();

        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'reload' => true]);
        }

        return redirect()->route($this->routePrefix . '.index')->with('ok', 'Order updated.');
    }

    /**
     * إرسال DHRU (الآن بدون زر Send)
     * - إذا نجاح واستلم REFERENCEID => status = inprogress + remote_id
     * - إذا ERROR => status = rejected
     */
    protected function sendDhruNow($order, $remoteServiceId): void
    {
        $provider = ApiProvider::find($order->supplier_id);
        if (!$provider || $provider->type !== 'dhru') {
            // لو provider غير موجود/غير dhru نخليها waiting (أنت تقدر تغيّرها لاحقًا)
            return;
        }

        $url = rtrim((string)$provider->url, '/') . '/api/index.php';

        $parametersXml = $this->buildDhruParametersXml($order, $remoteServiceId);

        $payload = [
            'username' => (string)($provider->username ?? ''),
            'apiaccesskey' => (string)($provider->api_key ?? ''),
            'requestformat' => 'JSON',
            'action' => 'placeimeiorder',
            'parameters' => $parametersXml,
        ];

        try {
            $resp = Http::asForm()
                ->timeout(40)
                ->post($url, $payload);

            $body = $resp->body();
            $json = $resp->json();

            $order->response = json_encode([
                'http_status' => $resp->status(),
                'body' => $body,
                'json' => $json,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Parse:
            $parsed = $this->parseResponse($order->response);

            if ($parsed['type'] === 'success' && !empty($parsed['reference'])) {
                $order->remote_id = (string)$parsed['reference'];
                $order->status = 'inprogress';
            } elseif ($parsed['type'] === 'error') {
                $order->status = 'rejected';
            } else {
                // إذا ما قدرنا نفهم الرد نخليها waiting
                $order->status = 'waiting';
            }

            $order->save();
        } catch (\Throwable $e) {
            $order->response = json_encode([
                'exception' => true,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $order->status = 'rejected';
            $order->save();
        }
    }

    protected function buildDhruParametersXml($order, $remoteServiceId): string
    {
        // ملاحظة: مزودين كثير يقبلون القيمة في IMEI حتى لو كانت Serial/Device
        // لذلك نخليها IMEI = device
        $device = (string)($order->device ?? '');

        // Server sometimes has QNT
        $qnt = ($this->kind === 'server' && !empty($order->quantity)) ? (int)$order->quantity : null;

        $xml = '<PARAMETERS>';
        $xml .= '<IMEI>' . htmlspecialchars($device, ENT_XML1) . '</IMEI>';
        $xml .= '<ID>' . htmlspecialchars((string)$remoteServiceId, ENT_XML1) . '</ID>';
        if ($qnt !== null) {
            $xml .= '<QNT>' . (int)$qnt . '</QNT>';
        }
        $xml .= '</PARAMETERS>';

        return $xml;
    }

    protected function parseResponse(?string $response): array
    {
        if (!$response) {
            return ['type' => 'none', 'message' => '', 'reference' => null, 'raw' => null];
        }

        $arr = json_decode($response, true);
        $json = is_array($arr) ? ($arr['json'] ?? null) : null;

        // إذا الرد محفوظ بصيغة {http_status, body, json}
        if (is_array($json)) {
            // ERROR
            if (isset($json['ERROR'][0])) {
                $msg = (string)($json['ERROR'][0]['MESSAGE'] ?? 'ERROR');
                $full = (string)($json['ERROR'][0]['FULL_DESCRIPTION'] ?? '');
                $clean = trim(strip_tags($full));
                return [
                    'type' => 'error',
                    'message' => $clean !== '' ? $clean : $msg,
                    'reference' => null,
                    'raw' => $json,
                ];
            }

            // SUCCESS
            if (isset($json['SUCCESS'][0])) {
                $ref = $json['SUCCESS'][0]['REFERENCEID'] ?? null;
                $msg = (string)($json['SUCCESS'][0]['MESSAGE'] ?? 'Success');
                return [
                    'type' => 'success',
                    'message' => $msg,
                    'reference' => $ref ? (string)$ref : null,
                    'raw' => $json,
                ];
            }
        }

        // fallback
        return ['type' => 'unknown', 'message' => '', 'reference' => null, 'raw' => $arr];
    }
}
