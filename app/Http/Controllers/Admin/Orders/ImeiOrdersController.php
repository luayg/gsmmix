<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ImeiOrdersController extends Controller
{
    public function index(Request $r)
    {
        $q        = trim((string)$r->get('q', ''));
        $status   = trim((string)$r->get('status', ''));
        $provider = (int)$r->get('provider_id', 0);

        $rows = ImeiOrder::query()
            ->with(['service', 'provider'])
            ->orderByDesc('id');

        if ($q !== '') {
            $rows->where(function ($w) use ($q) {
                $w->where('device', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('remote_id', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        if ($status !== '') {
            $rows->where('status', $status);
        }

        if ($provider > 0) {
            $rows->where('supplier_id', $provider);
        }

        $rows = $rows->paginate(20)->withQueryString();

        $providers = ApiProvider::query()->orderBy('name')->get();

        return view('admin.orders.imei.index', [
            'rows'       => $rows,
            'providers'  => $providers,
            'routePrefix'=> 'admin.orders.imei',
        ]);
    }

    /**
     * Modal: create
     */
    public function modalCreate()
    {
        $users = User::query()
            ->select(['id', 'email', 'balance'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        $services = ImeiService::query()
            ->select(['id','name','price','allow_bulk','main_field','min_qty','max_qty','supplier_id','remote_id'])
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->map(function ($s) {
                $nameArr = json_decode((string)$s->name, true);
                if (!is_array($nameArr)) $nameArr = [];

                $title = (string)($nameArr['en'] ?? $nameArr['fallback'] ?? $s->name ?? '');
                $title = trim($title) === '' ? ('Service #' . $s->id) : $title;

                $price = (float)($s->price ?? 0);

                $main = json_decode((string)$s->main_field, true);
                if (!is_array($main)) $main = [];

                $mainLabel = (string)Arr::get($main, 'label.en', Arr::get($main, 'label.fallback', 'IMEI'));
                $mainType  = (string)Arr::get($main, 'type', 'imei');

                return [
                    'id'         => (int)$s->id,
                    'label'      => $title . ' — $' . number_format($price, 2),
                    'price'      => $price,
                    'allow_bulk' => (int)($s->allow_bulk ?? 0),
                    'main_label' => $mainLabel ?: 'IMEI',
                    'main_type'  => $mainType ?: 'imei',
                    'min_qty'    => (int)($s->min_qty ?? 0),
                    'max_qty'    => (int)($s->max_qty ?? 0),
                    'supplier_id'=> (int)($s->supplier_id ?? 0),
                    'remote_id'  => $s->remote_id,
                ];
            })
            ->values();

        return view('admin.orders.imei.modals.create', [
            'users'      => $users,
            'services'   => $services,
            'routePrefix'=> 'admin.orders.imei',
        ]);
    }

    /**
     * Store (POST) — هذا كان لا يحدث عندك لأن الفورم كان يرسل GET
     */
    public function store(Request $r)
    {
        $v = $r->validate([
            'user_id'    => 'nullable|integer|exists:users,id',
            'email'      => 'nullable|email',
            'service_id' => 'required|integer|exists:imei_services,id',
            'device'     => 'required|string|max:255',
            'comments'   => 'nullable|string',
            'bulk'       => 'nullable|string', // optional (لو allow_bulk)
        ]);

        $service = ImeiService::findOrFail((int)$v['service_id']);

        $userId = !empty($v['user_id']) ? (int)$v['user_id'] : null;
        $email  = $v['email'] ?? null;

        if ($userId) {
            $u = User::find($userId);
            if ($u) $email = $u->email;
        }

        // السعر/الربح (نفس أعمدتك الموجودة بالموديل)
        $finalPrice = (float)($service->price ?? 0);
        $cost       = (float)($service->cost ?? 0);
        $profit     = $finalPrice - $cost;

        $order = ImeiOrder::create([
            'device'      => $v['device'],
            'remote_id'   => null,                 // يصير ReferenceID بعد الإرسال للمزود
            'status'      => 'waiting',            // حسب طلبك: waiting أولاً
            'order_price' => $finalPrice,          // سعر البيع
            'price'       => $finalPrice,
            'profit'      => $profit,

            'comments'    => $v['comments'] ?? null,

            'user_id'     => $userId,
            'email'       => $email,

            'service_id'  => (int)$service->id,
            'supplier_id' => $service->supplier_id,  // المزود المرتبط بالخدمة
            'needs_verify'=> 0,
            'expired'     => 0,
            'approved'    => 1,

            'ip'          => $r->ip(),
            'api_order'   => ($service->supplier_id && $service->remote_id) ? 1 : 0,
            'params'      => json_encode([
                'kind'      => 'imei',
                'bulk'      => $v['bulk'] ?? null,
                'service_remote_id' => $service->remote_id,
            ], JSON_UNESCAPED_UNICODE),
            'processing'  => 0,
        ]);

        // ملاحظة: الإرسال التلقائي للمزود “فعلياً” لازم يمر عبر OrderDispatcher/OrderSender عندك
        // لكن بدون رؤية توقيعات الكلاسات الحالية عندك قد نكسر النظام.
        // الآن على الأقل: الحفظ صار صحيح + الحالة waiting جاهزة للـ auto-dispatch بالخطوة التالية.

        return redirect()->route('admin.orders.imei.index')
            ->with('ok', 'Order created (waiting). #' . $order->id);
    }
}
