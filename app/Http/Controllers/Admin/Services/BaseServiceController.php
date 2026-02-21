<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
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
    protected string $routePrefix;  // admin.services.imei / admin.services.server / admin.services.file
    protected string $table;        // imei_services / server_services / file_services

    public function index(Request $r)
    {
        $q = ($this->model)::query();

        // ✅ Eager load only if relations exist
        $with = [];
        foreach (['group', 'supplier', 'api'] as $rel) {
            if (method_exists(($this->model), $rel)) $with[] = $rel;
        }
        if (!empty($with)) $q->with($with);

        // ✅ Search
        if ($r->filled('q')) {
            $term = trim((string) $r->q);
            $q->where(function ($qq) use ($term) {
                $qq->where('alias', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });
        }

        // ✅ Filter by API provider (supplier_id)
        if ($r->filled('api_provider_id')) {
            $pid = (int)$r->api_provider_id;
            if ($pid > 0) {
                $q->where('supplier_id', $pid);
            }
        }

        // ✅ ترتيب: ordering ثم id تصاعدي (طلبك)
        $q->orderBy('ordering', 'asc')->orderBy('id', 'asc');

        $rows = $q->paginate(20)->withQueryString();

        // ✅ APIs dropdown: لازم يكون id + name (عشان لا يصير Attempt to read property "id" on int)
        $apis = ApiProvider::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return view("admin.services.{$this->viewPrefix}.index", [
            'rows'        => $rows,
            'apis'        => $apis,
            'routePrefix' => $this->routePrefix,
            'viewPrefix'  => $this->viewPrefix,
        ]);
    }

    /**
     * ✅ JSON endpoint for Edit modal
     * GET .../{service}/json
     */
    public function showJson($service)
    {
        $s = ($service instanceof Model)
            ? $service
            : ($this->model)::query()->findOrFail($service);

        // group prices
        $gp = [];
        if (class_exists(ServiceGroupPrice::class)) {
            $gp = ServiceGroupPrice::query()
                ->where('service_type', $this->viewPrefix)
                ->where('service_id', $s->id)
                ->get()
                ->mapWithKeys(function ($row) {
                    return [
                        (int)$row->group_id => [
                            'price' => (float)$row->price,
                            'discount' => (float)$row->discount,
                            'discount_type' => (int)$row->discount_type,
                        ]
                    ];
                })
                ->toArray();
        }

        // params json
        $params = [];
        $rawParams = $s->params ?? null;
        if (is_array($rawParams)) $params = $rawParams;
        if (is_string($rawParams) && trim($rawParams) !== '') {
            $decoded = json_decode($rawParams, true);
            if (is_array($decoded)) $params = $decoded;
        }

        $customFields = $params['custom_fields'] ?? [];

        return response()->json([
            'ok' => true,
            'service' => [
                'id' => $s->id,
                'alias' => $s->alias,
                'type' => $s->type,
                'group_id' => $s->group_id,
                'source' => $s->source,
                'supplier_id' => $s->supplier_id,
                'remote_id' => $s->remote_id,

                'name' => $this->decodeJsonField($s->name),
                'time' => $this->decodeJsonField($s->time),
                'info' => $this->decodeJsonField($s->info),
                'main_field' => $this->decodeJsonField($s->main_field),

                'cost' => (float)($s->cost ?? 0),
                'profit' => (float)($s->profit ?? 0),
                'profit_type' => (int)($s->profit_type ?? 1),

                'active' => (int)($s->active ?? 0),
                'allow_bulk' => (int)($s->allow_bulk ?? 0),
                'allow_duplicates' => (int)($s->allow_duplicates ?? 0),
                'reply_with_latest' => (int)($s->reply_with_latest ?? 0),
                'allow_report' => (int)($s->allow_report ?? 0),
                'allow_report_time' => (int)($s->allow_report_time ?? 0),
                'allow_cancel' => (int)($s->allow_cancel ?? 0),
                'allow_cancel_time' => (int)($s->allow_cancel_time ?? 0),
                'use_remote_cost' => (int)($s->use_remote_cost ?? 0),
                'use_remote_price' => (int)($s->use_remote_price ?? 0),
                'stop_on_api_change' => (int)($s->stop_on_api_change ?? 0),
                'needs_approval' => (int)($s->needs_approval ?? 0),
                'reply_expiration' => (int)($s->reply_expiration ?? 0),
                'reject_on_missing_reply' => (int)($s->reject_on_missing_reply ?? 0),
                'ordering' => (int)($s->ordering ?? 0),

                // meta (لو موجودة بالـ params)
                'params' => $params,
            ],
            'group_prices' => $gp,
            'custom_fields' => $customFields,
            'custom_fields_json' => json_encode($customFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    protected function decodeJsonField($val)
    {
        if (is_array($val)) return $val;
        if (!is_string($val)) return $val;

        $t = trim($val);
        if ($t === '') return $val;
        if ($t[0] !== '{' && $t[0] !== '[') return $val;

        $j = json_decode($t, true);
        return is_array($j) ? $j : $val;
    }

    // ===================== MODAL CREATE (clone uses same template) =====================
    public function modalCreate(Request $r)
    {
        // ملاحظة: Create Service لازم يبقى نفس الطريقة (من template id=serviceCreateTpl)
        // هذا الميثود موجود لو احتجته في مكان آخر، لكن المودال الحالي يعتمد template مباشرة.
        $data = [
            'supplier_id' => $r->input('provider_id'),
            'remote_id'   => $r->input('remote_id'),
            'name'        => $r->input('name'),
            'cost'        => $r->input('credit'),
            'time'        => $r->input('time'),
            'group_name'  => $r->input('group'),
            'type'        => $this->viewPrefix,
        ];

        return view("admin.services.{$this->viewPrefix}._modal_create", compact('data'));
    }

    // ===================== STORE =====================
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

            // meta (يُستخدم في IMEI وأيضاً نخليه للجميع)
            'meta_keywords'     => 'nullable|string',
            'meta_description'  => 'nullable|string',
            'meta_after_head'   => 'nullable|string',
            'meta_before_head'  => 'nullable|string',
            'meta_after_body'   => 'nullable|string',
            'meta_before_body'  => 'nullable|string',

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

        $customFields = $this->normalizeCustomFields(
            $request->input('custom_fields'),
            $request->input('custom_fields_json')
        );

        $params = [
            'meta_keywords'           => $v['meta_keywords'] ?? '',
            'meta_description'        => $v['meta_description'] ?? '',
            'after_head_tag_opening'  => $v['meta_after_head'] ?? '',
            'before_head_tag_closing' => $v['meta_before_head'] ?? '',
            'after_body_tag_opening'  => $v['meta_after_body'] ?? '',
            'before_body_tag_closing' => $v['meta_before_body'] ?? '',
            'custom_fields'           => $customFields,
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

            // ✅ حفظ custom_fields في جدول custom_fields (لو موجود)
            $this->saveCustomFieldsToTable((int)$service->id, $this->viewPrefix, $customFields);

            return response()->json([
                'ok' => true,
                'msg' => 'Created',
                'id' => $service->id,
            ]);
        });
    }

    // ===================== UPDATE =====================
    public function update(Request $request, $service)
    {
        $s = ($service instanceof Model)
            ? $service
            : ($this->model)::query()->findOrFail($service);

        $v = $request->validate([
            'alias'        => 'nullable|string|max:255',
            'group_id'     => 'nullable|integer|exists:service_groups,id',
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

            'meta_keywords'     => 'nullable|string',
            'meta_description'  => 'nullable|string',
            'meta_after_head'   => 'nullable|string',
            'meta_before_head'  => 'nullable|string',
            'meta_after_body'   => 'nullable|string',
            'meta_before_body'  => 'nullable|string',

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

            'group_prices' => 'nullable|array',
            'group_prices.*.price' => 'nullable|numeric|min:0',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            'custom_fields_json' => 'nullable|string',
        ]);

        $mainType = strtolower(trim((string)($v['main_field_type'] ?? 'serial')));
        $minVal = $v['min'] ?? $v['minimum'] ?? 0;
        $maxVal = $v['max'] ?? $v['maximum'] ?? 0;

        $labelVal = trim((string)($v['main_field_label'] ?? ''));
        if ($labelVal === '') $labelVal = strtoupper($mainType);

        $name = ['en' => $v['name'], 'fallback' => $v['name']];
        $time = ['en' => ($v['time'] ?? ''), 'fallback' => ($v['time'] ?? '')];
        $info = ['en' => ($v['info'] ?? ''), 'fallback' => ($v['info'] ?? '')];

        $main = [
            'type'  => $mainType,
            'rules' => [
                'allowed' => $v['allowed_characters'] ?? 'any',
                'minimum' => (int)$minVal,
                'maximum' => (int)$maxVal,
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

        // preserve old params then merge meta/custom_fields
        $params = [];
        $rawParams = $s->params ?? null;
        if (is_array($rawParams)) $params = $rawParams;
        if (is_string($rawParams) && trim($rawParams) !== '') {
            $decoded = json_decode($rawParams, true);
            if (is_array($decoded)) $params = $decoded;
        }

        $params['meta_keywords']           = $v['meta_keywords'] ?? ($params['meta_keywords'] ?? '');
        $params['meta_description']        = $v['meta_description'] ?? ($params['meta_description'] ?? '');
        $params['after_head_tag_opening']  = $v['meta_after_head'] ?? ($params['after_head_tag_opening'] ?? '');
        $params['before_head_tag_closing'] = $v['meta_before_head'] ?? ($params['before_head_tag_closing'] ?? '');
        $params['after_body_tag_opening']  = $v['meta_after_body'] ?? ($params['after_body_tag_opening'] ?? '');
        $params['before_body_tag_closing'] = $v['meta_before_body'] ?? ($params['before_body_tag_closing'] ?? '');
        $params['custom_fields']           = $customFields;

        return DB::transaction(function () use ($request, $s, $v, $name, $time, $info, $main, $params, $customFields) {

            $s->update([
                'alias' => $v['alias'] ?? $s->alias,
                'group_id'    => $v['group_id'] ?? null,

                'name'       => json_encode($name, JSON_UNESCAPED_UNICODE),
                'time'       => json_encode($time, JSON_UNESCAPED_UNICODE),
                'info'       => json_encode($info, JSON_UNESCAPED_UNICODE),
                'main_field' => json_encode($main, JSON_UNESCAPED_UNICODE),
                'params'     => json_encode($params, JSON_UNESCAPED_UNICODE),

                'cost'        => $v['cost'] ?? $s->cost,
                'profit'      => $v['profit'] ?? $s->profit,
                'profit_type' => $v['profit_type'] ?? $s->profit_type,

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

            $this->saveGroupPrices((int)$s->id, $v['group_prices'] ?? []);
            $this->saveCustomFieldsToTable((int)$s->id, $this->viewPrefix, $customFields);

            return response()->json(['ok' => true, 'msg' => 'Updated']);
        });
    }

    public function destroy($service)
    {
        $s = ($service instanceof Model)
            ? $service
            : ($this->model)::query()->findOrFail($service);

        $s->delete();

        return response()->json(['ok' => true, 'msg' => 'Deleted']);
    }

    public function toggle($service)
    {
        $s = ($service instanceof Model)
            ? $service
            : ($this->model)::query()->findOrFail($service);

        $s->active = (int)!((int)$s->active);
        $s->save();

        return response()->json(['ok' => true, 'active' => (int)$s->active]);
    }

    // ===================== GROUP PRICES + CUSTOM FIELDS HELPERS =====================

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