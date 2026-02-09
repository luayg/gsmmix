<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    // =========================
    // LIST
    // =========================
    public function index(Request $request)
    {
        $q      = trim((string)$request->get('q', ''));
        $status = trim((string)$request->get('status', ''));
        $prov   = trim((string)$request->get('provider', ''));

        // ✅ Per-page selector (10..1000)
        $perPage = (int)$request->get('per_page', 20);
        if ($perPage < 10) $perPage = 10;
        if ($perPage > 1000) $perPage = 1000;

        $rows = ($this->orderModel)::query()
            ->with(['service','provider'])
            ->orderBy('id', 'asc');

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

        $rows = $rows->paginate($perPage)->withQueryString();
        $providers = ApiProvider::query()->orderBy('name')->get();

        return view("admin.orders.{$this->kind}.index", [
            'title'       => $this->title,
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'rows'        => $rows,
            'providers'   => $providers,
            'perPage'     => $perPage,
        ]);
    }

    // =========================
    // CREATE MODAL
    // =========================
    public function modalCreate()
    {
        $users = User::query()->orderByDesc('id')->limit(500)->get();

        // ✅ load services
        $services = ($this->serviceModel)::query()->orderByDesc('id')->limit(1000)->get();

        // ✅ attach custom fields from DB (custom_fields table) into service->params['custom_fields']
        // so that resources/views/admin/orders/modals/create.blade.php keeps working without edits.
        if (in_array($this->kind, ['imei','server','file'], true) && $services->count() > 0) {
            $this->injectCustomFieldsIntoServices($services, $this->kind);
        }

        // ✅ Build group pricing map for UI:
        // [service_id => [group_id => final_price]]
        $servicePriceMap = $this->buildServicePriceMap($services);

        return view('admin.orders.modals.create', [
            'title'          => "Create {$this->title}",
            'kind'           => $this->kind,
            'routePrefix'    => $this->routePrefix,
            'deviceLabel'    => $this->deviceLabel(),
            'supportsQty'    => $this->supportsQuantity(),
            'users'          => $users,
            'services'       => $services,
            'servicePriceMap'=> $servicePriceMap,
        ]);
    }

    /**
     * Inject DB custom_fields into $service->params['custom_fields']
     * Output format matches what your Blade expects:
     * [
     *   ['active'=>1,'name'=>'','input'=>'service_fields_1','type'=>'text','description'=>'','minimum'=>0,'maximum'=>0,'validation'=>null,'required'=>0,'options'=>'...'],
     *   ...
     * ]
     */
    private function injectCustomFieldsIntoServices($services, string $kind): void
    {
        $serviceIds = $services->pluck('id')->map(fn($x)=>(int)$x)->filter()->values()->all();
        if (empty($serviceIds)) return;

        $serviceType = $kind . '_service';

        $rows = DB::table('custom_fields')
            ->where('service_type', $serviceType)
            ->whereIn('service_id', $serviceIds)
            ->orderBy('service_id', 'asc')
            ->orderBy('ordering', 'asc')
            ->get([
                'service_id',
                'active',
                'required',
                'minimum',
                'maximum',
                'validation',
                'description',
                'field_options',
                'field_type',
                'input',
                'name',
                'ordering',
            ]);

        // Group rows by service_id
        $byService = [];
        foreach ($rows as $r) {
            $sid = (int)($r->service_id ?? 0);
            if ($sid <= 0) continue;

            $byService[$sid] ??= [];
            $byService[$sid][] = $r;
        }

        foreach ($services as $svc) {
            $sid = (int)($svc->id ?? 0);
            if ($sid <= 0) continue;

            // decode current params
            $params = $svc->params ?? [];
            if (is_string($params)) {
                $decoded = json_decode($params, true);
                $params = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($params)) $params = [];

            $fields = [];
            foreach (($byService[$sid] ?? []) as $r) {
                $name = $this->pickTranslatableText($r->name ?? '');
                $desc = $this->pickTranslatableText($r->description ?? '');

                $input = trim((string)($r->input ?? ''));
                if ($input === '') continue;

                $type = strtolower(trim((string)($r->field_type ?? 'text')));
                if ($type === '') $type = 'text';

                // options: keep as string (Blade will split/parse it)
                $opts = $r->field_options ?? '';
                $opts = $this->normalizeOptionsForBlade($opts);

                $fields[] = [
                    'active'      => (int)($r->active ?? 1),
                    'name'        => $name,
                    'input'       => $input,
                    'type'        => $type,
                    'description' => $desc,
                    'minimum'     => (int)($r->minimum ?? 0),
                    'maximum'     => (int)($r->maximum ?? 0),
                    'validation'  => ($r->validation ?? null) !== '' ? (string)$r->validation : null,
                    'required'    => (int)($r->required ?? 0),
                    'options'     => $opts, // string or json string
                ];
            }

            $params['custom_fields'] = $fields;
            $svc->params = $params; // Eloquent will cast to JSON in Blade (you already handle string/array there)
        }
    }

    /**
     * Build servicePriceMap for the create order modal.
     * Map: [service_id => [group_id => finalPrice]]
     */
    private function buildServicePriceMap($services): array
    {
        if (!class_exists(\App\Models\ServiceGroupPrice::class)) return [];

        $ids = $services->pluck('id')->map(fn($x)=>(int)$x)->filter()->values()->all();
        if (empty($ids)) return [];

        // service_type in service_group_prices table appears as:
        // - your Blade uses it as "imei" pricing map; the model uses $this->kind in other places.
        // We'll keep it aligned with existing usage:
        $serviceType = $this->kind;

        $rows = \App\Models\ServiceGroupPrice::query()
            ->where('service_type', $serviceType)
            ->whereIn('service_id', $ids)
            ->get(['service_id','group_id','price','discount','discount_type']);

        $out = [];
        foreach ($rows as $gp) {
            $sid = (int)($gp->service_id ?? 0);
            $gid = (int)($gp->group_id ?? 0);
            if ($sid <= 0 || $gid <= 0) continue;

            $price = (float)($gp->price ?? 0);
            if ($price <= 0) continue;

            $discount = (float)($gp->discount ?? 0);
            $dtype = (int)($gp->discount_type ?? 1); // 1 fixed, 2 percent

            if ($discount > 0) {
                if ($dtype === 2) $price = $price - ($price * ($discount / 100));
                else $price = $price - $discount;
            }

            if ($price < 0) $price = 0.0;

            $out[$sid] ??= [];
            $out[$sid][$gid] = (float)$price;
        }

        return $out;
    }

    /**
     * If a DB column contains JSON like {"en":"..","fallback":".."} return en/fallback.
     * Otherwise return as plain string.
     */
    private function pickTranslatableText($value): string
    {
        if (is_array($value)) {
            return (string)($value['en'] ?? $value['fallback'] ?? reset($value) ?? '');
        }

        $s = trim((string)$value);
        if ($s === '') return '';

        if (isset($s[0]) && $s[0] === '{') {
            $j = json_decode($s, true);
            if (is_array($j)) {
                return (string)($j['en'] ?? $j['fallback'] ?? reset($j) ?? $s);
            }
        }

        return $s;
    }

    /**
     * Normalize field_options for Blade splitOptions():
     * - if it's JSON translatable => pick en/fallback
     * - if it's JSON array => keep JSON string (Blade parses JSON arrays)
     * - else => return string as is
     */
    private function normalizeOptionsForBlade($opts): string
    {
        if (is_array($opts)) {
            return json_encode($opts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        $s = trim((string)$opts);
        if ($s === '') return '';

        // JSON?
        if (isset($s[0]) && ($s[0] === '{' || $s[0] === '[')) {
            $j = json_decode($s, true);

            // JSON array => keep json string
            if (is_array($j) && array_is_list($j)) {
                return json_encode($j, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }

            // JSON object (maybe translations) => pick text
            if (is_array($j)) {
                $picked = (string)($j['en'] ?? $j['fallback'] ?? '');
                return $picked !== '' ? $picked : $s;
            }
        }

        return $s;
    }

    // =========================
    // PRICING (user group)
    // =========================
    private function calcServiceSellPriceForUser($service, User $user): float
    {
        if ($this->kind === 'imei') {
            $gid = (int)($user->group_id ?? 0);

            if ($gid > 0 && class_exists(\App\Models\ServiceGroupPrice::class)) {
                $gp = \App\Models\ServiceGroupPrice::query()
                    ->where('service_type', 'imei')
                    ->where('service_id', (int)$service->id)
                    ->where('group_id', $gid)
                    ->first();

                if ($gp && (float)($gp->price ?? 0) > 0) {
                    $price = (float)$gp->price;
                    $discount = (float)($gp->discount ?? 0);
                    $dtype = (int)($gp->discount_type ?? 1); // 1 fixed, 2 percent

                    if ($discount > 0) {
                        if ($dtype === 2) $price = $price - ($price * ($discount / 100));
                        else $price = $price - $discount;
                    }
                    return max(0.0, (float)$price);
                }
            }
        }

        foreach ([
            $service->price ?? null,
            $service->sell_price ?? null,
            $service->final_price ?? null,
            $service->customer_price ?? null,
            $service->retail_price ?? null,
        ] as $p) {
            if ($p !== null && $p !== '' && is_numeric($p) && (float)$p > 0) return (float)$p;
        }

        $cost = (float)($service->cost ?? 0);
        $profit = (float)($service->profit ?? 0);
        $profitType = (int)($service->profit_type ?? 1); // 1 fixed, 2 percent
        if ($profitType === 2) return max(0.0, $cost + ($cost * ($profit/100)));
        return max(0.0, $cost + $profit);
    }

    // =========================
    // REFUND / RECHARGE helpers
    // =========================
    private function refundOrderIfNeeded(Model $order, string $reason): void
    {
        $req = (array)($order->request ?? []);
        if (!empty($req['refunded_at'])) return;

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) return;

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) return;

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) return;

            $u->balance = (float)($u->balance ?? 0) + $amount;
            $u->save();

            $req['refunded_at'] = now()->toDateTimeString();
            $req['refunded_amount'] = $amount;
            $req['refunded_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
    }

    private function rechargeOrderIfNeeded(Model $order, string $reason): void
    {
        $req = (array)($order->request ?? []);

        if (empty($req['refunded_at'])) return;
        if (!empty($req['recharged_at'])) return;

        $uid = (int)($order->user_id ?? 0);
        if ($uid <= 0) return;

        $amount = (float)($req['charged_amount'] ?? 0);
        if ($amount <= 0) return;

        DB::transaction(function () use ($order, $uid, $amount, $reason, $req) {
            $u = User::query()->lockForUpdate()->find($uid);
            if (!$u) return;

            $bal = (float)($u->balance ?? 0);
            if ($bal < $amount) {
                throw new \RuntimeException('INSUFFICIENT_BALANCE_RECHARGE');
            }

            $u->balance = $bal - $amount;
            $u->save();

            $req['recharged_at'] = now()->toDateTimeString();
            $req['recharged_amount'] = $amount;
            $req['recharged_reason'] = $reason;

            $order->request = $req;
            $order->save();
        });
    }

    // =========================
    // STORE
    // =========================
    public function store(Request $request)
    {
        $rules = [
            'user_id'    => ['required','integer'],
            'service_id' => ['required','integer'],
            'comments'   => ['nullable','string'],
            'bulk'       => ['nullable','boolean'],
            'devices'    => ['nullable','string'],
        ];

        if ($this->kind === 'file') {
            $rules['file'] = ['required','file','max:51200'];
        } else {
            // ✅ device optional for server (fields-based)
            $rules['device'] = ['nullable','string','max:255'];
        }

        if ($this->supportsQuantity()) {
            $rules['quantity'] = ['nullable','integer','min:1','max:999'];
        }

        /**
         * ✅ IMPORTANT:
         * UI sends required[...] for all kinds (imei/server/file) based on custom fields
         * so accept it and store in params.fields
         */
        if ($this->kind !== 'product') {
            $rules['required'] = ['nullable','array']; // required[field_input]=...
        }

        $data = $request->validate($rules);

        $userId = (int)($data['user_id'] ?? 0);
        $user = User::find($userId);
        if (!$user) {
            return redirect()->back()->withErrors(['user_id' => 'User not found.'])->withInput();
        }

        $service = ($this->serviceModel)::findOrFail((int)$data['service_id']);

        $supplierId = (int)($service->supplier_id ?? 0);
        $provider   = $supplierId ? ApiProvider::find($supplierId) : null;

        $hasRemote = !empty($service->remote_id);
        $isApi = $provider && (int)$provider->active === 1 && $hasRemote;

        $sellPrice = (float)$this->calcServiceSellPriceForUser($service, $user);
        $costPrice = (float)($service->cost ?? $service->order_price ?? $service->provider_price ?? 0);
        $profitOne = $sellPrice - $costPrice;

        // ✅ params unified
        $params = ['kind' => $this->kind];

        // Quantity: store in params for server like ready site
        if ($this->supportsQuantity()) {
            $params['quantity'] = (int)($data['quantity'] ?? 1);
        }

        // ✅ store fields for ALL kinds (imei/server/file)
        $params['fields'] = (isset($data['required']) && is_array($data['required'])) ? $data['required'] : [];

        $bulk = (bool)($data['bulk'] ?? false);
        $devices = [];

        // ✅ IMEI/File: same logic
        // ✅ Server: device optional unless service->device_based = 1
        if ($this->kind !== 'file') {
            $deviceBased = (bool)($service->device_based ?? false);

            if ($deviceBased) {
                if ($bulk) {
                    $raw = (string)($data['devices'] ?? '');
                    $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
                    $lines = array_values(array_filter(array_map('trim', $lines), fn($x) => $x !== ''));
                    if (count($lines) < 1) {
                        return redirect()->back()->withErrors(['devices' => 'Bulk list is empty.'])->withInput();
                    }
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
            } else {
                // ✅ fields-based service: create single order even if device empty
                $one = trim((string)($data['device'] ?? ''));
                $devices = [$one]; // can be empty string
            }
        }

        $countOrders = ($this->kind === 'file') ? 1 : max(1, count($devices));
        $totalCharge = $sellPrice * $countOrders;

        try {
            DB::transaction(function () use (
                $request, $data, $userId, $service, $provider, $isApi,
                $sellPrice, $costPrice, $profitOne, $params, $devices, $countOrders, $totalCharge
            ) {
                $u = User::query()->lockForUpdate()->findOrFail($userId);
                $balance = (float)($u->balance ?? 0);

                if ($totalCharge > 0 && $balance < $totalCharge) {
                    throw new \RuntimeException('INSUFFICIENT_BALANCE');
                }

                if ($totalCharge > 0) {
                    $u->balance = $balance - $totalCharge;
                    $u->save();
                }

                $createOne = function (string $deviceValue = '') use (
                    $request, $data, $u, $service, $provider, $isApi,
                    $sellPrice, $costPrice, $profitOne, $params
                ) {
                    /** @var Model $order */
                    $order = new ($this->orderModel);

                    $order->comments    = (string)($data['comments'] ?? '');
                    $order->user_id     = $u->id;
                    $order->email       = $u->email ?: null;

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

                    $order->request = array_merge((array)($order->request ?? []), [
                        'charged_amount' => (float)$sellPrice,
                        'charged_at'     => now()->toDateTimeString(),
                    ]);

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
                            $order->status = 'waiting';
                            $order->replied_at = null;

                            $order->request = array_merge((array)($order->request ?? []), [
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
                    foreach ($devices as $dv) $createOne($dv);
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
            'order' => $row,
        ]);
    }

    public function modalEdit(int $id)
    {
        $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);

        return view('admin.orders.modals.edit', [
            'title'       => "Edit Order #{$row->id}",
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'row'   => $row,
            'order' => $row,
        ]);
    }

    // =========================
    // UPDATE (manual status change)
    // =========================
    public function update(Request $request, int $id)
    {
        $row = ($this->orderModel)::findOrFail($id);

        $data = $request->validate([
            'status'              => ['required','in:waiting,inprogress,success,rejected,cancelled'],
            'comments'            => ['nullable','string'],
            'response'            => ['nullable'],
            'provider_reply_html' => ['nullable','string'],
        ]);

        $oldStatus = strtolower(trim((string)($row->getOriginal('status') ?? '')));
        $newStatus = strtolower(trim((string)($data['status'] ?? '')));

        $row->status   = $data['status'];
        $row->comments = (string)($data['comments'] ?? '');

        $currentResp = $row->response;
        if (is_string($currentResp)) {
            $decoded = json_decode($currentResp, true);
            $currentResp = is_array($decoded) ? $decoded : ['raw' => $row->response];
        } elseif (!is_array($currentResp) && $currentResp !== null) {
            $currentResp = ['raw' => $currentResp];
        } elseif ($currentResp === null) {
            $currentResp = [];
        }

        if (array_key_exists('response', $data)) {
            if (is_string($data['response'])) {
                $decoded = json_decode($data['response'], true);
                if (is_array($decoded)) $currentResp = array_merge($currentResp, $decoded);
                else $currentResp['raw'] = $data['response'];
            } elseif (is_array($data['response'])) {
                $currentResp = array_merge($currentResp, $data['response']);
            }
        }

        if (!empty($data['provider_reply_html'])) {
            $currentResp['provider_reply_html'] = $data['provider_reply_html'];
            $currentResp['provider_reply_updated_at'] = now()->toDateTimeString();
        }

        $row->response = $currentResp;
        $row->save();

        // Refund on transition to rejected/cancelled
        if (in_array($newStatus, ['rejected','cancelled'], true) && $oldStatus !== $newStatus) {
            $this->refundOrderIfNeeded($row, 'manual_'.$newStatus);

            $req = (array)($row->request ?? []);
            unset($req['recharged_at'], $req['recharged_amount'], $req['recharged_reason']);
            $row->request = $req;
            $row->save();
        }

        // Recharge only if rejected/cancelled => success
        if ($newStatus === 'success' && in_array($oldStatus, ['rejected','cancelled'], true)) {
            try {
                $this->rechargeOrderIfNeeded($row, 'manual_success');

                $req = (array)($row->request ?? []);
                unset($req['refunded_at'], $req['refunded_amount'], $req['refunded_reason']);
                $row->request = $req;
                $row->save();
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'INSUFFICIENT_BALANCE_RECHARGE') {
                    $row->status = $oldStatus ?: $row->status;
                    $row->save();

                    return redirect()->back()
                        ->withErrors(['status' => 'User balance is not enough to set Success again (recharge required).'])
                        ->withInput();
                }
                throw $e;
            }
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order updated.');
    }
}
