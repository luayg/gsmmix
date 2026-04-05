<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\User;
use App\Services\Orders\OrderFinanceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

abstract class BaseOrdersController extends Controller
{
    /** @var class-string<Model> */
    protected string $orderModel;

    /** @var class-string<Model> */
    protected string $serviceModel;

    protected string $kind;        // imei|server|file|product|smm
    protected string $title;       // IMEI Orders ...
    protected string $routePrefix; // admin.orders.imei ...

    protected function deviceLabel(): string { return 'Device'; }
    protected function supportsQuantity(): bool { return false; }

    protected function finance(): OrderFinanceService
    {
        return app(OrderFinanceService::class);
    }

    // =========================
    // LIST
    // =========================
    public function index(Request $request)
    {
        $q      = trim((string)$request->get('q', ''));
        $status = trim((string)$request->get('status', ''));
        $prov   = trim((string)$request->get('provider', ''));

        $perPage = (int)$request->get('per_page', 20);
        if ($perPage < 10) $perPage = 10;
        if ($perPage > 1000) $perPage = 1000;

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
        $services = ($this->serviceModel)::query()
            ->where('active', 1)
            ->orderByDesc('id')
            ->limit(1000)
            ->get();

        if (in_array($this->kind, ['imei','server','file'], true) && $services->count() > 0) {
            $this->injectCustomFieldsIntoServices($services, $this->kind);
        }

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
                    'options'     => $opts,
                ];
            }

            $params['custom_fields'] = $fields;
            $svc->params = $params;
        }
    }

    private function buildServicePriceMap($services): array
    {
        if (!class_exists(\App\Models\ServiceGroupPrice::class)) return [];

        $ids = $services->pluck('id')->map(fn($x)=>(int)$x)->filter()->values()->all();
        if (empty($ids)) return [];

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
            $dtype = (int)($gp->discount_type ?? 1);

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

    private function normalizeOptionsForBlade($opts): string
    {
        if (is_array($opts)) {
            return json_encode($opts, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }

        $s = trim((string)$opts);
        if ($s === '') return '';

        if (isset($s[0]) && ($s[0] === '{' || $s[0] === '[')) {
            $j = json_decode($s, true);

            if (is_array($j) && array_is_list($j)) {
                return json_encode($j, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }

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
                    $dtype = (int)($gp->discount_type ?? 1);

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
        $profitType = (int)($service->profit_type ?? 1);
        if ($profitType === 2) return max(0.0, $cost + ($cost * ($profit/100)));
        return max(0.0, $cost + $profit);
    }

    private function failValidation(Request $request, array $errors, int $status = 422)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok'     => false,
                'message'=> 'Validation error',
                'errors' => $errors,
            ], $status);
        }

        return redirect()->back()->withErrors($errors)->withInput();
    }

    private function duplicateSubmitResponse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Order already submitted.',
                'redirect_url' => route("{$this->routePrefix}.index"),
            ]);
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order already submitted.');
    }

    private function decodeArray($value): array
    {
        if (is_array($value)) return $value;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function safeScalarText($value): string
{
    if (is_array($value)) {
        return $this->pickTranslatableText($value);
    }

    if (is_string($value)) {
        $s = trim($value);

        if ($s !== '' && ($s[0] === '{' || $s[0] === '[')) {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                return $this->pickTranslatableText($decoded);
            }
        }

        return $s;
    }

    if ($value === null) {
        return '';
    }

    return trim((string)$value);
}

    private function serviceMainFieldMeta($service): array
{
    $meta = $this->decodeArray($service->main_field ?? []);

    $type = strtolower($this->safeScalarText($meta['type'] ?? ($service->main_type ?? '')));
    $label = $this->safeScalarText($meta['label'] ?? '');
    $allowed = strtolower($this->safeScalarText($meta['allowed_characters'] ?? ''));

    $min = isset($meta['minimum']) && is_numeric($meta['minimum']) ? (int)$meta['minimum'] : null;
    $max = isset($meta['maximum']) && is_numeric($meta['maximum']) ? (int)$meta['maximum'] : null;

    if ($type === '') {
        $params = $this->decodeArray($service->params ?? []);
        $type = strtolower($this->safeScalarText($params['main_field_type'] ?? ''));
    }

    if ($type === '') {
        $type = 'text';
    }

    $presets = [
        'imei'        => ['label' => 'IMEI',        'allowed' => 'numbers',      'min' => 15, 'max' => 15],
        'serial'      => ['label' => 'IMEI/Serial', 'allowed' => 'any',          'min' => 10, 'max' => 13],
        'imei_serial' => ['label' => 'IMEI/Serial', 'allowed' => 'any',          'min' => 10, 'max' => 15],
        'number'      => ['label' => 'Number',      'allowed' => 'numbers',      'min' => 1,  'max' => 255],
        'email'       => ['label' => 'Email',       'allowed' => 'any',          'min' => 3,  'max' => 255],
        'text'        => ['label' => 'Text',        'allowed' => 'any',          'min' => 1,  'max' => 255],
        'custom'      => ['label' => 'Custom',      'allowed' => 'alphanumeric', 'min' => 1,  'max' => 255],
    ];

    $preset = $presets[$type] ?? $presets['text'];

    return [
        'type' => $type,
        'label' => $label !== '' ? $label : $preset['label'],
        'allowed_characters' => $allowed !== '' ? $allowed : $preset['allowed'],
        'minimum' => $min !== null ? $min : $preset['min'],
        'maximum' => $max !== null ? $max : $preset['max'],
    ];
}

    private function validateAllowedCharacters(string $value, string $allowed): bool
    {
        return match ($allowed) {
            'numbers', 'numeric' => preg_match('/^\d+$/', $value) === 1,
            'alphanumeric' => preg_match('/^[A-Za-z0-9]+$/', $value) === 1,
            default => true,
        };
    }

    private function validateSingleDeviceByType(string $value, array $meta): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Value is required.';
        }

        $type = strtolower(trim((string)($meta['type'] ?? 'text')));
        $min  = (int)($meta['minimum'] ?? 0);
        $max  = (int)($meta['maximum'] ?? 0);
        $allowed = strtolower(trim((string)($meta['allowed_characters'] ?? 'any')));
        $len = mb_strlen($value);

        if ($type === 'email') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'Email format is invalid.';
            }
            if ($min > 0 && $len < $min) return "Email must be at least {$min} characters.";
            if ($max > 0 && $len > $max) return "Email must be at most {$max} characters.";
            return null;
        }

        if ($type === 'imei') {
            if (!preg_match('/^\d+$/', $value)) {
                return 'IMEI must contain numbers only.';
            }
            if ($len !== 15) {
                return 'IMEI must be exactly 15 digits.';
            }
            return null;
        }

        if ($type === 'serial') {
            if ($allowed !== 'any' && !$this->validateAllowedCharacters($value, $allowed)) {
                return 'Serial contains invalid characters.';
            }
            if ($len < 10) return 'Serial must be at least 10 characters.';
            if ($len > 13) return 'Serial must be at most 13 characters.';
            return null;
        }

        if ($type === 'imei_serial') {
            if (preg_match('/^\d{15}$/', $value)) {
                return null; // valid IMEI
            }

            if ($len >= 10 && $len <= 13) {
                if ($allowed !== 'any' && !$this->validateAllowedCharacters($value, $allowed)) {
                    return 'IMEI/Serial contains invalid characters.';
                }
                return null; // valid Serial
            }

            return 'Value must be either a 15-digit IMEI or a Serial between 10 and 13 characters.';
        }

        if ($min > 0 && $len < $min) {
            return "Value must be at least {$min} characters.";
        }

        if ($max > 0 && $len > $max) {
            return "Value must be at most {$max} characters.";
        }

        if (!$this->validateAllowedCharacters($value, $allowed)) {
            return 'Value contains invalid characters.';
        }

        return null;
    }

    private function validateDeviceInputsForService(Request $request, $service): array
    {
        if ($this->kind === 'file' || $this->kind === 'server' || $this->kind === 'smm') {
            return [[], []];
        }

        $meta = $this->serviceMainFieldMeta($service);
        $bulk = (bool)$request->boolean('bulk');
        $deviceBased = (bool)($service->device_based ?? false);

        $cleanDevices = [];
        $errors = [];

        if (!$deviceBased) {
            $one = trim((string)$request->input('device', ''));
            $err = $this->validateSingleDeviceByType($one, $meta);
            if ($err !== null) {
                $errors['device'] = $err;
            } else {
                $cleanDevices[] = $one;
            }
            return [$cleanDevices, $errors];
        }

        if ($bulk) {
            $raw = (string)$request->input('devices', '');
            $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), fn($x) => $x !== ''));

            if (count($lines) < 1) {
                $errors['devices'] = 'Bulk list is empty.';
                return [[], $errors];
            }

            if (count($lines) > 200) {
                $errors['devices'] = 'Too many lines (max 200).';
                return [[], $errors];
            }

            foreach ($lines as $idx => $line) {
                $err = $this->validateSingleDeviceByType($line, $meta);
                if ($err !== null) {
                    $humanIndex = $idx + 1;
                    $errors['devices'] = "Line {$humanIndex}: {$err}";
                    return [[], $errors];
                }
                $cleanDevices[] = $line;
            }

            return [$cleanDevices, []];
        }

        $one = trim((string)$request->input('device', ''));
        $err = $this->validateSingleDeviceByType($one, $meta);
        if ($err !== null) {
            $errors['device'] = $err;
            return [[], $errors];
        }

        $cleanDevices[] = $one;
        return [$cleanDevices, []];
    }

    private function validateRequiredFieldsAgainstService(Request $request, $service): array
    {
        if ($this->kind === 'product') {
            return [];
        }

        $params = $this->decodeArray($service->params ?? []);
        $customFields = $params['custom_fields'] ?? [];
        if (!is_array($customFields) || empty($customFields)) {
            return [];
        }

        $submitted = $request->input('required', []);
        if (!is_array($submitted)) {
            $submitted = [];
        }

        $errors = [];

        foreach ($customFields as $field) {
            if (!is_array($field)) continue;
            if ((int)($field['active'] ?? 1) !== 1) continue;

            $input = trim((string)($field['input'] ?? ''));
            if ($input === '') continue;

            $name = trim((string)($field['name'] ?? $input));
            $required = (int)($field['required'] ?? 0) === 1;
            $validation = strtolower(trim((string)($field['validation'] ?? '')));
            $minimum = is_numeric($field['minimum'] ?? null) ? (int)$field['minimum'] : 0;
            $maximum = is_numeric($field['maximum'] ?? null) ? (int)$field['maximum'] : 0;
            $type = strtolower(trim((string)($field['type'] ?? 'text')));

            $value = $submitted[$input] ?? null;

            if (is_array($value)) {
                $flat = array_values(array_filter(array_map(fn($v) => trim((string)$v), $value), fn($v) => $v !== ''));
                $valueText = implode("\n", $flat);
            } else {
                $valueText = trim((string)$value);
            }

            if ($required && $valueText === '') {
                $errors["required.{$input}"] = "{$name} is required.";
                continue;
            }

            if ($valueText === '') {
                continue;
            }

            $len = mb_strlen($valueText);

            if ($minimum > 0 && $len < $minimum) {
                $errors["required.{$input}"] = "{$name} must be at least {$minimum} characters.";
                continue;
            }

            if ($maximum > 0 && $len > $maximum) {
                $errors["required.{$input}"] = "{$name} must be at most {$maximum} characters.";
                continue;
            }

            if ($type === 'number' || $validation === 'numeric') {
                if (!preg_match('/^\d+$/', $valueText)) {
                    $errors["required.{$input}"] = "{$name} must contain numbers only.";
                    continue;
                }
            }

            if ($validation === 'email' && !filter_var($valueText, FILTER_VALIDATE_EMAIL)) {
                $errors["required.{$input}"] = "{$name} must be a valid email.";
                continue;
            }

            if ($validation === 'imei') {
                if (!preg_match('/^\d{15}$/', $valueText)) {
                    $errors["required.{$input}"] = "{$name} must be exactly 15 digits.";
                    continue;
                }
            }

            if ($validation === 'serial') {
                if ($len < 10 || $len > 13) {
                    $errors["required.{$input}"] = "{$name} must be between 10 and 13 characters.";
                    continue;
                }
            }
        }

        return $errors;
    }

    // =========================
    // STORE
    // =========================
    public function store(Request $request)
    {
        $rules = [
            'user_id'     => ['required','integer'],
            'service_id'  => ['required','integer'],
            'comments'    => ['nullable','string'],
            'bulk'        => ['nullable','boolean'],
            'devices'     => ['nullable','string'],
            'request_uid' => ['required','string','max:100'],
        ];

        if ($this->kind === 'file') {
            $rules['file'] = ['required','file','max:51200'];
        } else {
            $rules['device'] = ['nullable','string','max:255'];
        }

        if ($this->supportsQuantity()) {
            $rules['quantity'] = ['nullable','integer','min:1','max:999'];
        }

        if ($this->kind !== 'product') {
            $rules['required'] = ['nullable','array'];
        }

        $data = $request->validate($rules);

        $requestUid = trim((string)($data['request_uid'] ?? ''));
        $sessionId = (string)$request->session()->getId();
        $submitLockKey = 'order_submit_lock:' . sha1($this->kind . '|' . $sessionId . '|' . $requestUid);

        if (!Cache::add($submitLockKey, now()->toDateTimeString(), now()->addMinutes(10))) {
            return $this->duplicateSubmitResponse($request);
        }

        try {
            $userId = (int)($data['user_id'] ?? 0);
            $user = User::find($userId);
            if (!$user) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, ['user_id' => 'User not found.'], 422);
            }

            $service = ($this->serviceModel)::query()
                ->where('id', (int)$data['service_id'])
                ->where('active', 1)
                ->first();
            if (!$service) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, ['service_id' => 'Service is not active or not found.'], 422);
            }

            $customFieldErrors = $this->validateRequiredFieldsAgainstService($request, $service);
            if (!empty($customFieldErrors)) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, $customFieldErrors, 422);
            }

            [$validatedDevices, $deviceErrors] = $this->validateDeviceInputsForService($request, $service);
            if (!empty($deviceErrors)) {
                Cache::forget($submitLockKey);
                return $this->failValidation($request, $deviceErrors, 422);
            }

            if ($this->kind === 'file') {
                $uploadedFile = $request->file('file');
                $allowedExtensions = $this->extractAllowedExtensionsFromServiceParams($service);

                if (!empty($allowedExtensions) && $uploadedFile) {
                    $uploadedExt = strtolower((string)$uploadedFile->getClientOriginalExtension());
                    if (!$this->isExtensionAllowed($uploadedExt, $allowedExtensions)) {
                        Cache::forget($submitLockKey);
                        return $this->failValidation($request, [
                            'file' => 'File extension is not allowed for this service. Allowed: ' . implode(', ', $allowedExtensions),
                        ], 422);
                    }
                }
            }

            $supplierId = (int)($service->supplier_id ?? 0);
            $provider   = $supplierId ? ApiProvider::find($supplierId) : null;

            $hasRemote = !empty($service->remote_id);
            $isApi = $provider && (int)$provider->active === 1 && $hasRemote;

            $sellPrice = (float)$this->calcServiceSellPriceForUser($service, $user);
            $costPrice = (float)($service->cost ?? $service->order_price ?? $service->provider_price ?? 0);
            $profitOne = $sellPrice - $costPrice;

            $params = ['kind' => $this->kind];

            if ($this->supportsQuantity()) {
                $params['quantity'] = (int)($data['quantity'] ?? 1);
            }

            $params['fields'] = (isset($data['required']) && is_array($data['required'])) ? $data['required'] : [];

            $bulk = (bool)($data['bulk'] ?? false);
            $devices = [];

            if ($this->kind !== 'file') {
                $deviceBased = (bool)($service->device_based ?? false);

                if ($deviceBased) {
                    if ($bulk) {
                        $devices = $validatedDevices;
                    } else {
                        $devices = $validatedDevices;
                    }
                } else {
                    $devices = $validatedDevices;
                }
            }

            $countOrders = ($this->kind === 'file') ? 1 : max(1, count($devices));
            $totalCharge = $sellPrice * $countOrders;

            DB::transaction(function () use (
                $request, $data, $userId, $service, $provider, $isApi,
                $sellPrice, $costPrice, $profitOne, $params, $devices, $countOrders, $totalCharge, $requestUid
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
                    $sellPrice, $costPrice, $profitOne, $params, $requestUid
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
                        'request_uid'    => $requestUid,
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
            Cache::forget($submitLockKey);

            if ($e->getMessage() === 'INSUFFICIENT_BALANCE') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'No enough balance for this order.',
                    ], 422);
                }

                return redirect()->back()
                    ->withErrors(['user_id' => 'No enough balance for this order.'])
                    ->withInput();
            }

            throw $e;
        } catch (\Throwable $e) {
            Cache::forget($submitLockKey);
            throw $e;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Order created.',
                'redirect_url' => route("{$this->routePrefix}.index"),
            ]);
        }

        return redirect()->route("{$this->routePrefix}.index")->with('ok', 'Order created.');
    }

    private function extractAllowedExtensionsFromServiceParams($service): array
    {
        $params = $service->params ?? [];
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($params)) return [];

        $raw = trim((string)($params['allowed_extensions'] ?? ''));
        if ($raw === '') return [];

        $parts = preg_split('/[,\s]+/', $raw) ?: [];
        $normalized = [];
        foreach ($parts as $ext) {
            $clean = strtolower(trim((string)$ext));
            $clean = ltrim($clean, '.');
            if ($clean === '') continue;
            $normalized[$clean] = true;
        }

        return array_keys($normalized);
    }

    private function isExtensionAllowed(string $extension, array $allowedExtensions): bool
    {
        $ext = ltrim(strtolower(trim($extension)), '.');
        if ($ext === '') {
            return false;
        }

        foreach ($allowedExtensions as $allowed) {
            $allowedNorm = ltrim(strtolower(trim((string)$allowed)), '.');
            if ($allowedNorm !== '' && $allowedNorm === $ext) {
                return true;
            }
        }

        return false;
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

        if (in_array($newStatus, ['rejected','cancelled'], true) && $oldStatus !== $newStatus) {
            $this->finance()->refundOrderIfNeeded($row, 'manual_'.$newStatus);

            $req = (array)($row->request ?? []);
            unset($req['recharged_at'], $req['recharged_amount'], $req['recharged_reason']);
            $row->request = $req;
            $row->save();
        }

        if ($newStatus === 'success' && in_array($oldStatus, ['rejected','cancelled'], true)) {
            try {
                $this->finance()->rechargeOrderIfNeeded($row, 'manual_success');

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