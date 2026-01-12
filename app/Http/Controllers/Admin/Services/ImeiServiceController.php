<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\ServiceGroupPrice;
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
        $q->where(function($qq) use ($term){
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

foreach (['remote_id','supplier_id','api_provider_id','api_service_remote_id'] as $k) {
    $v = $request->input($k);
    if ($v === 'undefined' || $v === '') {
        $request->merge([$k => null]);
    }
}



    // 1) استقبل كل حقول المودال (حالياً كثير منها يُتجاهل) 
    $v = $request->validate([
        'alias'        => 'nullable|string|max:255',
        'group_id'     => 'nullable|integer|exists:service_groups,id',
        'type'         => 'required|string|max:255',

        'source'       => 'nullable|integer',
        'remote_id'   => 'nullable',
        'supplier_id' => 'nullable',


        // حقول المودال الأساسية
        'name'         => 'required|string',
        'time'         => 'nullable|string',
        'info'         => 'nullable|string',

        // main field config
        'main_field_type'   => 'required|string|max:50',
        'allowed_characters'=> 'nullable|string|max:50',
        'min'               => 'nullable|integer',
        'max'               => 'nullable|integer',
        'main_field_label'  => 'nullable|string|max:255',

        // meta params
        'meta_keywords'     => 'nullable|string',
        'meta_description'  => 'nullable|string',
        'meta_after_head'   => 'nullable|string',
        'meta_before_head'  => 'nullable|string',
        'meta_after_body'   => 'nullable|string',
        'meta_before_body'  => 'nullable|string',

        // pricing
        'cost'          => 'nullable|numeric',
        'profit'        => 'nullable|numeric',
        'profit_type'   => 'nullable|integer',

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
        'reply_expiration'  => 'nullable|integer',

        'reject_on_missing_reply' => 'sometimes|boolean',
        'ordering'                => 'nullable|integer',

        // اختيار خدمة Remote من واجهة “Add Service” (موجود بالمودال) 
        'api_provider_id'      => 'nullable|integer',
        'api_service_remote_id'=> 'nullable|integer',
    ]);

    // 2) طابق main_field_type إلى صيغة ثابتة (يمكن تعديلها لاحقاً حسب نظامك)
    $map = [
        'IMEI'   => 'imei',
        'Serial' => 'serial',
        'Number' => 'number',
        'Email'  => 'email',
        'Text'   => 'text',
    ];
    $mainType = $map[$v['main_field_type']] ?? strtolower($v['main_field_type']);

    // 3) ابنِ JSONs مثل BaseServiceController (هذا المطلوب ليطابق قاعدة البيانات “الصح”) :contentReference[oaicite:5]{index=5}
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

    // 4) إذا كان مصدر الخدمة API وتم اختيار خدمة Remote من المودال:
    // اجعل supplier_id = api_provider_id و remote_id = api_service_remote_id (بدل القيم الخفية/القديمة)
    if (($v['source'] ?? null) == 2 && !empty($v['api_provider_id']) && !empty($v['api_service_remote_id'])) {
        $v['supplier_id'] = (int)$v['api_provider_id'];
        $v['remote_id']   = (int)$v['api_service_remote_id'];
    }

    // 5) احفظ كل شيء بالأعمدة الصحيحة (المشكلة الحالية أنها لا تُحفظ) :contentReference[oaicite:6]{index=6}
    ImeiService::create([
        'alias'      => $v['alias'] ?? null,
        'group_id'   => $v['group_id'] ?? null,
        'type'       => $v['type'],

        'source'     => $v['source'] ?? null,
        'remote_id'  => $v['remote_id'] ?? null,
        'supplier_id'=> $v['supplier_id'] ?? null,

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

    return back()->with('ok', 'Created');
}




    /**
     * ✅ Save pricing values in service_group_prices table
     */
    private function saveGroupPrices(int $serviceId, array $groupPrices)
    {
        foreach ($groupPrices as $groupId => $row) {
            ServiceGroupPrice::updateOrCreate([
                'service_id'   => $serviceId,
                'service_kind' => 'imei',
                'group_id'     => (int)$groupId,
            ], [
                'price'    => (float)($row['price'] ?? 0),
                'discount' => (float)($row['discount'] ?? 0),
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

            // ✅ Additional Tab Pricing
            'group_prices'        => 'nullable|array',
            'group_prices.*.price'    => 'nullable|numeric|min:0',
            'group_prices.*.discount' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($service, $data) {

            $service->update([
                'name'        => $data['name'],
                'time'        => $data['time'] ?? null,
                'info'        => $data['info'] ?? null,
                'cost'        => (float)$data['cost'],
                'profit'      => (float)($data['profit'] ?? 0),
                'profit_type' => (int)($data['profit_type'] ?? 1),
                'active'      => (int)($data['active'] ?? 1),
            ]);

            // ✅ Save group prices
            $this->saveGroupPrices($service->id, $data['group_prices'] ?? []);

            return response()->json([
                'ok' => true,
                'msg' => '✅ Updated successfully'
            ]);
        });
    }
}
