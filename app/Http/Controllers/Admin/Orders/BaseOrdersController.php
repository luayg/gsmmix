// C:\xampp\htdocs\gsmmix\app\Http\Controllers\Admin\Orders\BaseOrdersController.php
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

    private function calcServiceSellPriceForUser($service, User $user): float
    {
        // IMEI group pricing
        if ($this->kind === 'imei') {
            $gid = (int)($user->group_id ?? 0);

            if ($gid > 0 && class_exists(\App\Models\ServiceGroupPrice::class)) {
                $gp = \App\Models\ServiceGroupPrice::query()
                    ->where('service_type', 'imei')
                    ->where('service_id', (int)$service->id)
                    ->where('group_id', $gid)
                    ->first();

                if ($gp) {
                    $base = (float)($gp->price ?? 0);
                    if ($base > 0) {
                        $price = $base;

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
        }

        // fallback explicit columns
        foreach ([
            $service->price ?? null,
            $service->sell_price ?? null,
            $service->final_price ?? null,
            $service->customer_price ?? null,
            $service->retail_price ?? null,
        ] as $p) {
            if ($p !== null && $p !== '' && is_numeric($p) && (float)$p > 0) return (float)$p;
        }

        // fallback cost + profit
        $cost = (float)($service->cost ?? 0);
        $profit = (float)($service->profit ?? 0);
        $profitType = (int)($service->profit_type ?? 1); // 1 fixed, 2 percent
        if ($profitType === 2) return max(0.0, $cost + ($cost * ($profit / 100)));
        return max(0.0, $cost + $profit);
    }

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
        $user = User::find($userId);
        if (!$user) {
            return redirect()->back()->withErrors(['user_id' => 'User not found.'])->withInput();
        }

        $service = ($this->serviceModel)::findOrFail((int)$data['service_id']);

        $supplierId = (int)($service->supplier_id ?? 0);
        $provider   = $supplierId ? ApiProvider::find($supplierId) : null;

        $hasRemote = !empty($service->remote_id);
        $isApi = $provider && (int)$provider->active === 1 && $hasRemote;

        // ✅ price based on user group
        $sellPrice = (float)$this->calcServiceSellPriceForUser($service, $user);

        $costPrice = (float)($service->cost ?? $service->order_price ?? $service->provider_price ?? 0);
        $profitOne = $sellPrice - $costPrice;

        $params = ['kind' => $this->kind];
        if ($this->kind === 'server' && isset($data['required']) && is_array($data['required'])) {
            $params['required'] = $data['required'];
        }

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

        $countOrders = ($this->kind === 'file') ? 1 : count($devices);
        $totalCharge = $sellPrice * $countOrders;

        try {
            DB::transaction(function () use (
                $request, $data, $userId, $service, $provider, $isApi,
                $sellPrice, $costPrice, $profitOne, $params, $devices, $totalCharge
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

                    // ✅ store charged amount for refund
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

    public function update(Request $request, int $id)
    {
        $row = ($this->orderModel)::findOrFail($id);

        $data = $request->validate([
            'status'              => ['required','in:waiting,inprogress,success,rejected,cancelled'],
            'comments'            => ['nullable','string'],
            'response'            => ['nullable'],
            'provider_reply_html' => ['nullable','string'],
        ]);

        $oldStatus = strtolower((string)($row->status ?? ''));
        $newStatus = strtolower((string)($data['status'] ?? ''));

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

        // ✅ Refund only when moved into rejected/cancelled
        if (($newStatus === 'rejected' || $newStatus === 'cancelled') && $oldStatus !== $newStatus) {
            $this->refundOrderIfNeeded($row, 'manual_'.$newStatus);
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order updated.');
    }
}
