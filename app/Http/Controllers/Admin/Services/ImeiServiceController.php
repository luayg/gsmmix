<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroup;
use App\Models\ServiceGroupPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        if ($r->filled('api_id')) {
            $q->where('source', (int) $r->api_id);
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
        // ✅ يتم استدعاؤه من زر Clone / Add
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

    public function store(Request $request)
    {
        foreach (['remote_id', 'supplier_id', 'api_provider_id', 'api_service_remote_id'] as $k) {
            $vv = $request->input($k);
            if ($vv === 'undefined' || $vv === '') {
                $request->merge([$k => null]);
            }
        }

        $v = $request->validate([
            'alias'        => 'nullable|string|max:255',
            'group_id'     => 'nullable|integer|exists:service_groups,id',
            'type'         => 'required|string|max:255',

            'source'       => 'nullable|integer',
            'remote_id'    => 'nullable',
            'supplier_id'  => 'nullable',

            // حقول المودال الأساسية
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

            // اختيار خدمة Remote من واجهة “Add Service”
            'api_provider_id'       => 'nullable|integer',
            'api_service_remote_id' => 'nullable|integer',

            // ✅ Additional (Groups pricing)
            'pricing_table'  => 'nullable|string', // JSON from pricingTableHidden
            'group_price'    => 'nullable|array',  // legacy
            'group_discount' => 'nullable|array',

            // ✅ Additional (Custom fields)
            'custom_fields' => 'nullable|array',
            'custom_fields.name'        => 'nullable|array',
            'custom_fields.type'        => 'nullable|array',
            'custom_fields.input_name'  => 'nullable|array',
            'custom_fields.description' => 'nullable|array',
            'custom_fields.min'         => 'nullable|array',
            'custom_fields.max'         => 'nullable|array',
            'custom_fields.validation'  => 'nullable|array',
            'custom_fields.required'    => 'nullable|array',
        ]);

        // ✅ alias must never be null
        $alias = $v['alias'] ?? null;

        if (!$alias) seeing.
            $alias = Str::slug($v['name'] ?? '');
        }
        if (!$alias) {
            $alias = 'service-' . Str::random(8);
        }

        // ✅ ensure unique
        $base = $alias;
        $i = 1;
        while (ImeiService::where('alias', $alias)->exists()) {
            $alias = $base . '-' . $i++;
        }
        $v['alias'] = $alias;

        // main_field type normalize
        $map = [
            'IMEI'         => 'imei',
            'Serial'       => 'serial',
            'Number'       => 'number',
            'Email'        => 'email',
            'Text'         => 'text',

            // لو عندك خيارات مثل imei_serial:
            'IMEI/SN'      => 'imei',
            'IMEI/Serial'  => 'imei',
        ];
        $mainType = $map[$v['main_field_type']] ?? strtolower($v['main_field_type']);

        // JSON fields
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

        $params = [
            'meta_keywords'           => $v['meta_keywords'] ?? '',
            'meta_description'        => $v['meta_description'] ?? '',
            'after_head_tag_opening'  => $v['meta_after_head'] ?? '',
            'before_head_tag_closing' => $v['meta_before_head'] ?? '',
            'after_body_tag_opening'  => $v['meta_after_body'] ?? '',
            'before_body_tag_closing' => $v['meta_before_body'] ?? '',
        ];

        // إذا مصدر الخدمة API وتم اختيار خدمة Remote:
        if (($v['source'] ?? null) == 2 && !empty($v['api_provider_id']) && !empty($v['api_service_remote_id'])) {
            $v['supplier_id'] = (int) $v['api_provider_id'];
            $v['remote_id']   = (int) $v['api_service_remote_id'];
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

                'active'            => (int) $request->boolean('active'),
                'allow_bulk'        => (int) $request->boolean('allow_bulk'),
                'allow_duplicates'  => (int) $request->boolean('allow_duplicates'),
                'reply_with_latest' => (int) $request->boolean('reply_with_latest'),
                'allow_report'      => (int) $request->boolean('allow_report'),
                'allow_report_time' => (int) ($v['allow_report_time'] ?? 0),
                'allow_cancel'      => (int) $request->boolean('allow_cancel'),
                'allow_cancel_time' => (int) ($v['allow_cancel_time'] ?? 0),

                'use_remote_cost'    => (int) $request->boolean('use_remote_cost'),
                'use_remote_price'   => (int) $request->boolean('use_remote_price'),
                'stop_on_api_change' => (int) $request->boolean('stop_on_api_change'),
                'needs_approval'     => (int) $request->boolean('needs_approval'),

                'reply_expiration'        => (int) ($v['reply_expiration'] ?? 0),
                'reject_on_missing_reply' => (int) $request->boolean('reject_on_missing_reply'),
                'ordering'                => (int) ($v['ordering'] ?? 0),
            ]);

            // ✅ Save groups pricing
            $groupPrices = $this->extractGroupPricesFromRequest($request);
            if (!empty($groupPrices)) {
                $this->saveGroupPrices($service->id, $groupPrices);
            }

            // ✅ Save custom fields rows
            $this->saveCustomFields($service->id, $request->input('custom_fields', []));

            return back()->with('ok', 'Created');
        });
    }

    /**
     * ✅ Read group prices from:
     * - pricing_table JSON (pricingTableHidden)
     * - group_price[] + group_discount[] (legacy)
     */
    private function extractGroupPricesFromRequest(Request $r): array
    {
        $out = [];

        // 1) New way: pricing_table JSON
        $json = $r->input('pricing_table');
        if ($json) {
            $rows = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($rows)) {
                foreach ($rows as $row) {
                    $gid = (int) ($row['group_id'] ?? 0);
                    if ($gid <= 0) continue;

                    $out[$gid] = [
                        'price'         => (float) ($row['price'] ?? 0),
                        'discount'      => (float) ($row['discount'] ?? 0),
                        'discount_type' => (int) ($row['discount_type'] ?? 1),
                    ];
                }
                return $out;
            }
        }

        // 2) Legacy way: group_price + group_discount
        $prices = $r->input('group_price', []);
        $discounts = $r->input('group_discount', []);

        if (is_array($prices) || is_array($discounts)) {
            $allIds = array_unique(array_merge(
                array_map('intval', array_keys((array) $prices)),
                array_map('intval', array_keys((array) $discounts))
            ));

            foreach ($allIds as $gid) {
                if ($gid <= 0) continue;
                $out[$gid] = [
                    'price'         => (float) ($prices[$gid] ?? 0),
                    'discount'      => (float) ($discounts[$gid] ?? 0),
                    'discount_type' => 1, // legacy: credits
                ];
            }
        }

        return $out;
    }

    /**
     * ✅ Save pricing values in service_group_prices table
     * IMPORTANT: uses service_type (not service_kind)
     */
    private function saveGroupPrices(int $serviceId, array $groupPrices): void
    {
        foreach ($groupPrices as $groupId => $row) {
            ServiceGroupPrice::updateOrCreate([
                'service_id'   => $serviceId,
                'service_type' => 'imei',
                'group_id'     => (int) $groupId,
            ], [
                'price'         => (float) ($row['price'] ?? 0),
                'discount'      => (float) ($row['discount'] ?? 0),
                'discount_type' => (int) ($row['discount_type'] ?? 1),
            ]);
        }
    }

    /**
     * ✅ Save custom fields to `custom_fields` table
     * (Delete old, Insert new)
     */
    private function saveCustomFields(int $serviceId, array $cf): void
    {
        // نفس القيمة اللي عندك في قاعدة البيانات: imei_service / server_service ...
        $customServiceType = 'imei_service';

        $names = $cf['name'] ?? [];
        if (!is_array($names) || count($names) === 0) {
            DB::table('custom_fields')
                ->where('service_id', $serviceId)
                ->where('service_type', $customServiceType)
                ->delete();
            return;
        }

        $types       = $cf['type'] ?? [];
        $inputs      = $cf['input_name'] ?? [];
        $descs       = $cf['description'] ?? [];
        $mins        = $cf['min'] ?? [];
        $maxs        = $cf['max'] ?? [];
        $validations = $cf['validation'] ?? [];
        $requireds   = $cf['required'] ?? [];

        // احذف القديم
        DB::table('custom_fields')
            ->where('service_id', $serviceId)
            ->where('service_type', $customServiceType)
            ->delete();

        $now = now();
        $rows = [];

        $count = max(
            count($names),
            count($types),
            count($inputs),
            count($descs),
            count($mins),
            count($maxs),
            count($validations),
            count($requireds)
        );

        for ($i = 0; $i < $count; $i++) {
            $n = trim((string) ($names[$i] ?? ''));
            if ($n === '') continue;

            $rowType = (string) ($types[$i] ?? 'text');
            $input   = trim((string) ($inputs[$i] ?? ''));
            if ($input === '') {
                $input = 'field_' . ($i + 1);
            }

            $rows[] = [
                'service_id'   => $serviceId,
                'service_type' => $customServiceType,

                'name'        => json_encode(['en' => $n, 'fallback' => $n], JSON_UNESCAPED_UNICODE),
                'input'       => $input,
                'description' => json_encode([
                    'en' => (string) ($descs[$i] ?? ''),
                    'fallback' => (string) ($descs[$i] ?? ''),
                ], JSON_UNESCAPED_UNICODE),

                'type'       => $rowType,
                'validation' => (string) ($validations[$i] ?? ''),
                'minimum'    => is_numeric($mins[$i] ?? null) ? (int) $mins[$i] : 0,
                'maximum'    => is_numeric($maxs[$i] ?? null) ? (int) $maxs[$i] : 0,
                'required'   => (int) ((string) ($requireds[$i] ?? '0') === '1'),

                'options'    => json_encode(['en' => '', 'fallback' => ''], JSON_UNESCAPED_UNICODE),
                'active'     => 1,
                'ordering'   => $i,

                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('custom_fields')->insert($rows);
        }
    }

    public function modalEdit(ImeiService $service)
    {
        return view('admin.services.imei._modal_edit', compact('service'));
    }

    public function update(Request $r, ImeiService $service)
    {
        $data = $r->validate([
            'name'        => 'required|string',
            'time'        => 'nullable|string',
            'info'        => 'nullable|string',
            'cost'        => 'required|numeric|min:0',
            'profit'      => 'nullable|numeric|min:0',
            'profit_type' => 'nullable|integer|in:1,2',
            'active'      => 'nullable|boolean',

            // ✅ Additional pricing (two formats)
            'pricing_table'  => 'nullable|string',
            'group_price'    => 'nullable|array',
            'group_discount' => 'nullable|array',

            // ✅ Additional custom fields
            'custom_fields' => 'nullable|array',
            'custom_fields.name'        => 'nullable|array',
            'custom_fields.type'        => 'nullable|array',
            'custom_fields.input_name'  => 'nullable|array',
            'custom_fields.description' => 'nullable|array',
            'custom_fields.min'         => 'nullable|array',
            'custom_fields.max'         => 'nullable|array',
            'custom_fields.validation'  => 'nullable|array',
            'custom_fields.required'    => 'nullable|array',
        ]);

        return DB::transaction(function () use ($service, $r, $data) {

            $service->update([
                'name'        => $data['name'],
                'time'        => $data['time'] ?? null,
                'info'        => $data['info'] ?? null,
                'cost'        => (float) $data['cost'],
                'profit'      => (float) ($data['profit'] ?? 0),
                'profit_type' => (int) ($data['profit_type'] ?? 1),
                'active'      => (int) ($data['active'] ?? 1),
            ]);

            // ✅ Save group prices
            $groupPrices = $this->extractGroupPricesFromRequest($r);
            if (!empty($groupPrices)) {
                $this->saveGroupPrices($service->id, $groupPrices);
            }

            // ✅ Save custom fields
            $this->saveCustomFields($service->id, $r->input('custom_fields', []));

            return response()->json([
                'ok'  => true,
                'msg' => '✅ Updated successfully'
            ]);
        });
    }
}
