<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\User;
use Illuminate\Http\Request;

class ImeiOrdersController extends Controller
{
    private string $kind = 'imei';
    private string $routePrefix = 'admin.orders.imei';

    public function index(Request $r)
    {
        $q = trim((string)$r->get('q', ''));
        $status = trim((string)$r->get('status', ''));
        $provider = trim((string)$r->get('provider', ''));

        $rows = ImeiOrder::query()
            ->with(['service','provider'])
            ->when($q !== '', function($qq) use ($q){
                $qq->where(function($w) use ($q){
                    $w->where('device','like',"%$q%")
                      ->orWhere('email','like',"%$q%")
                      ->orWhere('remote_id','like',"%$q%");
                });
            })
            ->when($status !== '', fn($qq) => $qq->where('status', $status))
            ->when($provider !== '', fn($qq) => $qq->where('supplier_id', (int)$provider))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // للفلاتر
        $providers = ApiProvider::query()->orderBy('name')->get(['id','name']);
        $statuses = ['waiting','inprogress','success','rejected','cancelled','failed'];

        return view('admin.orders.imei.index', [
            'rows' => $rows,
            'providers' => $providers,
            'statuses' => $statuses,
            'routePrefix' => $this->routePrefix,
            'kind' => $this->kind,
        ]);
    }

    public function modalCreate()
    {
        $users = User::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id','email','name']);

        $servicesRaw = ImeiService::query()
            ->where('active', 1)
            ->orderByDesc('id')
            ->limit(1000)
            ->get(['id','name','price','allow_bulk']);

        $services = $servicesRaw->map(function($s){
            $nameArr = [];
            if (is_string($s->name) && $s->name !== '') {
                $decoded = json_decode($s->name, true);
                if (is_array($decoded)) $nameArr = $decoded;
            }
            $label = (string)($nameArr['en'] ?? $nameArr['fallback'] ?? $s->name ?? ('Service #' . $s->id));

            return [
                'id' => $s->id,
                'label' => $label,
                'price' => (float)($s->price ?? 0),
                'allow_bulk' => (int)($s->allow_bulk ?? 0) === 1,
            ];
        })->values()->all();

        return view('admin.orders._modals.create', [
            'kind' => $this->kind,
            'routePrefix' => $this->routePrefix,
            'users' => $users,
            'services' => $services,
        ]);
    }

    public function store(Request $r)
    {
        // ملاحظة: user_id اختياري، email اختياري، لكن واحد منهم لازم يكون موجود
        $v = $r->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|email|max:255',
            'service_id' => 'required|integer|exists:imei_services,id',
            'device' => 'required|string|max:255',
            'bulk' => 'nullable|string',
            'comments' => 'nullable|string',
        ]);

        if (empty($v['user_id']) && empty($v['email'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Please select user or enter email',
            ], 422);
        }

        $service = ImeiService::findOrFail((int)$v['service_id']);

        // إذا تم اختيار user_id نجيب ايميله تلقائيًا
        $email = $v['email'] ?? null;
        $userId = $v['user_id'] ?? null;
        if ($userId) {
            $u = User::find($userId);
            if ($u) $email = $u->email;
        }

        // الحالة المطلوبة منك: waiting / inprogress / success / rejected / cancelled
        // كبداية دايمًا waiting
        $order = ImeiOrder::create([
            'device' => $v['device'],
            'status' => 'waiting',
            'comments' => $v['comments'] ?? '',
            'user_id' => $userId,
            'email' => $email,
            'service_id' => (int)$service->id,
            'supplier_id' => (int)($service->supplier_id ?? 0) ?: null,
            'remote_id' => (int)($service->remote_id ?? 0) ?: null,

            'api_order' => (int)((int)($service->supplier_id ?? 0) > 0 && (int)($service->remote_id ?? 0) > 0),
            'processing' => 0,
            'request' => null,
            'response' => null,
            'params' => null,
            'ip' => $r->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $order->id,
        ]);
    }
}
