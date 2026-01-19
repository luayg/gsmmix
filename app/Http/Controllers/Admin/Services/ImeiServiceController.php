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
            $q->where('source', (int)$r->api_id);
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
        // ✅ تنظيف undefined/null
        foreach ([
                     'remote_id',
                     'supplier_id',
                     'api_provider_id',
                     'api_service_remote_id',
                     'group_id'
                 ] as $k) {
            $v = $request->input($k);
            if ($v === 'undefined' || $v === '') {
                $request->merge([$k => null]);
            }
        }

        // ✅ Validate
        $v = $request->validate([
            'alias'     => 'nullable|string|max:255',
            'group_id'  => 'nullable|integer|exists:service_groups,id',
            'type'      => 'required|string|max:255',

            'source'       => 'nullable|integer',
            'remote_id'    => 'nullable',
            'supplier_id'  => 'nullable',

            // General
            'name'     => 'required|string',
            'time'     => 'nullable|string',
            'info'     => 'nullable|string',

            // main field
            'main_field_type'     => 'required|string|max:50',
            'allowed_characters'  => 'nullable|string|max:50',
            'min'                 => 'nullable|integer',
            'max'                 => 'nullable|integer',
            'main_field_label'    => 'nullable|string|max:255',

            // meta
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

            'reply_expiration'        => 'nullable|integer',
            'reject_on_missing_reply' => 'sometimes|boolean',
            'ordering'                => 'nullable|integer',

            // API picks
            'api_provider_id'       => 'nullable|integer',
            'api_service_remote_id' => 'nullable|integer',

            // ✅ Additional tab payloads (JSON coming from modal)
            'group_prices_json'   => 'nullable|string',
            'custom_fields_json'  => 'nullable|string',
        ]);

        // ✅ alias must never be null
        $alias = $v['alias'] ?? null;
        if (!$alias) $alias = Str::slug($v['name'] ?? '');
        if (!$alias) $alias = 'service-' . Str::random(8);

        // ✅ ensure unique
        $base = $alias;
        $i = 1;
        while (ImeiService::where('alias', $alias)->exists()) {
            $alias = $base . '-' . $i++;
        }
        $v['alias'] = $alias;

        // ✅ map main_field_type to stable internal type
        $mainType = strtolower($v['main_field_type'] ?? 'imei');

        // ✅ localized JSON fields
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
                'en'       => $v['main_field_label'] ?? '',
                'fallback' => $v['main_field_label'] ?? '',
            ],
        ];

        // ✅ params base
        $params = [
            'meta_keywords'           => $v['meta_keywords'] ?? '',
            'meta_description'        => $v['meta_description'] ?? '',
            'after_head_tag_opening'  => $v['meta_after_head'] ?? '',
            'before_head_tag_closing' => $v['meta_before_head'] ?? '',
            'after_body_tag_opening'  => $v['meta_after_body'] ?? '',
            'before_body_tag_closing' => $v['meta_before_body'] ?? '',
        ];

        // ✅ Custom fields from Additional tab
        // stored inside params.custom_fields
        $customFields = [];
        if (!empty($v['custom_fields_json'])) {
            $decoded = json_decode($v['custom_fields_json'], true);
            if (is_array($decoded)) {
                $customFields = $decoded;
            }
        }
        $params['custom_fields'] = $customFields;

        // ✅ If Source = API and chosen remote service, override supplier+remote
        if (($v['source'] ?? null) == 2 && !empty($v['api_provider_id']) && !empty($v['api_service_remote_id'])) {
            $v['supplier_id'] = (int)$v['api_provider_id'];
            $v['remote_id']   = (int)$v['api_service_remote_id'];
        }

        // ✅ Save everything in transaction
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

            // ✅ Save group prices from Additional tab
            $groupPrices = [];
            if (!empty($v['group_prices_json'])) {
                $decoded = json_decode($v['group_prices_json'], true);
                if (is_array($decoded)) {
                    $groupPrices = $decoded;
                }
            }
            $this->saveGroupPricesFromRows($service->id, $groupPrices);

            // ✅ Ajax response or normal redirect
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'msg' => '✅ Created successfully',
                ]);
            }

            return back()->with('ok', 'Created');
        });
    }

    /**
     * ✅ Save pricing values in service_group_prices table (create/store)
     * Expected rows:
     * [
     *   { group_id: 1, price: 0, discount: 0, discount_type: 1 },
     *   ...
     * ]
     */
    private function saveGroupPricesFromRows(int $serviceId, array $rows): void
    {
        foreach ($rows as $row) {
            $groupId = (int)($row['group_id'] ?? 0);
            if ($groupId <= 0) continue;

            ServiceGroupPrice::updateOrCreate([
                'service_id'   => $serviceId,
                'service_kind' => 'imei',
                'group_id'     => $groupId,
            ], [
                'price'         => (float)($row['price'] ?? 0),
                'discount'      => (float)($row['discount'] ?? 0),
                'discount_type' => (int)($row['discount_type'] ?? 1),
            ]);
        }
    }

    /**
     * ✅ Edit modal
     */
    public function modalEdit(ImeiService $service)
    {
        // you can load existing custom fields / group pricing here later
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

            // ✅ Additional payloads
            'group_prices_json'  => 'nullable|string',
            'custom_fields_json' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($service, $data, $r) {

            // ✅ Update base fields
            $service->update([
                'cost'        => (float)$data['cost'],
                'profit'      => (float)($data['profit'] ?? 0),
                'profit_type' => (int)($data['profit_type'] ?? 1),
                'active'      => (int)($data['active'] ?? 1),
            ]);

            // ✅ Update json name/time/info
            $service->name = json_encode(['en' => $data['name'], 'fallback' => $data['name']], JSON_UNESCAPED_UNICODE);
            $service->time = json_encode(['en' => ($data['time'] ?? ''), 'fallback' => ($data['time'] ?? '')], JSON_UNESCAPED_UNICODE);
            $service->info = json_encode(['en' => ($data['info'] ?? ''), 'fallback' => ($data['info'] ?? '')], JSON_UNESCAPED_UNICODE);

            // ✅ Update params.custom_fields without destroying other params
            $params = json_decode($service->params ?? '{}', true);
            if (!is_array($params)) $params = [];

            $customFields = [];
            if (!empty($data['custom_fields_json'])) {
                $decoded = json_decode($data['custom_fields_json'], true);
                if (is_array($decoded)) $customFields = $decoded;
            }
            $params['custom_fields'] = $customFields;

            $service->params = json_encode($params, JSON_UNESCAPED_UNICODE);
            $service->save();

            // ✅ Update group pricing
            $rows = [];
            if (!empty($data['group_prices_json'])) {
                $decoded = json_decode($data['group_prices_json'], true);
                if (is_array($decoded)) $rows = $decoded;
            }
            $this->saveGroupPricesFromRows($service->id, $rows);

            return response()->json([
                'ok'  => true,
                'msg' => '✅ Updated successfully'
            ]);
        });
    }
}
