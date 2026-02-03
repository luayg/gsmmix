<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $orderModel;

    /** @var class-string<Model> */
    protected string $serviceModel;

    protected string $kind;        // imei|server|file|product
    protected string $title;       // IMEI Orders ...
    protected string $routePrefix; // admin.orders.imei ...

    protected function deviceLabel(): string { return 'Device'; }
    protected function supportsQuantity(): bool { return false; }

    public function index(Request $request)
    {
        $q      = trim((string)$request->get('q', ''));
        $status = trim((string)$request->get('status', ''));
        $prov   = trim((string)$request->get('provider', ''));

        $rows = ($this->orderModel)::query()
            ->with(['service','provider'])
            ->orderByDesc('id');

        if ($q !== '') {
            $rows->where(function ($w) use ($q) {
                $w->where('device', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('remote_id', 'like', "%{$q}%");
            });
        }

        if ($status !== '' && in_array($status, ['waiting','inprogress','success','rejected','cancelled'], true)) {
            $rows->where('status', $status);
        }

        if ($prov !== '') {
            $rows->where('supplier_id', (int)$prov);
        }

        $rows = $rows->paginate(20)->withQueryString();
        $providers = ApiProvider::query()->orderBy('name')->get();

        return view("admin.orders.{$this->kind}.index", [
            'title'       => $this->title,
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'rows'        => $rows,
            'providers'   => $providers,
        ]);
    }

    public function modalCreate()
    {
        $users = User::query()->orderByDesc('id')->limit(500)->get();
        $services = ($this->serviceModel)::query()->orderByDesc('id')->limit(1000)->get();

        return view('admin.orders.modals.create', [
            'title'       => "Create {$this->title}",
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'deviceLabel' => $this->deviceLabel(),
            'supportsQty' => $this->supportsQuantity(),
            'users'       => $users,
            'services'    => $services,
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'user_id'    => ['nullable','integer'],
            'email'      => ['nullable','string','max:255'],
            'service_id' => ['required','integer'],
            'comments'   => ['nullable','string'],
        ];

        // file kind: نحتاج ملف
        if ($this->kind === 'file') {
            $rules['file'] = ['required','file','max:51200']; // 50MB
        } else {
            $rules['device'] = ['required','string','max:255'];
        }

        if ($this->supportsQuantity()) {
            $rules['quantity'] = ['nullable','integer','min:1','max:999'];
        }

        // Server required fields (اختياري) يجي من API أو توسعة لاحقاً
        if ($this->kind === 'server') {
            $rules['required'] = ['nullable','array'];
        }

        $data = $request->validate($rules);

        // user resolution
        $userId = (int)($data['user_id'] ?? 0);
        $email  = trim((string)($data['email'] ?? ''));

        $user = null;
        if ($userId > 0) {
            $user = User::find($userId);
            if ($user) $email = $user->email;
        }
        if ($email === '' && $user) $email = (string)$user->email;

        // service + provider resolution
        $service = ($this->serviceModel)::findOrFail((int)$data['service_id']);
        $supplierId = (int)($service->supplier_id ?? 0);
        $provider   = $supplierId ? ApiProvider::find($supplierId) : null;

        $hasRemote = !empty($service->remote_id);
        $isApi = $provider && (int)$provider->active === 1 && $hasRemote;

        // prices
        $sellPrice = (float)($service->price ?? $service->sell_price ?? 0);
        $costPrice = (float)($service->cost ?? $service->order_price ?? $service->provider_price ?? 0);
        $profit    = $sellPrice - $costPrice;

        /** @var Model $order */
        $order = new ($this->orderModel);

        $order->comments    = (string)($data['comments'] ?? '');
        $order->user_id     = $user?->id;
        $order->email       = $email ?: null;

        $order->service_id  = (int)$service->id;
        $order->supplier_id = $provider?->id;

        if ($this->supportsQuantity()) {
            $order->quantity = (int)($data['quantity'] ?? 1);
        }

        // الحالة
        $order->status     = 'waiting';
        $order->processing = 0;
        $order->api_order  = $isApi ? 1 : 0;

        $order->price       = $sellPrice;
        $order->order_price = $costPrice;
        $order->profit      = $profit;

        // params
        $params = ['kind' => $this->kind];

        if ($this->kind === 'server' && isset($data['required']) && is_array($data['required'])) {
            $params['required'] = $data['required'];
        }

        $order->params = $params;
        $order->ip = $request->ip();

        // file upload (File Orders)
        if ($this->kind === 'file') {
            $file = $request->file('file');
            $original = $file->getClientOriginalName();

            // نخزن على default disk (storage/app/...)
            $path = $file->store('orders/files');

            $order->device = $original;         // اسم الملف
            $order->storage_path = $path;       // المسار للتصدير API لاحقاً
        } else {
            $order->device = (string)$data['device'];
        }

        // احفظ أولاً
        $order->save();

        // إرسال تلقائي لو API
        if ($isApi) {
            try {
                $order->processing = 1;
                $order->status = 'inprogress';
                $order->save();

                if (class_exists(\App\Services\Orders\OrderDispatcher::class)) {
                    $dispatcher = app(\App\Services\Orders\OrderDispatcher::class);
                    $dispatcher->send($this->kind, (int)$order->id);
                } else {
                    $order->status = 'waiting';
                    $order->processing = 0;
                    $order->save();
                }
            } catch (\Throwable $e) {
                Log::error('Auto dispatch failed', ['id'=>$order->id,'err'=>$e->getMessage()]);

                $order->processing = 0;
                $order->status = 'rejected';
                $order->response = ['ERROR' => [['MESSAGE' => $e->getMessage()]]];
                $order->replied_at = now();
                $order->save();
            }
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order created.');
    }

    public function modalView(int $id)
{
    $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);

    return view('admin.orders.modals.view', [
        'title'       => "View Order #{$row->id}",
        'kind'        => $this->kind,
        'routePrefix' => $this->routePrefix,

        'row'         => $row,
        'order'       => $row, // ✅ لا يضر ويساعد لو view قديم
    ]);
}


    public function modalEdit(int $id)
{
    $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);

    // decode response for optional provider reply
    $resp = $row->response;
    if (is_string($resp)) {
        $decoded = json_decode($resp, true);
        $resp = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($resp)) $resp = [];

    return view('admin.orders.modals.edit', [
        'title'       => "Edit Order #{$row->id}",
        'kind'        => $this->kind,
        'routePrefix' => $this->routePrefix,

        // ✅ compatibility (old blade expects $order)
        'row'         => $row,
        'order'       => $row,

        // ✅ optional for provider reply feature
        'providerReplyHtml' => $resp['provider_reply_html'] ?? '',
        'replyPreviewHtml'  => $resp['provider_reply_html'] ?? '',
    ]);
}

    public function update(Request $request, int $id)
{
    $row = ($this->orderModel)::findOrFail($id);

    $data = $request->validate([
        'status'             => ['required','in:waiting,inprogress,success,rejected,cancelled'],
        'comments'           => ['nullable','string'],

        // response raw (json/text)
        'response'           => ['nullable'],

        // ✅ Provider reply html (Summernote)
        'provider_reply_html' => ['nullable','string'],
    ]);

    $row->status   = $data['status'];
    $row->comments = (string)($data['comments'] ?? '');

    // ---- normalize existing response to array if possible ----
    $currentResp = $row->response;

    if (is_string($currentResp)) {
        $decoded = json_decode($currentResp, true);
        $currentResp = is_array($decoded) ? $decoded : ['raw' => $row->response];
    } elseif (!is_array($currentResp) && $currentResp !== null) {
        $currentResp = ['raw' => $currentResp];
    } elseif ($currentResp === null) {
        $currentResp = [];
    }

    // ---- apply provided response (if any) ----
    if (array_key_exists('response', $data)) {
        if (is_string($data['response'])) {
            $decoded = json_decode($data['response'], true);

            // إذا JSON صحيح نخزنه array
            if (is_array($decoded)) {
                $currentResp = array_merge($currentResp, $decoded);
            } else {
                // نص عادي
                $currentResp['raw'] = $data['response'];
            }
        } elseif (is_array($data['response'])) {
            $currentResp = array_merge($currentResp, $data['response']);
        }
    }

    // ✅ save provider reply HTML داخل response
    if (!empty($data['provider_reply_html'])) {
        $currentResp['provider_reply_html'] = $data['provider_reply_html'];
        $currentResp['provider_reply_updated_at'] = now()->toDateTimeString();
    } else {
        // لو حاب تمسحها لو تركها فاضية
        // unset($currentResp['provider_reply_html']);
    }

    $row->response = $currentResp;
    $row->save();

    return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order updated.');
}

}
