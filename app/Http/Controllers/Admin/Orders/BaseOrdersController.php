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
            ->orderBy('id'); // ascending: 1,2,3...


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
        'user_id'    => ['required','integer'], // ✅ صار مطلوب لخصم الرصيد
        'email'      => ['nullable','string','max:255'],
        'service_id' => ['required','integer'],
        'comments'   => ['nullable','string'],
        'bulk'       => ['nullable','boolean'],
        'devices'    => ['nullable','string'],
    ];

    // file kind: نحتاج ملف
    if ($this->kind === 'file') {
        $rules['file'] = ['required','file','max:51200']; // 50MB
    } else {
        // في non-bulk: device مطلوب
        $rules['device'] = ['nullable','string','max:255'];
    }

    if ($this->supportsQuantity()) {
        $rules['quantity'] = ['nullable','integer','min:1','max:999'];
    }

    if ($this->kind === 'server') {
        $rules['required'] = ['nullable','array'];
    }

    $data = $request->validate($rules);

    $userId = (int)($data['user_id'] ?? 0);
    $email  = trim((string)($data['email'] ?? ''));

    $user = User::find($userId);
    if (!$user) {
        return redirect()->back()->withErrors(['user_id' => 'User not found.'])->withInput();
    }
    if ($email === '') $email = (string)$user->email;

    // service + provider resolution
    $service = ($this->serviceModel)::findOrFail((int)$data['service_id']);
    $supplierId = (int)($service->supplier_id ?? 0);
    $provider   = $supplierId ? ApiProvider::find($supplierId) : null;

    $hasRemote = !empty($service->remote_id);
    $isApi = $provider && (int)$provider->active === 1 && $hasRemote;

    // prices
    $sellPrice = (float)($service->price ?? $service->sell_price ?? 0);
    $costPrice = (float)($service->cost ?? $service->order_price ?? $service->provider_price ?? 0);
    $profitOne = $sellPrice - $costPrice;

    // params
    $params = ['kind' => $this->kind];
    if ($this->kind === 'server' && isset($data['required']) && is_array($data['required'])) {
        $params['required'] = $data['required'];
    }

    // ==========
    // ✅ BULK parsing (non-file only)
    // ==========
    $bulk = (bool)($data['bulk'] ?? false);
    $devices = [];

    if ($this->kind !== 'file') {
        if ($bulk) {
            $raw = (string)($data['devices'] ?? '');
            $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), fn($x) => $x !== ''));

            if (count($lines) < 1) {
                return redirect()->back()->withErrors(['devices' => 'Bulk list is empty.'])->withInput();
            }

            // حد حماية
            if (count($lines) > 200) {
                return redirect()->back()->withErrors(['devices' => 'Too many lines (max 200).'])->withInput();
            }

            $devices = $lines;
        } else {
            $one = trim((string)($data['device'] ?? ''));
            if ($one === '') {
                return redirect()->back()->withErrors(['device' => 'Device is required.'])->withInput();
            }
            $devices = [$one];
        }
    }

    // ==========
    // ✅ BALANCE CHECK + DEDUCT (atomic)
    // ==========
    $countOrders = ($this->kind === 'file') ? 1 : count($devices);
    $totalCharge = $sellPrice * $countOrders;

    try {
        \DB::transaction(function () use (
            $request, $data, $userId, $email, $user,
            $service, $provider, $isApi,
            $sellPrice, $costPrice, $profitOne,
            $params, $devices, $countOrders, $totalCharge
        ) {
            // lock user row
            $u = User::query()->lockForUpdate()->findOrFail($userId);

            // ✅ غيّر balance إذا اسم عمود الرصيد مختلف عندك
            $balance = (float)($u->balance ?? 0);

            if ($totalCharge > 0 && $balance < $totalCharge) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE');
            }

            // deduct
            if ($totalCharge > 0) {
                $u->balance = $balance - $totalCharge;
                $u->save();
            }

            // create one or many orders
            $createOne = function (string $deviceValue = '') use (
                $request, $data, $email, $u, $service, $provider, $isApi,
                $sellPrice, $costPrice, $profitOne, $params
            ) {
                /** @var Model $order */
                $order = new ($this->orderModel);

                $order->comments    = (string)($data['comments'] ?? '');
                $order->user_id     = $u->id;
                $order->email       = $email ?: null;

                $order->service_id  = (int)$service->id;
                $order->supplier_id = $provider?->id;

                if ($this->supportsQuantity()) {
                    $order->quantity = (int)($data['quantity'] ?? 1);
                }

                $order->status     = 'waiting';
                $order->processing = 0;
                $order->api_order  = $isApi ? 1 : 0;

                $order->price       = $sellPrice;
                $order->order_price = $costPrice;
                $order->profit      = $profitOne;

                $order->params = $params;
                $order->ip = $request->ip();

                // file kind
                if ($this->kind === 'file') {
                    $file = $request->file('file');
                    $original = $file->getClientOriginalName();
                    $path = $file->store('orders/files');
                    $order->device = $original;
                    $order->storage_path = $path;
                } else {
                    $order->device = $deviceValue;
                }

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
                        \Log::error('Auto dispatch failed', ['id'=>$order->id,'err'=>$e->getMessage()]);

                        // ✅ لا نرفض عند فشل الاتصال
                        $order->processing = 0;
                        $order->status = 'waiting';
                        $order->replied_at = null;

                        $order->request = array_merge((array)$order->request, [
                            'dispatch_failed_at' => now()->toDateTimeString(),
                            'dispatch_error'     => $e->getMessage(),
                            'dispatch_retry'     => ((int) data_get($order->request, 'dispatch_retry', 0)) + 1,
                        ]);

                        $order->response = [
                            'type'    => 'info',
                            'message' => 'Provider is unreachable. Will retry automatically.',
                        ];

                        $order->save();
                    }
                }

                return $order;
            };

            if ($this->kind === 'file') {
                $createOne('');
            } else {
                foreach ($devices as $dv) {
                    $createOne($dv);
                }
            }
        });
    } catch (\RuntimeException $e) {
        if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
            return redirect()->back()
                ->withErrors(['user_id' => 'No enough balance for this order.'])
                ->withInput();
        }
        throw $e;
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

        'row'   => $row,
        'order' => $row, // ✅ احتياط لو أي view قديم يتوقع order
    ]);
}



    public function modalEdit(int $id)
{
    $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);

    return view('admin.orders.modals.edit', [
        'title'       => "Edit Order #{$row->id}",
        'kind'        => $this->kind,
        'routePrefix' => $this->routePrefix,

        // ✅ مهم: المودال الحالي يتوقع $order
        'row'   => $row,
        'order' => $row,
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
