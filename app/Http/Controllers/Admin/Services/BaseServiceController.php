<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ServiceGroupPrice;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class BaseServiceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $viewPrefix;   // imei|server|file
    protected string $routePrefix;  // admin.services.server ...
    protected string $table;        // server_services ...

    public function index(Request $r)
    {
        $q = ($this->model)::query();

        // ✅ فلتر مزود API (supplier_id)
        if ($r->filled('api_provider_id')) {
            $pid = (int)$r->input('api_provider_id');
            if ($pid > 0) $q->where('supplier_id', $pid);
        }

        if ($r->filled('q')) {
            $term = trim((string)$r->q);
            $q->where(function ($qq) use ($term) {
                $qq->where('alias', 'like', "%$term%")
                   ->orWhere('name', 'like', "%$term%");
            });
        }

        // ✅ ترتيب: ordering ثم id (لو موجود ordering) وإلا id فقط
        try {
            if (Schema::hasColumn($this->table, 'ordering')) {
                $q->orderBy('ordering', 'asc');
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $q->orderBy('id', 'asc');

        $rows = $q->paginate(20)->withQueryString();

        // ✅ قائمة مزودي API لفلتر الـ select
        $apis = ApiProvider::query()->orderBy('name')->get(['id','name']);

        return view("admin.services.{$this->viewPrefix}.index", [
            'rows'        => $rows,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
            'apis'        => $apis,
        ]);
    }

    /**
     * ✅ JSON للـ Edit modal
     * يرجع:
     * - service fields الأساسية
     * - decoded main_field / params
     * - group_prices من جدول service_group_prices
     */
    public function showJson($service)
    {
        // route model binding غالبًا يمرر Model، لكن احتياط:
        if (!$service instanceof Model) {
            $service = ($this->model)::query()->findOrFail((int)$service);
        }

        $decode = function ($v) {
            if (is_array($v)) return $v;
            if (!is_string($v)) return null;
            $t = trim($v);
            if ($t === '' || $t === 'null' || $t === 'undefined') return null;
            $j = json_decode($t, true);
            return is_array($j) ? $j : null;
        };

        $nameArr = $decode($service->name) ?: null;
        $timeArr = $decode($service->time) ?: null;
        $infoArr = $decode($service->info) ?: null;

        $mainArr = $decode($service->main_field) ?: null;
        $paramsArr = $decode($service->params) ?: null;

        $groupPrices = [];
        try {
            if (class_exists(ServiceGroupPrice::class)) {
                $groupPrices = ServiceGroupPrice::query()
                    ->where('service_type', $this->viewPrefix)
                    ->where('service_id', (int)$service->id)
                    ->get(['group_id','price','discount','discount_type'])
                    ->map(fn($r) => [
                        'group_id' => (int)$r->group_id,
                        'price' => (float)$r->price,
                        'discount' => (float)$r->discount,
                        'discount_type' => (int)$r->discount_type,
                    ])->values()->all();
            }
        } catch (\Throwable $e) {
            $groupPrices = [];
        }

        return response()->json([
            'ok' => true,
            'service' => [
                'id' => (int)$service->id,
                'alias' => (string)($service->alias ?? ''),
                'group_id' => $service->group_id ? (int)$service->group_id : null,
                'type' => (string)($service->type ?? $this->viewPrefix),
                'source' => (int)($service->source ?? 1),
                'supplier_id' => $service->supplier_id ? (int)$service->supplier_id : null,
                'remote_id' => $service->remote_id !== null ? (string)$service->remote_id : null,

                'cost' => (float)($service->cost ?? 0),
                'profit' => (float)($service->profit ?? 0),
                'profit_type' => (int)($service->profit_type ?? 1),
                'active' => (int)($service->active ?? 0),

                'name' => $nameArr ?: (string)($service->name ?? ''),
                'time' => $timeArr ?: (string)($service->time ?? ''),
                'info' => $infoArr ?: (string)($service->info ?? ''),

                'main_field' => $mainArr,
                'params' => $paramsArr,
                'group_prices' => $groupPrices,
            ],
        ]);
    }

    /**
     * ✅ MODAL CREATE (CLONE)
     */
    public function modalCreate(Request $r)
    {
        $providerId = $r->input('provider_id');
        $remoteId   = $r->input('remote_id');

        $data = [
            'supplier_id' => $providerId,
            'remote_id'   => $remoteId,
            'name'        => $r->input('name'),
            'cost'        => $r->input('credit'),
            'time'        => $r->input('time'),
            'group_name'  => $r->input('group'),
            'type'        => $this->viewPrefix,

            'remote_additional_fields' => [],
            'remote_additional_fields_json' => '[]',
        ];

        if (!empty($providerId) && $remoteId !== null && $remoteId !== '') {
            $row = null;

            if ($this->viewPrefix === 'server') {
                $row = RemoteServerService::query()
                    ->where('api_provider_id', (int)$providerId)
                    ->where('remote_id', (string)$remoteId)
                    ->first();
            } elseif ($this->viewPrefix === 'imei') {
                $row = RemoteImeiService::query()
                    ->where('api_provider_id', (int)$providerId)
                    ->where('remote_id', (string)$remoteId)
                    ->first();
            } elseif ($this->viewPrefix === 'file') {
                $row = RemoteFileService::query()
                    ->where('api_provider_id', (int)$providerId)
                    ->where('remote_id', (string)$remoteId)
                    ->first();
            }

            if ($row) {
                $raw = $row->additional_fields ?? $row->additional_data ?? null;

                $fields = [];
                if (is_array($raw)) {
                    $fields = $raw;
                } elseif (is_string($raw) && trim($raw) !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) $fields = $decoded;
                }

                if (!is_array($fields)) $fields = [];
                $data['remote_additional_fields'] = $fields;
                $data['remote_additional_fields_json'] = json_encode($fields, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
        }

        return view("admin.services.{$this->viewPrefix}._modal_create", compact('data'));
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
        if ($labelVal === '') {
            $labelVal = strtoupper($mainType);
        }

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
}