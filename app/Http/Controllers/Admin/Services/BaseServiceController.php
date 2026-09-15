<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ServiceGroup;
use App\Models\ServiceGroupPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

abstract class BaseServiceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $viewPrefix;   // imei|server|file
    protected string $routePrefix;  // admin.services.server ...
    protected string $table;        // server_services ...

    // =========================
    // Index
    // =========================
    public function index(Request $r)
    {
        $q = ($this->model)::query()->orderBy('id', 'asc');

        // حاول تعمل eager load فقط للعلاقات الموجودة فعليًا (بدون ما يسبب RelationNotFound)
        $tmp = new ($this->model);
        $rels = [];
        foreach (['group', 'supplier', 'api'] as $rel) {
            if (method_exists($tmp, $rel)) $rels[] = $rel;
        }
        if ($rels) $q->with($rels);

        if ($r->filled('q')) {
            $term = trim((string)$r->q);
            $q->where(function ($qq) use ($term) {
                $qq->where('alias', 'like', "%$term%")
                    ->orWhere('name', 'like', "%$term%");
            });
        }

        // ✅ فلتر API connection الصحيح: supplier_id (مش source)
        if ($r->filled('api_provider_id')) {
            $pid = (int)$r->api_provider_id;
            if ($pid > 0) $q->where('supplier_id', $pid);
        }

         if ($r->filled('status')) {
            $status = strtolower(trim((string)$r->status));
            if ($status === 'active') {
                $q->where(function ($qq) {
                    $qq->where('active', 1)->orWhere('active', true)->orWhere('active', '1');
                });
            } elseif ($status === 'inactive') {
                $q->where(function ($qq) {
                    $qq->where('active', 0)->orWhere('active', false)->orWhere('active', '0')->orWhereNull('active');
                });
            }
        }

        $rows = $q->paginate(20)->withQueryString();

        // ✅ قائمة مزودين API (id + name) بدل pluck int (حل خطأ Attempt to read property "id" on int)
        $apis = ApiProvider::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return view("admin.services.{$this->viewPrefix}.index", [
            'rows' => $rows,
            'apis' => $apis,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
        ]);
    }

    public function modalEdit($service)
    {
        $row = ($this->model)::query()->findOrFail($service);
        return redirect()->route($this->routePrefix . '.index', ['edit_service' => $row->id]);
    }

    public function toggle(Request $request, $service)
    {
        $data = $request->validate(['active' => 'sometimes|required|boolean']);
        $active = DB::transaction(function () use ($service, $data) {
            $row = ($this->model)::query()->lockForUpdate()->findOrFail($service);
            $row->active = array_key_exists('active', $data) ? (bool) $data['active'] : !$row->active;
            $row->save();
            return (bool) $row->active;
        });
        return response()->json(['ok' => true, 'active' => $active]);
    }

    public function modalCreate()
    {
        return view("admin.services.{$this->viewPrefix}._modal_create");
    }

    // =========================
    // JSON for Edit modal
    // =========================
      public function showJson($service)
{
    $row = ($this->model)::query()->findOrFail($service);

    // name/time/info/main_field/params قد تكون JSON مخزنة كنص
    $decode = function ($value) {
        // Read raw storage so collection/array casts cannot hide legacy
        // double encoding. Normal writes require only the first decode.
        for ($depth = 0; $depth < 2 && is_string($value); $depth++) {
            $value = json_decode($value, true);
        }
        return is_array($value) ? $value : [];
    };

    $name = $decode($row->getRawOriginal('name'));
    $time = $decode($row->getRawOriginal('time'));
    $info = $decode($row->getRawOriginal('info'));
    $main = $decode($row->getRawOriginal('main_field'));
    $params = $decode($row->getRawOriginal('params'));

    // group prices
    $gp = [];
    $gpList = [];
    if (class_exists(ServiceGroupPrice::class)) {
        $gpRows = ServiceGroupPrice::query()
            ->where('service_type', $this->viewPrefix)
            ->where('service_id', (int)$row->id)
            ->get();
        foreach ($gpRows as $g) {
            $groupId = (int) $g->group_id;
            $price = $g->basePrice($row) ?? (float) $g->price;
            $discount = (float) $g->discount;
            $discountType = (int) $g->discount_type;

            $gp[$groupId] = [
                'price' => $price,
                'auto_price' => (bool) $g->auto_price,
                'discount' => $discount,
                'discount_type' => $discountType,
            ];

            $gpList[] = [
                'group_id' => $groupId,
                'price' => $price,
                'auto_price' => (bool) $g->auto_price,
                'discount' => $discount,
                'discount_type' => $discountType,
            ];
        }
    }

    // custom fields (من جدول custom_fields) - إن وجد
    $customFields = [];
    try {
        $cf = DB::table('custom_fields')
            ->where('service_type', $this->viewPrefix . '_service')
            ->where('service_id', (int)$row->id)
            ->orderBy('ordering')
            ->get();
        foreach ($cf as $c) {
            $nm = json_decode((string)$c->name, true);
            $description = json_decode((string)$c->description, true);
            $options = $c->field_options ? (json_decode((string)$c->field_options, true) ?? (string)$c->field_options) : '';
            if (is_array($options) && !array_is_list($options)) {
                $options = $options['fallback'] ?? $options['en'] ?? $options;
            }
            $customFields[] = [
                'active' => (int)($c->active ?? 1),
                'required' => (int)($c->required ?? 0),
                'name' => is_array($nm) ? ($nm['fallback'] ?? $nm['en'] ?? '') : (string)$c->name,
                'input' => (string)($c->input ?? ''),
                'type' => (string)($c->field_type ?? 'text'),
                'description' => is_array($description)
                    ? ($description['fallback'] ?? $description['en'] ?? '')
                    : (string)($c->description ?? ''),
                'minimum' => (int)($c->minimum ?? 0),
                'maximum' => (int)($c->maximum ?? 0),
                'validation' => (string)($c->validation ?? ''),
                'options' => $options,
            ];
        }
    } catch (\Throwable $e) {
        $customFields = [];
    }

    $servicePayload = [
        'id' => (int)$row->id,
        'alias' => (string)($row->alias ?? ''),
        'group_id' => $row->group_id ? (int)$row->group_id : null,
        'type' => (string)($row->type ?? $this->viewPrefix),
        'source' => $row->source ?? null,
        'supplier_id' => $row->supplier_id ?? null,
        'remote_id' => $row->remote_id ?? null,
        'name' => (string)($name['fallback'] ?? $name['en'] ?? $row->name ?? ''),
        'time' => (string)($time['fallback'] ?? $time['en'] ?? $row->time ?? ''),
        'info' => (string)($info['fallback'] ?? $info['en'] ?? $row->info ?? ''),
        'cost' => (float)($row->cost ?? 0),
        'profit' => (float)($row->profit ?? 0),
        'profit_type' => (int)($row->profit_type ?? 1),
        'active' => (int)($row->active ?? 1),
        'allow_bulk' => (int)($row->allow_bulk ?? 0),
        'allow_duplicates' => (int)($row->allow_duplicates ?? 0),
        'reply_with_latest' => (int)($row->reply_with_latest ?? 0),
        'allow_report' => (int)($row->allow_report ?? 0),
        'allow_report_time' => (int)($row->allow_report_time ?? 0),
        'allow_cancel' => (int)($row->allow_cancel ?? 0),
        'allow_cancel_time' => (int)($row->allow_cancel_time ?? 0),
        'use_remote_cost' => (int)($row->use_remote_cost ?? 0),
        'use_remote_price' => (int)($row->use_remote_price ?? 0),
        'stop_on_api_change' => (int)($row->stop_on_api_change ?? 0),
        'needs_approval' => (int)($row->needs_approval ?? 0),
        'reply_expiration' => (int)($row->reply_expiration ?? 0),
        'main_field' => $main,
        'params' => $params,
        'group_prices' => $gpList,
        'custom_fields' => $customFields,
        'supplier_name' => method_exists($row, 'supplier') ? optional($row->supplier)->name : null,
        'api_name' => method_exists($row, 'api') ? optional($row->api)->name : null,
    ];

    return response()->json([
        'ok' => true,
        'service' => $servicePayload,
        'id' => $servicePayload['id'],
        'alias' => $servicePayload['alias'],
        'group_id' => $servicePayload['group_id'],
        'type' => $servicePayload['type'],
        'source' => $servicePayload['source'],
        'supplier_id' => $servicePayload['supplier_id'],
        'remote_id' => $servicePayload['remote_id'],
        'name_text' => $servicePayload['name'],
        'time_text' => $servicePayload['time'],
        'info_text' => $servicePayload['info'],
        'cost' => $servicePayload['cost'],
        'profit' => $servicePayload['profit'],
        'profit_type' => $servicePayload['profit_type'],
        'active' => $servicePayload['active'],
        'allow_bulk' => $servicePayload['allow_bulk'],
        'allow_duplicates' => $servicePayload['allow_duplicates'],
        'reply_with_latest' => $servicePayload['reply_with_latest'],
        'allow_report' => $servicePayload['allow_report'],
        'allow_report_time' => $servicePayload['allow_report_time'],
        'allow_cancel' => $servicePayload['allow_cancel'],
        'allow_cancel_time' => $servicePayload['allow_cancel_time'],
        'use_remote_cost' => $servicePayload['use_remote_cost'],
        'use_remote_price' => $servicePayload['use_remote_price'],
        'stop_on_api_change' => $servicePayload['stop_on_api_change'],
        'needs_approval' => $servicePayload['needs_approval'],
        'reply_expiration' => $servicePayload['reply_expiration'],
        'main_field' => $servicePayload['main_field'],
        'params' => $servicePayload['params'],
        'group_prices' => $gp,
        'custom_fields' => $customFields,
    ]);
}

    // =========================
    // Store (Merged)
    // =========================
    public function store(Request $request)
    {
        foreach (['remote_id', 'supplier_id', 'api_provider_id', 'api_service_remote_id'] as $k) {
            $v = $request->input($k);
            if ($v === 'undefined' || $v === '') $request->merge([$k => null]);
        }

        $v = $request->validate([
            'alias'        => 'nullable|string|max:255',
            'group_id'     => ['nullable', 'integer', Rule::exists('service_groups', 'id')
                ->where(fn ($query) => $query->whereRaw('LOWER(type) = ?', [$this->viewPrefix . '_service']))],
            'type'         => 'required|string|max:255',

            'source'       => 'nullable|integer|in:1,2',
            'remote_id'    => $this->viewPrefix === 'smm' ? 'nullable|regex:/^[a-zA-Z0-9_-]+$/|max:255' : 'nullable|integer|min:1|max:4294967295',
            'supplier_id'  => 'nullable|integer|min:1',

            'name'         => 'required|string',
            'time'         => 'nullable|string',
            'info'         => 'nullable|string',

            'main_field_type'    => 'required|string|max:50',
            'allowed_characters' => 'nullable|string|max:50',
            'min'                => 'nullable|integer|min:0|max:2147483647',
            'max'                => 'nullable|integer|min:0|max:2147483647',
            'minimum'            => 'nullable|integer|min:0|max:2147483647',
            'maximum'            => 'nullable|integer|min:0|max:2147483647',
            'main_field_label'   => 'nullable|string|max:255',

            'cost'        => 'nullable|numeric|min:0|max:99999999.9999',
            'profit'      => 'nullable|numeric|min:0|max:99999999.9999',
            'profit_type' => 'nullable|integer|in:1,2',

            'active'            => 'sometimes|boolean',
            'allow_bulk'        => 'sometimes|boolean',
            'allow_duplicates'  => 'sometimes|boolean',
            'reply_with_latest' => 'sometimes|boolean',
            'allow_report'      => 'sometimes|boolean',
            'allow_report_time' => 'nullable|integer|min:0|max:2147483647',
            'allow_cancel'      => 'sometimes|boolean',
            'allow_cancel_time' => 'nullable|integer|min:0|max:2147483647',
            'use_remote_cost'   => 'sometimes|boolean',
            'use_remote_price'  => 'sometimes|boolean',
            'stop_on_api_change'=> 'sometimes|boolean',
            'needs_approval'    => 'sometimes|boolean',
            'reply_expiration'  => 'nullable|integer|min:0|max:2147483647',
            'reject_on_missing_reply' => 'sometimes|boolean',
            'ordering'                => 'nullable|integer|min:0|max:2147483647',

            'api_provider_id'       => 'nullable|integer|min:1',
            'api_service_remote_id' => $this->viewPrefix === 'smm' ? 'nullable|regex:/^[a-zA-Z0-9_-]+$/|max:255' : 'nullable|integer|min:1|max:4294967295',

            'group_prices' => 'nullable|array',
            'group_prices.*' => 'array',
            'group_prices.*.price' => 'nullable|numeric|min:0',
            'group_prices.*.auto_price' => 'sometimes|boolean',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            'custom_fields_json' => 'nullable|string',
        ]);

        $v = $this->validatedServiceValues($v);
        $v['group_prices'] = $this->validatedGroupPrices($v['group_prices'] ?? [], $v);

        $alias = $v['alias'] ?? null;
        if (!$alias) $alias = Str::slug($v['name'] ?? '');
        if (!$alias) $alias = 'service-' . Str::random(8);

        $base = $alias;
        $i = 1;
        while (($this->model)::where('alias', $alias)->exists()) {
            $alias = $base . '-' . $i++;
        }
        $v['alias'] = $alias;

        $mainType = strtolower(trim((string)($v['main_field_type'] ?? 'serial')));

        $minVal = $v['min'] ?? $v['minimum'] ?? 0;
        $maxVal = $v['max'] ?? $v['maximum'] ?? 0;

        $minVal = is_null($minVal) ? 0 : (int)$minVal;
        $maxVal = is_null($maxVal) ? 0 : (int)$maxVal;

        $labelVal = trim((string)($v['main_field_label'] ?? ''));
        if ($labelVal === '') $labelVal = strtoupper($mainType);

        $name = ['en' => $v['name'], 'fallback' => $v['name']];
        $time = ['en' => ($v['time'] ?? ''), 'fallback' => ($v['time'] ?? '')];
        $info = ['en' => ($v['info'] ?? ''), 'fallback' => ($v['info'] ?? '')];

        $main = [
            'type'  => $mainType,
            'rules' => [
                'allowed' => $v['allowed_characters'] ?? 'any',
                'minimum' => $minVal,
                'maximum' => $maxVal,
            ],
            'label' => [
                'en' => $labelVal,
                'fallback' => $labelVal,
            ],
        ];

        // custom_fields_json (إن وجد) نحفظه داخل params + داخل جدول custom_fields عبر نفس منطق مشروعك
        $customFields = $this->validatedCustomFields($request) ?? [];

        $incomingParams = $request->input('params');
        if (is_string($incomingParams)) {
            $decodedIncomingParams = json_decode($incomingParams, true);
            $incomingParams = is_array($decodedIncomingParams) ? $decodedIncomingParams : [];
        }

        if (!is_array($incomingParams)) {
            $incomingParams = [];
        }

        $params = $incomingParams;
        $params['custom_fields'] = $customFields;

        return DB::transaction(function () use ($request, $v, $name, $time, $info, $main, $params, $customFields) {

            $service = ($this->model)::create([
                'alias' => $v['alias'],

                'group_id'    => $v['group_id'] ?? null,
                'type'        => $v['type'],

                'source'      => $v['source'] ?? null,
                'remote_id'   => $v['remote_id'] ?? null,
                'supplier_id' => $v['supplier_id'] ?? null,

                'name'       => $this->serviceJsonValue('name', $name),
                'time'       => $this->serviceJsonValue('time', $time),
                'info'       => $this->serviceJsonValue('info', $info),
                'main_field' => $this->serviceJsonValue('main_field', $main),
                'params'     => $this->serviceJsonValue('params', $params),

                'cost'        => $v['cost'] ?? 0,
                'profit'      => $v['profit'] ?? 0,
                'profit_type' => $v['profit_type'] ?? 1,

                'active'            => (int)$request->boolean('active'),
                'allow_bulk'        => (int)$request->boolean('allow_bulk'),
                'allow_duplicates'  => (int)$request->boolean('allow_duplicates'),
                'reply_with_latest' => (int)$request->boolean('reply_with_latest'),
                'allow_report'      => (int)$request->boolean('allow_report'),
                'allow_report_time' => (int)($v['allow_report_time'] ?? 0),
                'allow_cancel'      => (int)$request->boolean('allow_cancel'),
                'allow_cancel_time' => (int)($v['allow_cancel_time'] ?? 0),

                'use_remote_cost'    => (int)$request->boolean('use_remote_cost'),
                'use_remote_price'   => (int)$request->boolean('use_remote_price'),
                'stop_on_api_change' => (int)$request->boolean('stop_on_api_change'),
                'needs_approval'     => (int)$request->boolean('needs_approval'),

                'reply_expiration'        => (int)($v['reply_expiration'] ?? 0),
                'reject_on_missing_reply' => (int)$request->boolean('reject_on_missing_reply'),
                'ordering'                => (int)($v['ordering'] ?? 0),
            ]);

            $this->saveGroupPrices((int)$service->id, $v['group_prices'] ?? []);

            // ✅ حفظ custom fields بجدول custom_fields
            $this->saveCustomFieldsToTable((int)$service->id, $this->viewPrefix, $customFields);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => true,
                    'msg' => 'Created',
                    'id' => $service->id,
                ]);
            }

            return back()->with('ok', 'Created');
        });
    }

    // =========================
    // Update (Ajax friendly)
    // =========================
    public function update(Request $request, $service)
    {
        $row = ($this->model)::query()->findOrFail($service);

        foreach (['remote_id', 'supplier_id', 'api_provider_id', 'api_service_remote_id'] as $k) {
            $v = $request->input($k);
            if ($v === 'undefined' || $v === '') $request->merge([$k => null]);
        }

        $v = $request->validate([
            'alias'        => ['nullable', 'string', 'max:255', Rule::unique($this->table, 'alias')->ignore($row->getKey())],
            'group_id'     => ['nullable', 'integer', Rule::exists('service_groups', 'id')
                ->where(fn ($query) => $query->whereRaw('LOWER(type) = ?', [$this->viewPrefix . '_service']))],
            'type'         => 'required|string|max:255',

            'source'       => 'nullable|integer|in:1,2',
            'remote_id'    => $this->viewPrefix === 'smm' ? 'nullable|regex:/^[a-zA-Z0-9_-]+$/|max:255' : 'nullable|integer|min:1|max:4294967295',
            'supplier_id'  => 'nullable|integer|min:1',

            'name'         => 'required|string',
            'time'         => 'nullable|string',
            'info'         => 'nullable|string',

            'main_field_type'    => 'required|string|max:50',
            'allowed_characters' => 'nullable|string|max:50',
            'min'                => 'nullable|integer|min:0|max:2147483647',
            'max'                => 'nullable|integer|min:0|max:2147483647',
            'minimum'            => 'nullable|integer|min:0|max:2147483647',
            'maximum'            => 'nullable|integer|min:0|max:2147483647',
            'main_field_label'   => 'nullable|string|max:255',

            'cost'        => 'nullable|numeric|min:0|max:99999999.9999',
            'profit'      => 'nullable|numeric|min:0|max:99999999.9999',
            'profit_type' => 'nullable|integer|in:1,2',

            'active'            => 'sometimes|boolean',
            'allow_bulk'        => 'sometimes|boolean',
            'allow_duplicates'  => 'sometimes|boolean',
            'reply_with_latest' => 'sometimes|boolean',
            'allow_report'      => 'sometimes|boolean',
            'allow_report_time' => 'nullable|integer|min:0|max:2147483647',
            'allow_cancel'      => 'sometimes|boolean',
            'allow_cancel_time' => 'nullable|integer|min:0|max:2147483647',
            'use_remote_cost'   => 'sometimes|boolean',
            'use_remote_price'  => 'sometimes|boolean',
            'stop_on_api_change'=> 'sometimes|boolean',
            'needs_approval'    => 'sometimes|boolean',
            'reply_expiration'  => 'nullable|integer|min:0|max:2147483647',
            'reject_on_missing_reply' => 'sometimes|boolean',
            'ordering'                => 'nullable|integer|min:0|max:2147483647',

            'api_provider_id'       => 'nullable|integer|min:1',
            'api_service_remote_id' => $this->viewPrefix === 'smm' ? 'nullable|regex:/^[a-zA-Z0-9_-]+$/|max:255' : 'nullable|integer|min:1|max:4294967295',

            'group_prices' => 'nullable|array',
            'group_prices.*' => 'array',
            'group_prices.*.price' => 'nullable|numeric|min:0',
            'group_prices.*.auto_price' => 'sometimes|boolean',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            'custom_fields_json' => 'nullable|string',
        ]);

        $v = $this->validatedServiceValues($v, $row);
        $v['group_prices'] = $this->validatedGroupPrices($v['group_prices'] ?? [], $v);

        $customFields = $this->validatedCustomFields($request);

        $mainType = strtolower(trim((string)($v['main_field_type'] ?? 'serial')));

        $minVal = $v['min'] ?? $v['minimum'] ?? 0;
        $maxVal = $v['max'] ?? $v['maximum'] ?? 0;
        $minVal = is_null($minVal) ? 0 : (int)$minVal;
        $maxVal = is_null($maxVal) ? 0 : (int)$maxVal;

        $labelVal = trim((string)($v['main_field_label'] ?? ''));
        if ($labelVal === '') $labelVal = strtoupper($mainType);

        $name = ['en' => $v['name'], 'fallback' => $v['name']];
        $time = ['en' => ($v['time'] ?? ''), 'fallback' => ($v['time'] ?? '')];
        $info = ['en' => ($v['info'] ?? ''), 'fallback' => ($v['info'] ?? '')];

        $main = [
            'type'  => $mainType,
            'rules' => [
                'allowed' => $v['allowed_characters'] ?? 'any',
                'minimum' => $minVal,
                'maximum' => $maxVal,
            ],
            'label' => [
                'en' => $labelVal,
                'fallback' => $labelVal,
            ],
        ];

        $incomingParams = $request->input('params');
        if (is_string($incomingParams)) {
            $decodedIncomingParams = json_decode($incomingParams, true);
            $incomingParams = is_array($decodedIncomingParams) ? $decodedIncomingParams : [];
        }

        if (!is_array($incomingParams)) {
            $incomingParams = [];
        }

        $existingParams = $row->params;
        // Older controller writes may have encoded array-cast params twice.
        for ($depth = 0; $depth < 2 && is_string($existingParams); $depth++) {
            $existingParams = json_decode($existingParams, true);
        }
        $existingParams = is_array($existingParams) ? $existingParams : [];
        // Dedicated custom-field inputs control replacement; params alone must
        // not desynchronize the JSON copy from the custom_fields table.
        unset($incomingParams['custom_fields']);
        $params = array_replace($existingParams, $incomingParams);
        if ($customFields !== null) {
            $params['custom_fields'] = $customFields;
        }
        $paramsValue = !$request->exists('params') && $customFields === null
            ? $row->params
            : ($row->hasCast('params', ['array', 'json']) ? $params : $this->serviceJsonValue('params', $params));

        return DB::transaction(function () use ($request, $row, $v, $name, $time, $info, $main, $paramsValue, $customFields) {

            $row->update([
                'alias' => $v['alias'] ?? $row->alias,

                'group_id'    => $v['group_id'] ?? null,
                'type'        => $v['type'],

                'source'      => $v['source'] ?? null,
                'remote_id'   => $v['remote_id'] ?? null,
                'supplier_id' => $v['supplier_id'] ?? null,

                'name'       => $this->serviceJsonValue('name', $name),
                'time'       => $this->serviceJsonValue('time', $time),
                'info'       => $this->serviceJsonValue('info', $info),
                'main_field' => $this->serviceJsonValue('main_field', $main),
                'params'     => $paramsValue,

                'cost'        => $v['cost'] ?? 0,
                'profit'      => $v['profit'] ?? 0,
                'profit_type' => $v['profit_type'] ?? 1,

                'active'            => (int)$request->boolean('active'),
                'allow_bulk'        => (int)$request->boolean('allow_bulk'),
                'allow_duplicates'  => (int)$request->boolean('allow_duplicates'),
                'reply_with_latest' => (int)$request->boolean('reply_with_latest'),
                'allow_report'      => (int)$request->boolean('allow_report'),
                'allow_report_time' => (int)($v['allow_report_time'] ?? 0),
                'allow_cancel'      => (int)$request->boolean('allow_cancel'),
                'allow_cancel_time' => (int)($v['allow_cancel_time'] ?? 0),

                'use_remote_cost'    => (int)$request->boolean('use_remote_cost'),
                'use_remote_price'   => (int)$request->boolean('use_remote_price'),
                'stop_on_api_change' => (int)$request->boolean('stop_on_api_change'),
                'needs_approval'     => (int)$request->boolean('needs_approval'),

                'reply_expiration'        => (int)($v['reply_expiration'] ?? 0),
                'reject_on_missing_reply' => (int)$request->boolean('reject_on_missing_reply'),
                'ordering'                => (int)($v['ordering'] ?? 0),
            ]);

            $this->saveGroupPrices((int)$row->id, $v['group_prices'] ?? []);
            if ($customFields !== null) {
                $this->saveCustomFieldsToTable((int)$row->id, $this->viewPrefix, $customFields);
            }

            return response()->json(['ok' => true, 'msg' => 'Updated']);
        });
    }

      public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:active,inactive,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);

        $ids = collect($validated['ids'])->map(fn ($id) => (int)$id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return response()->json(['ok' => false, 'msg' => 'No services selected'], 422);
        }

        $affected = 0;
        DB::transaction(function () use ($validated, $ids, &$affected) {
            $query = ($this->model)::query()->whereIn('id', $ids->all());

            if ($validated['action'] === 'active') {
                $affected = (int)$query->update(['active' => 1]);
                return;
            }

            if ($validated['action'] === 'inactive') {
                $affected = (int)$query->update(['active' => 0]);
                return;
            }

            $rows = $query->get(['id']);
            foreach ($rows as $row) {
                if (class_exists(ServiceGroupPrice::class)) {
                    ServiceGroupPrice::query()
                        ->where('service_type', $this->viewPrefix)
                        ->where('service_id', (int)$row->id)
                        ->delete();
                }

                try {
                    DB::table('custom_fields')
                        ->where('service_type', $this->viewPrefix . '_service')
                        ->where('service_id', (int)$row->id)
                        ->delete();
                } catch (\Throwable $e) {
                }

                $row->delete();
                $affected++;
            }
        });

        return response()->json([
            'ok' => true,
            'msg' => 'Bulk action applied',
            'affected' => $affected,
        ]);
    }

    // =========================
    // Destroy (Ajax friendly)
    // =========================
    public function destroy(Request $request, $service)
    {
        $row = ($this->model)::query()->findOrFail($service);

        DB::transaction(function () use ($row) {
            // prices
            if (class_exists(ServiceGroupPrice::class)) {
                ServiceGroupPrice::query()
                    ->where('service_type', $this->viewPrefix)
                    ->where('service_id', (int)$row->id)
                    ->delete();
            }

            // custom_fields
            try {
                DB::table('custom_fields')
                    ->where('service_type', $this->viewPrefix . '_service')
                    ->where('service_id', (int)$row->id)
                    ->delete();
            } catch (\Throwable $e) {}

            $row->delete();
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('ok', 'Deleted');
    }

    // =========================
    // Helpers from your existing Base
    // =========================
    /** Let Eloquent encode cast attributes; encode only raw JSON columns. */
    private function serviceJsonValue(string $attribute, array $value): array|string
    {
        $model = new $this->model;
        return $model->hasCast($attribute)
            ? $value
            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function validatedGroupPrices(array $groupPrices, array $servicePricing): array
    {
        if ($groupPrices === []) {
            return [];
        }

        $existingGroups = DB::table('groups')->whereIn('id', array_keys($groupPrices))
            ->pluck('id')->map(fn ($id) => (string)$id)->all();
        $errors = [];
        foreach ($groupPrices as $groupId => $priceRow) {
            $key = "group_prices.{$groupId}";
            if (!ctype_digit((string)$groupId) || (int)$groupId < 1
                || !in_array((string)$groupId, $existingGroups, true)) {
                $errors[$key . '.group_id'] = 'Select an existing customer group.';
            }
            $price = (float)($priceRow['price'] ?? 0);
            if (!empty($priceRow['auto_price'])) {
                // The submitted preview may be stale or tampered with.
                $price = ServiceGroupPrice::servicePrice($servicePricing);
                $groupPrices[$groupId]['price'] = $price;
                if (!is_finite($price) || $price < 0) {
                    $errors[$key . '.price'] = 'Automatic group price must be a nonnegative number.';
                }
            }
            $discount = (float)($priceRow['discount'] ?? 0);
            $type = (int)($priceRow['discount_type'] ?? 1);
            if ($type === 2 && $discount > 100) {
                $errors[$key . '.discount'] = 'Percentage discount cannot exceed 100.';
            } elseif ($type === 1 && $discount > $price) {
                $errors[$key . '.discount'] = 'Fixed discount cannot exceed the group price.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        return $groupPrices;
    }

    protected function saveGroupPrices(int $serviceId, array $groupPrices): void
    {
        if (!class_exists(ServiceGroupPrice::class)) return;

        foreach ($groupPrices as $groupId => $row) {
            ServiceGroupPrice::updateOrCreate(
                [
                    'service_id'   => $serviceId,
                    'service_type' => $this->viewPrefix,
                    'group_id'     => (int)$groupId,
                ],
                [
                    'price'         => (float)($row['price'] ?? 0),
                    'auto_price'    => (bool)($row['auto_price'] ?? false),
                    'discount'      => (float)($row['discount'] ?? 0),
                    'discount_type' => (int)($row['discount_type'] ?? 1),
                ]
            );
        }
    }

    /** Validate the effective values, including legacy field aliases, before writing. */
    private function validatedServiceValues(array $values, ?Model $existing = null): array
    {
        $min = (int) ($values['min'] ?? $values['minimum'] ?? 0);
        $max = (int) ($values['max'] ?? $values['maximum'] ?? 0);
        if ($max > 0 && $max < $min) {
            throw ValidationException::withMessages(['maximum' => 'Maximum must be zero (unlimited) or at least the minimum.']);
        }
        $sell = ServiceGroupPrice::servicePrice($values);
        if (!is_finite($sell) || $sell > 99999999.9999) {
            throw ValidationException::withMessages(['profit' => 'The resulting service price is too large.']);
        }
        $values['source'] = (int) ($values['source'] ?? 1);
        if ($values['source'] === 1) {
            $values['supplier_id'] = null;
            $values['remote_id'] = null;
            return $values;
        }
        $providerId = $values['api_provider_id'] ?? $values['supplier_id'] ?? null;
        $remoteId = $values['api_service_remote_id'] ?? $values['remote_id'] ?? null;
        if (!$providerId || !ApiProvider::query()->whereKey($providerId)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'Select an existing API provider.']);
        }
        if (!$remoteId || !DB::table('remote_' . $this->table)
            ->where('api_provider_id', $providerId)->where('remote_id', (string) $remoteId)->exists()) {
            throw ValidationException::withMessages(['remote_id' => 'Select a service from the selected provider and service kind.']);
        }
        $duplicate = ($this->model)::query()->where('supplier_id', $providerId)
            ->where('remote_id', $remoteId)->where('type', $values['type']);
        if ($existing) {
            $duplicate->whereKeyNot($existing->getKey());
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['remote_id' => 'This provider service is already linked to a local service of this type.']);
        }
        $values['supplier_id'] = (int) $providerId;
        $values['remote_id'] = $remoteId;
        return $values;
    }

    /** Null means omitted; an explicit empty list means remove all fields. */
    private function validatedCustomFields(Request $request): ?array
    {
        $inputs = [];
        foreach (['custom_fields', 'custom_fields_json'] as $key) {
            if (!$request->exists($key)) {
                continue;
            }
            $value = $request->input($key);
            if (is_string($value)) {
                $decoded = json_decode($value);
                if (!is_array($decoded)) {
                    throw ValidationException::withMessages([$key => 'Provide a valid JSON list of custom fields.']);
                }
                $value = json_decode($value, true);
            }
            if (!is_array($value) || !array_is_list($value)) {
                throw ValidationException::withMessages([$key => 'Provide a list of custom fields; use [] to remove all fields.']);
            }
            foreach ($value as $field) {
                if (!is_array($field) || !is_string($field['name'] ?? null) || trim($field['name']) === '') {
                    throw ValidationException::withMessages([$key => 'Every custom field must have a name.']);
                }
                foreach (['input_name', 'input', 'field_type', 'type', 'description', 'validation'] as $attribute) {
                    if (isset($field[$attribute]) && !is_scalar($field[$attribute])) {
                        throw ValidationException::withMessages([$key => 'Custom field attributes must be scalar values.']);
                    }
                }
                foreach (['min', 'minimum', 'max', 'maximum'] as $attribute) {
                    if (isset($field[$attribute]) && $field[$attribute] !== ''
                        && (filter_var($field[$attribute], FILTER_VALIDATE_INT) === false
                            || $field[$attribute] < 0 || $field[$attribute] > 2147483647)) {
                        throw ValidationException::withMessages([$key => 'Custom field limits must be nonnegative integers.']);
                    }
                }
                $min = (int) ($field['min'] ?? $field['minimum'] ?? 0);
                $max = (int) ($field['max'] ?? $field['maximum'] ?? 0);
                if ($max > 0 && $max < $min) {
                    throw ValidationException::withMessages([$key => 'Custom field maximum must be zero or at least the minimum.']);
                }
                foreach (['active', 'required'] as $attribute) {
                    if (isset($field[$attribute]) && !in_array($field[$attribute], [true, false, 0, 1, '0', '1'], true)) {
                        throw ValidationException::withMessages([$key => 'Custom field flags must be boolean values.']);
                    }
                }
                foreach (['options', 'field_options'] as $attribute) {
                    if (isset($field[$attribute]) && !is_scalar($field[$attribute])
                        && !(is_array($field[$attribute]) && count(array_filter($field[$attribute], 'is_scalar')) === count($field[$attribute]))) {
                        throw ValidationException::withMessages([$key => 'Custom field options must be text or a list of scalar values.']);
                    }
                }
            }
            $inputs[$key] = $value;
        }
        if ($inputs === []) {
            return null;
        }
        return $this->normalizeCustomFields($inputs['custom_fields'] ?? $inputs['custom_fields_json'], null);
    }

    protected function normalizeCustomFields($customFieldsRaw, ?string $customFieldsJson): array
    {
        $customFields = [];

        if (is_array($customFieldsRaw)) $customFields = $customFieldsRaw;

        if (is_string($customFieldsRaw) && trim($customFieldsRaw) !== '') {
            $decoded = json_decode($customFieldsRaw, true);
            if (is_array($decoded)) $customFields = $decoded;
        }

        if (!is_array($customFields) || empty($customFields)) {
            if (is_string($customFieldsJson) && trim($customFieldsJson) !== '') {
                $decoded = json_decode($customFieldsJson, true);
                if (is_array($decoded)) $customFields = $decoded;
            }
        }

        if (!is_array($customFields)) $customFields = [];

        $out = [];
        foreach ($customFields as $f) {
            if (!is_array($f)) continue;

            $inputName = (string)($f['input_name'] ?? $f['input'] ?? '');
            $fieldType = (string)($f['field_type'] ?? $f['type'] ?? 'text');

            $min = $f['min'] ?? $f['minimum'] ?? 0;
            $max = $f['max'] ?? $f['maximum'] ?? 0;

            $options = $f['field_options'] ?? $f['options'] ?? '';
            if (is_array($options)) $options = implode(',', $options);
            $options = (string)$options;

            $out[] = [
                'active'      => !empty($f['active']) ? 1 : 0,
                'name'        => (string)($f['name'] ?? ''),
                'input_name'  => $inputName,
                'field_type'  => $fieldType,
                'description' => (string)($f['description'] ?? ''),
                'min'         => (int)$min,
                'max'         => (int)$max,
                'validation'  => (string)($f['validation'] ?? 'none'),
                'required'    => !empty($f['required']) ? 1 : 0,
                'options'     => $options,
            ];
        }

        return $out;
    }

    protected function saveCustomFieldsToTable(int $serviceId, string $serviceType, array $fields): void
    {
        try {
            DB::table('custom_fields')->limit(1)->get();
        } catch (\Throwable $e) {
            return;
        }

        DB::table('custom_fields')
            ->where('service_type', $serviceType . '_service')
            ->where('service_id', $serviceId)
            ->delete();

        $now = now();

        foreach ($fields as $i => $f) {
            $name = trim((string)($f['name'] ?? ''));
            if ($name === '') continue;

            $desc = trim((string)($f['description'] ?? ''));
            $opts = trim((string)($f['options'] ?? ''));

            DB::table('custom_fields')->insert([
                'service_type'   => $serviceType . '_service',
                'service_id'     => $serviceId,

                'name'           => json_encode(['en' => $name, 'fallback' => $name], JSON_UNESCAPED_UNICODE),
                'input'          => (string)($f['input_name'] ?? ''),
                'field_type'     => (string)($f['field_type'] ?? 'text'),
                'field_options'  => json_encode(['en' => $opts, 'fallback' => $opts], JSON_UNESCAPED_UNICODE),
                'description'    => json_encode(['en' => $desc, 'fallback' => $desc], JSON_UNESCAPED_UNICODE),

                'validation'     => (string)($f['validation'] ?? ''),
                'minimum'        => (int)($f['min'] ?? 0),
                'maximum'        => (int)($f['max'] ?? 0),
                'required'       => (int)($f['required'] ?? 0),
                'active'         => (int)($f['active'] ?? 1),
                'ordering'       => (int)($i + 1),

                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }
}
