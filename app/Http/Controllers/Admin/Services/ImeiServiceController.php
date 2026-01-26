<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroupPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ImeiServiceController extends Controller
{
    public function index(Request $r)
    {
        $q = ImeiService::query()
            ->with(['group', 'supplier', 'api'])
            ->orderBy('id', 'desc');

        if ($r->filled('q')) {
            $term = $r->q;
            $q->where(function ($qq) use ($term) {
                $qq->where('alias', 'like', "%$term%")
                    ->orWhere('name', 'like', "%$term%");
            });
        }

        if ($r->filled('api_provider_id')) {
            $q->where('source', (int)$r->api_provider_id);
        }

        $rows = $q->paginate(20)->withQueryString();

        $apis = ImeiService::query()
            ->select('source')
            ->whereNotNull('source')
            ->groupBy('source')
            ->pluck('source');

        return view('admin.services.imei.index', [
            'rows' => $rows,
            'apis' => $apis,
            'routePrefix' => 'admin.services.imei',
            'viewPrefix'  => 'imei',
        ]);
    }

    public function modalCreate(Request $r)
    {
        $data = [
            'supplier_id' => $r->input('provider_id'),
            'remote_id'   => $r->input('remote_id'),
            'name'        => $r->input('name'),
            'cost'        => $r->input('credit'),
            'time'        => $r->input('time'),
            'group_name'  => $r->input('group'),
            'type'        => 'imei',
        ];

        return view('admin.services.imei._modal_create', compact('data'));
    }

    /**
     * ✅ Save pricing values in service_group_prices table
     */
    private function saveGroupPrices(int $serviceId, array $groupPrices): void
    {
        foreach ($groupPrices as $groupId => $row) {
            ServiceGroupPrice::updateOrCreate(
                [
                    'service_id'   => $serviceId,
                    // ✅ الصحيح حسب جدول وموديل ServiceGroupPrice
                    'service_type' => 'imei',
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

    /**
     * ✅ Normalize custom fields into a consistent structure
     * (Because UI sends custom_fields_json with keys: input/minimum/maximum/type/options ...)
     */
    private function normalizeCustomFields($customFieldsRaw, ?string $customFieldsJson): array
    {
        $customFields = [];

        // لو وصل array مباشرة
        if (is_array($customFieldsRaw)) {
            $customFields = $customFieldsRaw;
        }

        // لو وصل كنص JSON داخل custom_fields
        if (is_string($customFieldsRaw) && trim($customFieldsRaw) !== '') {
            $decoded = json_decode($customFieldsRaw, true);
            if (is_array($decoded)) $customFields = $decoded;
        }

        // لو ما وصل شيء، جرب custom_fields_json
        if (!is_array($customFields) || empty($customFields)) {
            if (is_string($customFieldsJson) && trim($customFieldsJson) !== '') {
                $decoded = json_decode($customFieldsJson, true);
                if (is_array($decoded)) $customFields = $decoded;
            }
        }

        if (!is_array($customFields)) $customFields = [];

        // ✅ توحيد المفاتيح
        $out = [];
        foreach ($customFields as $f) {
            if (!is_array($f)) continue;

            $inputName = (string)($f['input_name'] ?? $f['input'] ?? '');
            $fieldType = (string)($f['field_type'] ?? $f['type'] ?? 'text');

            $min = $f['min'] ?? $f['minimum'] ?? 0;
            $max = $f['max'] ?? $f['maximum'] ?? 0;

            $options = (string)($f['field_options'] ?? $f['options'] ?? '');

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

    public function store(Request $request)
    {
        // تنظيف القيم undefined
        foreach (['remote_id', 'supplier_id', 'api_provider_id', 'api_service_remote_id'] as $k) {
            $v = $request->input($k);
            if ($v === 'undefined' || $v === '') {
                $request->merge([$k => null]);
            }
        }

        // ✅ VALIDATION
        // ملاحظة مهمة: لا تعمل validate على custom_fields كـ array لأن الفورم يرسل JSON داخل custom_fields_json
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

            // main field config
            'main_field_type'    => 'required|string|max:50',
            'allowed_characters' => 'nullable|string|max:50',
            'min'                => 'nullable|integer',
            'max'                => 'nullable|integer',
            'main_field_label'   => 'nullable|string|max:255',

            // meta params
            'meta_keywords'     => 'nullable|string',
            'meta_description'  => 'nullable|string',
            'meta_after_head'   => 'nullable|string',
            'meta_before_head'  => 'nullable|string',
            'meta_after_body'   => 'nullable|string',
            'meta_before_body'  => 'nullable|string',

            // pricing
            'cost'        => 'nullable|numeric',
            'profit'      => 'nullable|numeric',
            'profit_type' => 'nullable|integer',

            // toggles
            'active'             => 'sometimes|boolean',
            'allow_bulk'         => 'sometimes|boolean',
            'allow_duplicates'   => 'sometimes|boolean',
            'reply_with_latest'  => 'sometimes|boolean',
            'allow_report'       => 'sometimes|boolean',
            'allow_report_time'  => 'nullable|integer',
            'allow_cancel'       => 'sometimes|boolean',
            'allow_cancel_time'  => 'nullable|integer',
            'use_remote_cost'    => 'sometimes|boolean',
            'use_remote_price'   => 'sometimes|boolean',
            'stop_on_api_change' => 'sometimes|boolean',
            'needs_approval'     => 'sometimes|boolean',
            'reply_expiration'   => 'nullable|integer',

            'reject_on_missing_reply' => 'sometimes|boolean',
            'ordering'                => 'nullable|integer',

            // API selection
            'api_provider_id'       => 'nullable|integer',
            'api_service_remote_id' => 'nullable|integer',

            // group prices
            'group_prices' => 'nullable|array',
            'group_prices.*.price' => 'nullable|numeric|min:0',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            // ✅ JSON payloads from Additional tab
            'custom_fields_json' => 'nullable|string',
        ]);

        // ✅ Normalize custom fields from JSON
        $customFields = $this->normalizeCustomFields(
            $request->input('custom_fields'), // غالباً لن يجي (ونحن سنحذفه من الفورم)
            $request->input('custom_fields_json')
        );

        // ✅ alias لازم لا يكون null
        $alias = $v['alias'] ?? null;
        if (!$alias) $alias = Str::slug($v['name'] ?? '');
        if (!$alias) $alias = 'service-' . Str::random(8);

        $base = $alias;
        $i = 1;
        while (ImeiService::where('alias', $alias)->exists()) {
            $alias = $base . '-' . $i++;
        }
        $v['alias'] = $alias;

        // main field mapping
        $map = [
            'IMEI'   => 'imei',
            'Serial' => 'serial',
            'Number' => 'number',
            'Email'  => 'email',
            'Text'   => 'text',
        ];
        $mainType = $map[$v['main_field_type']] ?? strtolower($v['main_field_type']);

        $name = ['en' => $v['name'], 'fallback' => $v['name']];
        $time = ['en' => ($v['time'] ?? ''), 'fallback' => ($v['time'] ?? '')];
        $info = ['en' => ($v['info'] ?? ''), 'fallback' => ($v['info'] ?? '')];

        $main = [
            'type'  => $mainType,
            'rules' => [
                'allowed' => $v['allowed_characters'] ?? null,
                'minimum' => $v['min'] ?? null,
                'maximum' => $v['max'] ?? null,
            ],
            'label' => [
                'en' => $v['main_field_label'] ?? '',
                'fallback' => $v['main_field_label'] ?? '',
            ],
        ];

        // ✅ Params + Custom Fields
        $params = [
            'meta_keywords'           => $v['meta_keywords'] ?? '',
            'meta_description'        => $v['meta_description'] ?? '',
            'after_head_tag_opening'  => $v['meta_after_head'] ?? '',
            'before_head_tag_closing' => $v['meta_before_head'] ?? '',
            'after_body_tag_opening'  => $v['meta_after_body'] ?? '',
            'before_body_tag_closing' => $v['meta_before_body'] ?? '',

            // ✅ custom fields
            'custom_fields' => $customFields,
        ];

        // لو source API واختار من القائمة
        if (($v['source'] ?? null) == 2 && !empty($v['api_provider_id']) && !empty($v['api_service_remote_id'])) {
            $v['supplier_id'] = (int)$v['api_provider_id'];
            $v['remote_id']   = (int)$v['api_service_remote_id'];
        }

        return DB::transaction(function () use ($request, $v, $name, $time, $info, $main, $params) {

            $service = ImeiService::create([
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

            // ✅ حفظ أسعار الجروبات
            $this->saveGroupPrices($service->id, $v['group_prices'] ?? []);

            // ✅ Ajax friendly response
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

    public function modalEdit(ImeiService $service)
    {
        return view('admin.services.imei._modal_edit', compact('service'));
    }

    public function update(Request $r, ImeiService $service)
    {
        // (اختياري) لو لاحقاً حبيت تدعم تحديث custom_fields
        $data = $r->validate([
            'name'        => 'required|string',
            'time'        => 'nullable|string',
            'info'        => 'nullable|string',
            'cost'        => 'required|numeric|min:0',
            'profit'      => 'nullable|numeric|min:0',
            'profit_type' => 'nullable|integer|in:1,2',
            'active'      => 'nullable|boolean',

            'group_prices'                 => 'nullable|array',
            'group_prices.*.price'         => 'nullable|numeric|min:0',
            'group_prices.*.discount'      => 'nullable|numeric|min:0',
            'group_prices.*.discount_type' => 'nullable|integer|in:1,2',

            'custom_fields_json' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($service, $data, $r) {

            $service->update([
                'name'        => $data['name'],
                'time'        => $data['time'] ?? null,
                'info'        => $data['info'] ?? null,
                'cost'        => (float)$data['cost'],
                'profit'      => (float)($data['profit'] ?? 0),
                'profit_type' => (int)($data['profit_type'] ?? 1),
                'active'      => (int)($data['active'] ?? 1),
            ]);

            $this->saveGroupPrices($service->id, $data['group_prices'] ?? []);

            return response()->json([
                'ok' => true,
                'msg' => '✅ Updated successfully'
            ]);
        });
    }
}
