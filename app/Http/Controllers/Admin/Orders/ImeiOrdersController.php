<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\User;
use App\Services\Orders\OrderDispatcher;
use Illuminate\Http\Request;

class ImeiOrdersController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string)$r->get('q',''));
        $status = trim((string)$r->get('status',''));
        $provider = trim((string)$r->get('provider',''));

        $rows = ImeiOrder::query()
            ->with(['service','provider','user'])
            ->orderByDesc('id');

        if ($q !== '') {
            $rows->where(function($w) use ($q){
                $w->where('device','like',"%$q%")
                  ->orWhere('email','like',"%$q%")
                  ->orWhere('remote_id','like',"%$q%");
            });
        }

        if ($status !== '') $rows->where('status',$status);
        if ($provider !== '') $rows->where('supplier_id',$provider);

        $rows = $rows->paginate(20)->withQueryString();

        $providers = ApiProvider::query()->orderBy('name')->get();

        return view('admin.orders.imei.index', [
            'rows' => $rows,
            'providers' => $providers,
            'routePrefix' => 'admin.orders.imei',
            'kind' => 'imei',
        ]);
    }

    // ========= Modals =========
    public function modalCreate()
    {
        $users = User::query()->orderBy('email')->limit(500)->get();

        $services = ImeiService::query()
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        return view('admin.orders._modals.create', [
            'kind' => 'imei',
            'routePrefix' => 'admin.orders.imei',
            'users' => $users,
            'services' => $services,
        ]);
    }

    public function modalView(ImeiOrder $order)
    {
        $order->load(['service','provider','user']);
        return view('admin.orders._modals.view', [
            'kind' => 'imei',
            'routePrefix' => 'admin.orders.imei',
            'order' => $order,
        ]);
    }

    public function modalEdit(ImeiOrder $order)
    {
        $order->load(['service','provider','user']);
        return view('admin.orders._modals.edit', [
            'kind' => 'imei',
            'routePrefix' => 'admin.orders.imei',
            'order' => $order,
        ]);
    }

    // ========= Store =========
    public function store(Request $r, OrderDispatcher $dispatcher)
    {
        $data = $r->validate([
            'user_id' => ['required','integer','exists:users,id'],
            'service_id' => ['required','integer','exists:imei_services,id'],
            'device' => ['required','string','max:255'],
            'comments' => ['nullable','string'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $service = ImeiService::findOrFail($data['service_id']);

        $price = (float)($service->price ?? 0);
        $cost  = (float)($service->cost ?? 0);
        $profit = $price - $cost;

        // ✅ لو تريد منع أوردر بدون رصيد
        if ((float)($user->balance ?? 0) < $price) {
            return response()->json([
                'ok' => false,
                'message' => 'User balance is not enough to create this order.',
            ], 422);
        }

        $order = ImeiOrder::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'service_id' => $service->id,
            'supplier_id' => $service->supplier_id,
            'device' => $data['device'],
            'comments' => $data['comments'] ?? null,

            'order_price' => $price,
            'price' => $cost,
            'profit' => $profit,

            // ✅ الحالة المطلوبة كبداية
            'status' => 'waiting',
            'api_order' => ($service->supplier_id && $service->remote_id) ? 1 : 0,
            'ip' => $r->ip(),
        ]);

        // ✅ إرسال تلقائي إذا الخدمة مربوطة API
        if ($order->api_order) {
            $dispatcher->dispatchImei($order);
            $order->refresh();
        }

        return response()->json(['ok'=>true,'id'=>$order->id]);
    }

    public function update(Request $r, ImeiOrder $order)
    {
        $data = $r->validate([
            'status' => ['required','in:waiting,inprogress,success,rejected,cancelled'],
            'response' => ['nullable','string'],
            'comments' => ['nullable','string'],
        ]);

        $order->status = $data['status'];
        $order->comments = $data['comments'] ?? $order->comments;
        $order->response = $data['response'] ?? $order->response;

        if ($order->status === 'success' || $order->status === 'rejected' || $order->status === 'cancelled') {
            $order->replied_at = now();
        }

        $order->save();

        return response()->json(['ok'=>true]);
    }
}
