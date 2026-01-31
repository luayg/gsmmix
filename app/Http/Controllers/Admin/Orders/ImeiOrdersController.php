<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Http\Request;

class ImeiOrdersController extends Controller
{
    private string $kind = 'imei';
    private string $routePrefix = 'admin.orders.imei';

    public function index(Request $r)
    {
        $q        = trim((string)$r->get('q',''));
        $status   = trim((string)$r->get('status',''));
        $provider = (int)$r->get('provider_id', 0);

        $rows = ImeiOrder::query()
            ->with(['service','provider'])
            ->when($q !== '', function($qq) use ($q){
                $qq->where(function($w) use ($q){
                    $w->where('device','like',"%$q%")
                      ->orWhere('remote_id','like',"%$q%")
                      ->orWhere('email','like',"%$q%");
                });
            })
            ->when($status !== '', fn($qq)=>$qq->where('status',$status))
            ->when($provider > 0, fn($qq)=>$qq->where('supplier_id',$provider))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $providers = ApiProvider::query()->orderBy('name')->get();

        return view('admin.orders.imei.index', [
            'rows' => $rows,
            'providers' => $providers,
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
        ]);
    }

    public function modalCreate()
    {
        $users = User::query()->select(['id','email','balance'])->orderByDesc('id')->limit(500)->get();
        $services = ImeiService::query()->orderBy('id','desc')->limit(1000)->get();

        return view('admin.orders._modals.create', [
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'users' => $users,
            'services' => $services,
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'user_id' => ['required','integer'],
            'service_id' => ['required','integer'],
            'device' => ['required','string','max:255'],
            'comments' => ['nullable','string'],
            'bulk' => ['nullable','string'],
        ]);

        $service = ImeiService::findOrFail($data['service_id']);

        // bulk: إذا الخدمة تسمح (allow_bulk_orders) ننشئ عدة طلبات
        $allowBulk = (int)($service->allow_bulk_orders ?? 0) === 1;
        $bulkLines = [];
        if ($allowBulk && !empty($data['bulk'])) {
            $bulkLines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $data['bulk']))));
        }

        $makeOne = function(string $device) use ($service, $data, $r) {
            $order = new ImeiOrder();
            $order->user_id   = (int)$data['user_id'];
            $order->email     = optional(\App\Models\User::find($order->user_id))->email;
            $order->service_id= (int)$service->id;
            $order->supplier_id = (int)($service->supplier_id ?? 0);
            $order->device    = $device;
            $order->comments  = $data['comments'] ?? null;

            $order->order_price = (float)($service->price ?? 0);
            $order->price       = (float)($service->price ?? 0);
            $order->profit      = (float)($service->profit ?? 0);

            $order->status    = 'waiting';
            $order->api_order = ((int)($service->supplier_id ?? 0) > 0 && !empty($service->remote_id)) ? 1 : 0;
            $order->ip        = $r->ip();

            $order->save();

            // إرسال تلقائي إذا API
            if ($order->api_order && $order->supplier_id > 0) {
                $this->trySendToProvider($order, $service);
            }

            return $order;
        };

        if (!empty($bulkLines)) {
            foreach ($bulkLines as $line) {
                $makeOne($line);
            }
            return response()->json(['ok'=>true, 'message'=>'Bulk orders created.']);
        }

        $makeOne($data['device']);

        return response()->json(['ok'=>true, 'message'=>'Order created.']);
    }

    public function modalView(ImeiOrder $order)
    {
        $order->load(['service','provider']);
        return view('admin.orders._modals.view', [
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'order' => $order,
        ]);
    }

    public function modalEdit(ImeiOrder $order)
    {
        $order->load(['service','provider']);
        return view('admin.orders._modals.edit', [
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'order' => $order,
        ]);
    }

    public function update(Request $r, ImeiOrder $order)
    {
        $data = $r->validate([
            'status' => ['required','in:waiting,inprogress,success,rejected,cancelled'],
            'comments' => ['nullable','string'],
        ]);

        $order->status = $data['status'];
        $order->comments = $data['comments'] ?? $order->comments;
        $order->save();

        return response()->json(['ok'=>true, 'message'=>'Order updated.']);
    }

    private function trySendToProvider(ImeiOrder $order, ImeiService $service): void
    {
        try {
            $provider = ApiProvider::find($order->supplier_id);
            if (!$provider || (int)$provider->active !== 1) {
                $order->status = 'rejected';
                $order->response = json_encode(['ERROR'=>'Provider inactive'], JSON_UNESCAPED_UNICODE);
                $order->save();
                return;
            }

            // status -> inprogress قبل الإرسال
            $order->status = 'inprogress';
            $order->save();

            // DHRU: placeimeiorder
            // ملاحظة: نفترض أن remote_id في service هو ID الخدمة عند المزود
            $gateway = app(\App\Services\Orders\DhruOrderGateway::class);
            $resp = $gateway->placeImeiOrder($provider, [
                'IMEI' => $order->device,
                'ID'   => (string)$service->remote_id,
                // CUSTOMFIELD لاحقاً حسب متطلبات الخدمة
            ]);

            $order->request  = $resp['request'] ?? null;
            $order->response = $resp['raw'] ?? null;

            if (!empty($resp['ok']) && !empty($resp['reference_id'])) {
                $order->remote_id = (string)$resp['reference_id']; // رقم طلب المزود
                // يبقى inprogress إلى أن نضيف polling لاحقاً
                $order->status = 'inprogress';
            } else {
                $order->status = 'rejected';
            }

            $order->save();

        } catch (\Throwable $e) {
            $order->status = 'rejected';
            $order->response = json_encode(['ERROR'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
            $order->save();
        }
    }
}
