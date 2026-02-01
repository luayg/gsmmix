<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $orderModel;

    /** @var class-string<Model> */
    protected string $serviceModel;

    /** route prefix مثل: admin.orders.imei */
    protected string $routePrefix;

    /** title مثل: IMEI Orders */
    protected string $title;

    /** kind: imei|server|file|product */
    protected string $kind;

    public function index(Request $request)
    {
        $q = trim((string)$request->get('q', ''));
        $status = trim((string)$request->get('status', ''));
        $providerId = trim((string)$request->get('provider_id', ''));

        $Order = $this->orderModel;

        $rows = $Order::query()
            ->with(['service', 'provider'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('device', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('remote_id', 'like', "%{$q}%")
                      ->orWhere('id', $q);
                });
            })
            ->when($status !== '' && $status !== 'all', fn($qq) => $qq->where('status', $status))
            ->when($providerId !== '' && $providerId !== 'all', fn($qq) => $qq->where('supplier_id', (int)$providerId))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $providers = ApiProvider::query()->orderBy('name')->get();
        return view("admin.orders.{$this->kind}.index", [
            'title' => $this->title,
            'rows' => $rows,
            'providers' => $providers,
            'routePrefix' => $this->routePrefix,
            'kind' => $this->kind,
        ]);
    }

    public function modalCreate()
    {
        $Service = $this->serviceModel;

        // ✅ users list (خفيف، لتجنب select2 الآن)
        $users = User::query()->orderByDesc('id')->limit(500)->get(['id', 'email', 'balance']);

        // ✅ services list
        $services = $Service::query()
            ->orderBy('id', 'desc')
            ->limit(2000)
            ->get();

        return view('admin.orders._modals.create', [
            'title' => $this->title,
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'users' => $users,
            'services' => $services,
        ]);
    }

    public function store(Request $request)
    {
        $Order = $this->orderModel;
        $Service = $this->serviceModel;

        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'email' => ['nullable', 'string', 'max:255'],
            'service_id' => ['required', 'integer'],
            'device' => ['required', 'string', 'max:255'],
            'comments' => ['nullable', 'string'],
        ]);

        $service = $Service::findOrFail((int)$data['service_id']);

        $user = null;
        if (!empty($data['user_id'])) {
            $user = User::find((int)$data['user_id']);
        }

        $email = $user?->email ?: ($data['email'] ?? null);

        // ✅ سعر الخدمة
        $price = (float)($service->price ?? $service->credits ?? $service->cost ?? 0);

        // ✅ مزود الخدمة (إذا API)
        $supplierId = (int)($service->supplier_id ?? 0);
        $serviceRemoteId = (string)($service->remote_id ?? '');

        $isApi = ($supplierId > 0 && $serviceRemoteId !== '');

        /** @var Model $order */
        $order = new $Order();
        $order->device = (string)$data['device'];
        $order->comments = (string)($data['comments'] ?? '');
        $order->user_id = $user?->id;
        $order->email = $email;

        // order pricing
        $order->order_price = $price;
        $order->price = $price;
        $order->profit = (float)($service->profit ?? 0);

        // link service/provider
        $order->service_id = (int)$service->id;
        $order->supplier_id = $supplierId ?: null;

        // status rules
        $order->status = 'waiting';
        $order->api_order = $isApi ? 1 : 0;
        $order->processing = 0;

        // params: نخزن service remote id هنا (لأن remote_id في order نحتاجه لرقم طلب المزود لاحقاً)
        $order->params = [
            'service_remote_id' => $serviceRemoteId,
            'kind' => $this->kind,
        ];

        $order->ip = $request->ip();

        $order->save();

        // ✅ إرسال تلقائي لو API
        if ($isApi) {
            try {
                $order->processing = 1;
                $order->status = 'inprogress';
                $order->save();

                // هنا نترك الإرسال الفعلي لطبقة OrderSender عندك (إذا موجودة)
                // إن ما عندك إرسال بعد، خليها لاحقاً — لكن الآن على الأقل الطلب ينحفظ ويظهر.
            } catch (\Throwable $e) {
                $order->processing = 0;
                $order->status = 'rejected';
                $order->response = json_encode(['ERROR' => [['MESSAGE' => 'Send failed', 'FULL_DESCRIPTION' => $e->getMessage()]]], JSON_UNESCAPED_UNICODE);
                $order->save();
            }
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order created.');
    }

    public function modalView($orderId)
    {
        $Order = $this->orderModel;
        $order = $Order::with(['service', 'provider'])->findOrFail((int)$orderId);

        return view('admin.orders._modals.view', [
            'title' => $this->title,
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'order' => $order,
        ]);
    }

    public function modalEdit($orderId)
    {
        $Order = $this->orderModel;
        $order = $Order::with(['service', 'provider'])->findOrFail((int)$orderId);

        return view('admin.orders._modals.edit', [
            'title' => $this->title,
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'order' => $order,
            'statuses' => ['waiting', 'inprogress', 'success', 'rejected', 'cancelled'],
        ]);
    }

    public function update(Request $request, $orderId)
    {
        $Order = $this->orderModel;
        $order = $Order::findOrFail((int)$orderId);

        $data = $request->validate([
            'status' => ['required', 'in:waiting,inprogress,success,rejected,cancelled'],
            'comments' => ['nullable', 'string'],
            'response' => ['nullable', 'string'],
        ]);

        $order->status = $data['status'];
        $order->comments = (string)($data['comments'] ?? $order->comments);
        $order->response = $data['response'] ?? $order->response;

        // replied_at لو success/rejected
        if (in_array($order->status, ['success', 'rejected'], true) && empty($order->replied_at)) {
            $order->replied_at = now();
        }

        $order->save();

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order updated.');
    }

    /** helper داخل blade: تحويل name json لاسم نصي */
    public static function serviceNameText($name): string
    {
        if (is_array($name)) {
            return (string)($name['en'] ?? $name['fallback'] ?? reset($name) ?? '');
        }
        if (is_string($name)) {
            $trim = trim($name);
            if ($trim !== '' && Str::startsWith($trim, '{')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    return (string)($decoded['en'] ?? $decoded['fallback'] ?? reset($decoded) ?? $trim);
                }
            }
        }
        return (string)$name;
    }
}
