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

    // =========================
    // JSON for Edit modal
    // =========================
      public function showJson($service)
{
    $row = ($this->model)::query()->findOrFail($service);

    // name/time/info/main_field/params قد تكون JSON مخزنة كنص
    $decode = function ($v) {
        if (is_array($v)) return $v;
        $s = trim((string)$v);
        if ($s === '') return [];
        $j = json_decode($s, true);
        return is_array($j) ? $j : [];
    };

    $name = $decode($row->name);
    $time = $decode($row->time);
    $info = $decode($row->info);
    $main = $decode($row->main_field);
    $params = $decode($row->params);

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
            $price = (float) $g->price;
            $discount = (float) $g->discount;
            $discountType = (int) $g->discount_type;

            $gp[$groupId] = [
                'price' => $price,
                'discount' => $discount,
                'discount_type' => $discountType,
            ];

            $gpList[] = [
                'group_id' => $groupId,
                'price' => $price,
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
            $customFields[] = [
                'active' => (int)($c->active ?? 1),
                'required' => (int)($c->required ?? 0),
                'name' => is_array($nm) ? ($nm['fallback'] ?? $nm['en'] ?? '') : (string)$c->name,
                'input' => (string)($c->input ?? ''),
                'type' => (string)($c->field_type ?? 'text'),
                'description' => (string)($c->description ?? ''),
                'minimum' => (int)($c->minimum ?? 0),
                'maximum' => (int)($c->maximum ?? 0),
                'validation' => (string)($c->validation ?? ''),
                'options' => $c->field_options ? (json_decode((string)$c->field_options, true) ?: (string)$c->field_options) : '',
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
            'group_id'     => 'nullable|integer|exists:service_groups,id',
            'type'         => 'required|string|max:255',

            'source'       => 'nullable|integer',
            'remote_id'    => 'nullable',
            'supplier_id'  => 'nullable',

            'name'         => 'required|string',
            'time'         => 'nullable|string',
            'info'         => 'nullable|string',

            'main_field_type'    => 'required|string|max:50',
            'allowed_characters' => 'nullable|string|max:50',
            'min'                => 'nullable|integer',
            'max'                => 'nullable|integer',
            'minimum'            => 'nullable|integer',
            'maximum'            => 'nullable|integer',
            'main_field_label'   => 'nullable|string|max:255',

            'cost'        => 'nullable|numeric',
            'profit'      => 'nullable|numeric',
            'profit_type' => 'nullable|integer',

            'active'            => 'sometimes|boolean',
            'allow_bulk'        => 'sometimes|boolean',
            'allow_duplicates'  => 'sometimes|boolean',
            'reply_with_latest' => 'sometimes|boolean',
            'allow_report'      => 'sometimes|boolean',
            'allow_report_time' => 'nullable|integer',
            'allow_cancel'      => 'sometimes|boolean',
            'allow_cancel_time' => 'nullable|integer',
            'use_remote_cost'   => 'sometimes|boolean',
            'use_remote_price'  => 'sometimes|boolean',
            'stop_on_api_change'=> 'sometimes|boolean',
            'needs_approval'    => 'sometimes|boolean',
            'reply_expiration'  => 'nullable|integer',
            'reject_on_missing_reply' => 'sometimes|boolean',
            'ordering'                => 'nullable|integer',

            'api_provider_id'       => 'nullable|integer',
            'api_service_remote_id' => 'nullable|integer',

            'group_prices' => 'nullable|array',
            'group_prices.*.price' => 'nullable|numeric|min:0',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            'custom_fields_json' => 'nullable|string',
        ]);

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
        $customFields = $this->normalizeCustomFields(
            $request->input('custom_fields'),
            $request->input('custom_fields_json')
        );

        $params = [
            'custom_fields' => $customFields,
        ];

        if (($v['source'] ?? null) == 2 && !empty($v['api_provider_id']) && !empty($v['api_service_remote_id'])) {
            $v['supplier_id'] = (int)$v['api_provider_id'];
            $v['remote_id']   = (int)$v['api_service_remote_id'];
        }

        return DB::transaction(function () use ($request, $v, $name, $time, $info, $main, $params, $customFields) {

            $service = ($this->model)::create([
                'alias' => $v['alias'],

                'group_id'    => $v['group_id'] ?? null,
                'type'        => $v['type'],

                'source'      => $v['source'] ?? null,
                'remote_id'   => $v['remote_id'] ?? null,
                'supplier_id' => $v['supplier_id'] ?? null,

                'name'       => json_encode($name, JSON_UNESCAPED_UNICODE),
                'time'       => json_encode($time, JSON_UNESCAPED_UNICODE),
                'info'       => json_encode($info, JSON_UNESCAPED_UNICODE),
                'main_field' => json_encode($main, JSON_UNESCAPED_UNICODE),
                'params'     => json_encode($params, JSON_UNESCAPED_UNICODE),

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
            'alias'        => 'nullable|string|max:255',
            'group_id'     => 'nullable|integer|exists:service_groups,id',
            'type'         => 'required|string|max:255',

            'source'       => 'nullable|integer',
            'remote_id'    => 'nullable',
            'supplier_id'  => 'nullable',

            'name'         => 'required|string',
            'time'         => 'nullable|string',
            'info'         => 'nullable|string',

            'main_field_type'    => 'required|string|max:50',
            'allowed_characters' => 'nullable|string|max:50',
            'min'                => 'nullable|integer',
            'max'                => 'nullable|integer',
            'minimum'            => 'nullable|integer',
            'maximum'            => 'nullable|integer',
            'main_field_label'   => 'nullable|string|max:255',

            'cost'        => 'nullable|numeric',
            'profit'      => 'nullable|numeric',
            'profit_type' => 'nullable|integer',

            'active'            => 'sometimes|boolean',
            'allow_bulk'        => 'sometimes|boolean',
            'allow_duplicates'  => 'sometimes|boolean',
            'reply_with_latest' => 'sometimes|boolean',
            'allow_report'      => 'sometimes|boolean',
            'allow_report_time' => 'nullable|integer',
            'allow_cancel'      => 'sometimes|boolean',
            'allow_cancel_time' => 'nullable|integer',
            'use_remote_cost'   => 'sometimes|boolean',
            'use_remote_price'  => 'sometimes|boolean',
            'stop_on_api_change'=> 'sometimes|boolean',
            'needs_approval'    => 'sometimes|boolean',
            'reply_expiration'  => 'nullable|integer',
            'reject_on_missing_reply' => 'sometimes|boolean',
            'ordering'                => 'nullable|integer',

            'api_provider_id'       => 'nullable|integer',
            'api_service_remote_id' => 'nullable|integer',

            'group_prices' => 'nullable|array',
            'group_prices.*.price' => 'nullable|numeric|min:0',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            'custom_fields_json' => 'nullable|string',
        ]);

        $customFields = $this->normalizeCustomFields(
            $request->input('custom_fields'),
            $request->input('custom_fields_json')
        );

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

        $params = [
            'custom_fields' => $customFields,
        ];

        if (($v['source'] ?? null) == 2 && !empty($v['api_provider_id']) && !empty($v['api_service_remote_id'])) {
            $v['supplier_id'] = (int)$v['api_provider_id'];
            $v['remote_id']   = (int)$v['api_service_remote_id'];
        }

        return DB::transaction(function () use ($request, $row, $v, $name, $time, $info, $main, $params, $customFields) {

            $row->update([
                'alias' => $v['alias'] ?? $row->alias,

                'group_id'    => $v['group_id'] ?? null,
                'type'        => $v['type'],

                'source'      => $v['source'] ?? null,
                'remote_id'   => $v['remote_id'] ?? null,
                'supplier_id' => $v['supplier_id'] ?? null,

                'name'       => json_encode($name, JSON_UNESCAPED_UNICODE),
                'time'       => json_encode($time, JSON_UNESCAPED_UNICODE),
                'info'       => json_encode($info, JSON_UNESCAPED_UNICODE),
                'main_field' => json_encode($main, JSON_UNESCAPED_UNICODE),
                'params'     => json_encode($params, JSON_UNESCAPED_UNICODE),

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
            $this->saveCustomFieldsToTable((int)$row->id, $this->viewPrefix, $customFields);

            return response()->json(['ok' => true, 'msg' => 'Updated']);
        });
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
                    'discount'      => (float)($row['discount'] ?? 0),
                    'discount_type' => (int)($row['discount_type'] ?? 1),
                ]
            );
        }
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