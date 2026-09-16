<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Admin\Orders\FileOrdersController;
use App\Http\Controllers\Admin\Orders\ImeiOrdersController;
use App\Http\Controllers\Admin\Orders\ServerOrdersController;
use App\Http\Controllers\Admin\Orders\SmmOrdersController;
use App\Http\Controllers\Controller;
use App\Models\FileOrder;
use App\Models\FileService;
use App\Models\ImeiOrder;
use App\Models\ImeiService;
use App\Models\ServerOrder;
use App\Models\ServerService;
use App\Models\ServiceGroupPrice;
use App\Models\SmmOrder;
use App\Models\SmmService;
use App\Models\ProductOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\Content\HtmlSanitizer;
use App\Support\CustomerOrderResultPresenter;

final class OrderController extends Controller
{
    private const TYPES = [
        'imei' => [ImeiService::class, ImeiOrder::class, ImeiOrdersController::class],
        'server' => [ServerService::class, ServerOrder::class, ServerOrdersController::class],
        'file' => [FileService::class, FileOrder::class, FileOrdersController::class],
        'smm' => [SmmService::class, SmmOrder::class, SmmOrdersController::class],
        'product' => [null, ProductOrder::class, null],
    ];

    public function create(Request $request)
    {
        $type = $this->type($request->input('type', 'imei'));
        abort_if($type==='product',404);
        $serviceModel = self::TYPES[$type][0];
        $services = $serviceModel::query()->where('active', true)->orderBy('ordering')->orderBy('id')->get();
        $fields = DB::table('custom_fields')->where('service_type', $type.'_service')
            ->where('active', true)->whereIn('service_id', $services->pluck('id'))
            ->orderBy('ordering')->get()->groupBy('service_id');
        $user = $request->user();

        $catalog = $services->map(function (Model $service) use ($fields, $type, $user): array {
            return [
                'id' => $service->id,
                'name' => $this->text($service->name ?? ''),
                'time' => $this->text($service->time ?? ''),
                'info_html' => app(HtmlSanitizer::class)->clean($this->text($service->info ?? '')),
                'price' => $this->price($service, $type, (int) $user->group_id),
                'allow_bulk' => (bool) ($service->allow_bulk ?? false),
                'main_field' => $service->main_field,
                'fields' => collect($fields->get($service->id, []))->map(fn ($field) => [
                    'name' => $this->text($field->name), 'input' => $field->input,
                    'type' => $field->field_type ?: 'text', 'required' => (bool) $field->required,
                    'description' => $this->text($field->description),
                    'minimum' => (int) $field->minimum, 'maximum' => (int) $field->maximum,
                    'options' => $this->options($field->field_options),
                ])->values(),
            ];
        })->values();

        return view('customer.orders.create', compact('type', 'catalog'));
    }

    public function store(Request $request, string $type)
    {
        $type = $this->type($type);
        $request->merge(['user_id' => $request->user()->id]);
        return app(self::TYPES[$type][2])->store($request);
    }

    public function index(Request $request, ?string $type = null)
    {
        $type = $type ? $this->type($type) : null;
        $types = $type ? [$type => self::TYPES[$type]] : self::TYPES;
        $orders = collect($types)->flatMap(function (array $classes, string $kind) use ($request) {
            $relation=$kind==='product'?'product':'service';
            return $classes[1]::query()->where('user_id', $request->user()->id)->with($relation)
                ->latest()->limit(250)->get()->map(fn ($order) => $this->row($order, $kind));
        })->sortByDesc('created_at')->values();

        return view('customer.orders.index', compact('orders', 'type'));
    }

    public function show(Request $request, string $type, int $order)
    {
        $type = $this->type($type);
        $relation=$type==='product'?'product':'service';
        $row = self::TYPES[$type][1]::query()->where('user_id', $request->user()->id)
            ->with($relation)->findOrFail($order);
        $data=['order' => $this->row($row, $type), 'model' => $row];
        if($request->expectsJson()) return response()->json(['html'=>view('customer.orders._details',$data)->render()]);
        return view('customer.orders.show', $data);
    }

    private function type(mixed $type): string
    {
        return validator(['type' => $type], ['type' => ['required', Rule::in(array_keys(self::TYPES))]])->validate()['type'];
    }

    private function row(Model $order, string $type): array
    {
        return ['id' => $order->id, 'type' => $type, 'service' => $this->text($order->service?->name ?? $order->product?->name ?? ucfirst($type).' service'),
            'device' => $order->device ?: '—', 'status' => $order->status, 'amount' => $order->price ?? $order->order_price ?? 0,
            'quantity' => $order->quantity ?? null, 'created_at' => $order->created_at,
            'result' => CustomerOrderResultPresenter::present($order->response ?? null), 'reference' => $order->remote_id ?? data_get($order->response, 'reference_id')];
    }

    private function price(Model $service, string $type, int $groupId): float
    {
        if ($groupId > 0 && class_exists(ServiceGroupPrice::class)) {
            $groupPrice = ServiceGroupPrice::query()->where('service_type', $type)
                ->where('service_id', $service->id)->where('group_id', $groupId)->first();
            if (($price = $groupPrice?->finalPrice($service)) !== null) return (float) $price;
        }
        if (isset($service->price)) return (float) $service->price;
        $cost = (float) ($service->cost ?? 0); $profit = (float) ($service->profit ?? 0);
        return (int) ($service->profit_type ?? 0) === 2 ? $cost + ($cost * $profit / 100) : $cost + $profit;
    }

    private function text(mixed $value): string
    {
        if ($value instanceof \Illuminate\Support\Collection) $value = $value->all();
        if (is_array($value)) return (string) ($value[app()->getLocale()] ?? $value['en'] ?? $value['fallback'] ?? reset($value) ?: '');
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $this->text($decoded) : trim((string) $value);
    }

    private function options(mixed $value): array
    {
        if (is_array($value)) return array_is_list($value) ? $value : (array) ($value[app()->getLocale()] ?? $value['en'] ?? []);
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) return array_is_list($decoded) ? $decoded : (array) ($decoded[app()->getLocale()] ?? $decoded['en'] ?? []);
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $value))));
    }
}
