<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroupPrice;
use App\Models\CustomField;
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
        // تنظيف undefined
        foreach (['remote_id', 'supplier_id', 'api_provider_id', 'api_service_remote_id'] as $k) {
            $v = $request->input($k);
            if ($v === 'undefined' || $v === '') {
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

            'name'         => 'required|string',
            'time'         => 'nullable|string',
            'info'         => 'nullable|string',

            'main_field_type'    => 'required|string|max:50',
            'allowed_characters' => 'nullable|string|max:50',
            'min'                => 'nullable|integer',
            'max'                => 'nullable|integer',
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
            'ordering'          => 'nullable|integer',

            'api_provider_id'       => 'nullable|integer',
            'api_service_remote_id' => 'nullable|integer',

            // ✅ إضافات additional
            'group_prices' => 'nullable|string', // JSON
            'custom_fields' => 'nullable|string', // JSON
        ]);

        // alias
        $alias = $v['alias'] ?? null;
        if (!$alias) $alias = Str::slug($v['name'] ?? '');
        if (!$alias) $alias = 'service-' . Str::random(8);

        $base = $alias;
        $i = 1;
        while (ImeiService::where('alias', $alias)->exists()) {
            $alias = $base . '-' . $i++;
        }
        $v['alias'] = $alias;

        // main_field_type normalize
        $mainType = strtolower((string)$v['main_field_type']);

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

        // source api mapping
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

                'cost'       => $v['cost'] ?? 0,
                'profit'     => $v['profit'] ?? 0,
                'profit_type'=> $v['profit_type'] ?? 1,

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

            // ✅ Save group prices (JSON)
            $groupPricesJson = $request->input('group_prices');
            if ($groupPricesJson) {
                $rows = json_decode($groupPricesJson, true);
                if (is_array($rows)) {
                    $this->saveGroupPrices($service->id, $rows);
                }
            }

            // ✅ Save custom fields (JSON)
            $customJson = $request->input('custom_fields');
            if ($customJson) {
                $rows = json_decode($customJson, true);
                if (is_array($rows)) {
                    $this->saveCustomFields($service->id, 'imei_service', $rows);
                }
            }

            return back()->with('ok', 'Created');
        });
    }

    private function saveGroupPrices(int $serviceId, array $rows): void
    {
        // rows: [{group_id, price, discount, discount_type}]
        foreach ($rows as $r) {
            $groupId = (int)($r['group_id'] ?? 0);
            if (!$groupId) continue;

            ServiceGroupPrice::updateOrCreate([
                'service_id'   => $serviceId,
                'service_kind' => 'imei',
                'group_id'     => $groupId,
            ], [
                'price'         => (float)($r['price'] ?? 0),
                'discount'      => (float)($r['discount'] ?? 0),
                'discount_type' => (int)($r['discount_type'] ?? 1),
            ]);
        }
    }

    private function saveCustomFields(int $serviceId, string $serviceType, array $rows): void
    {
        // امسح القديم ثم أعد الإدخال
        CustomField::where('service_id', $serviceId)
            ->where('service_type', $serviceType)
            ->delete();

        $order = 0;
        foreach ($rows as $r) {
            $name = trim((string)($r['name'] ?? ''));
            if ($name === '') continue;

            CustomField::create([
                'service_id'   => $serviceId,
                'service_type' => $serviceType,

                'name'         => $name,
                'input'        => (string)($r['input'] ?? ''),
                'description'  => (string)($r['description'] ?? ''),

                'field_type'   => (string)($r['field_type'] ?? 'text'),
                'field_options'=> (string)($r['field_options'] ?? ''),

                'validation'   => (string)($r['validation'] ?? ''),

                'minimum'      => (int)($r['minimum'] ?? 0),
                'maximum'      => (int)($r['maximum'] ?? 0),

                'required'     => (int)!empty($r['required']),
                'active'       => (int)!empty($r['active']),
                'ordering'     => $order++,
            ]);
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

            'group_prices' => 'nullable|string',
            'custom_fields'=> 'nullable|string',
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

            if (!empty($data['group_prices'])) {
                $rows = json_decode($data['group_prices'], true);
                if (is_array($rows)) $this->saveGroupPrices($service->id, $rows);
            }

            if (!empty($data['custom_fields'])) {
                $rows = json_decode($data['custom_fields'], true);
                if (is_array($rows)) $this->saveCustomFields($service->id, 'imei_service', $rows);
            }

            return response()->json([
                'ok'  => true,
                'msg' => '✅ Updated successfully'
            ]);
        });
    }
}
