<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $orderModel;

    /** @var class-string<Model> */
    protected string $serviceModel;

    protected string $kind; // imei|server|file|product
    protected string $title; // IMEI Orders ...
    protected string $routePrefix; // admin.orders.imei ...

    /** override per kind */
    protected function deviceLabel(): string { return 'Device'; }
    protected function supportsQuantity(): bool { return false; }

    public function index(Request $request)
    {
        $q       = trim((string)$request->get('q', ''));
        $status  = trim((string)$request->get('status', ''));
        $prov    = trim((string)$request->get('provider', ''));

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

    /** مودال create */
    public function modalCreate()
    {
        $users = User::query()->orderByDesc('id')->limit(500)->get();

        // نجيب الخدمات فقط التي فعلاً قابلة للبيع (حسب مشروعك)
        $services = ($this->serviceModel)::query()
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

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

    /** إنشاء الطلب */
    public function store(Request $request)
    {
        $rules = [
            'user_id'    => ['nullable','integer'],
            'email'      => ['nullable','string','max:255'],
            'service_id' => ['required','integer'],
            'device'     => ['required','string','max:255'],
            'comments'   => ['nullable','string'],
        ];

        if ($this->supportsQuantity()) {
            $rules['quantity'] = ['nullable','integer','min:1','max:999'];
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

        if ($email === '' && $user) {
            $email = (string)$user->email;
        }

        // service + provider resolution
        $service = ($this->serviceModel)::findOrFail((int)$data['service_id']);

        // هذه الطريقة متوافقة مع مشروعك لأن الخدمات عندك فيها supplier_id + remote_id
        $supplierId = (int)($service->supplier_id ?? 0);
        $provider   = $supplierId ? ApiProvider::find($supplierId) : null;

        $hasRemote = !empty($service->remote_id);
        $isApi = $provider && $provider->active && $hasRemote;

        // prices (حاولنا نقرأ أكثر من اسم حقل لأن مشاريع كثيرة تختلف)
        $sellPrice = (float)($service->price ?? $service->sell_price ?? 0);
        $costPrice = (float)($service->cost ?? $service->order_price ?? $service->provider_price ?? 0);
        $profit    = $sellPrice - $costPrice;

        /** @var Model $order */
        $order = new ($this->orderModel);

        $order->device      = (string)$data['device'];
        $order->comments    = (string)($data['comments'] ?? '');
        $order->user_id     = $user?->id;
        $order->email       = $email ?: null;

        $order->service_id  = (int)$service->id;
        $order->supplier_id = $provider?->id;

        if ($this->supportsQuantity()) {
            $order->quantity = (int)($data['quantity'] ?? 1);
        }

        // ✅ الحالة تبدأ دائمًا waiting (لا rejected بدون إرسال)
        $order->status     = 'waiting';
        $order->processing = 0;
        $order->api_order  = $isApi ? 1 : 0;

        $order->price       = $sellPrice;
        $order->order_price = $costPrice;
        $order->profit      = $profit;

        // ✅ params لازم تكون array (casts سيحوّلها تلقائياً JSON)
        $order->params = [
            'kind' => $this->kind,
        ];

        $order->ip = $request->ip();

        // ✅ احفظ أولاً (هذا يمنع ضياع الطلب)
        $order->save();

        // ✅ إرسال تلقائي لو API
        if ($isApi) {
            try {
                $order->processing = 1;
                $order->status = 'inprogress';
                $order->save();

                // هنا يفترض عندك OrderDispatcher/OrderSender (أنت أرسلت صور وجودهم)
                // نستخدمه إن كان موجود
                if (class_exists(\App\Services\Orders\OrderDispatcher::class)) {
                    /** @var \App\Services\Orders\OrderDispatcher $dispatcher */
                    $dispatcher = app(\App\Services\Orders\OrderDispatcher::class);
                    $dispatcher->send($this->kind, $order->id);
                    // الدسباتشر هو الذي يحدّث status/remote_id/response
                } else {
                    // إذا الدسباتشر غير موجود: لا نكسر النظام
                    $order->status = 'waiting';
                    $order->processing = 0;
                    $order->save();
                }
            } catch (\Throwable $e) {
                $order->processing = 0;
                $order->status = 'rejected';
                $order->response = ['ERROR' => [['MESSAGE' => $e->getMessage()]]];
                $order->save();
            }
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order created.');
    }

    /** View modal */
    public function modalView(int $id)
    {
        $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);

        return view('admin.orders.modals.view', [
            'title'       => "View Order #{$row->id}",
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'row'         => $row,
        ]);
    }

    /** Edit modal */
    public function modalEdit(int $id)
    {
        $row = ($this->orderModel)::query()->with(['service','provider'])->findOrFail($id);

        return view('admin.orders.modals.edit', [
            'title'       => "Edit Order #{$row->id}",
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'row'         => $row,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $row = ($this->orderModel)::findOrFail($id);

        $data = $request->validate([
            'status'   => ['required','in:waiting,inprogress,success,rejected,cancelled'],
            'comments' => ['nullable','string'],
            'response' => ['nullable'],
        ]);

        $row->status   = $data['status'];
        $row->comments = (string)($data['comments'] ?? '');

        // لو المستخدم كتب response نص/JSON
        if (isset($data['response'])) {
            if (is_string($data['response'])) {
                $decoded = json_decode($data['response'], true);
                $row->response = is_array($decoded) ? $decoded : ['raw' => $data['response']];
            } elseif (is_array($data['response'])) {
                $row->response = $data['response'];
            }
        }

        $row->save();

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order updated.');
    }
}
