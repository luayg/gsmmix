<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ImeiService;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImeiServiceController extends Controller
{
    /* =========================
     * Index (قائمة الخدمات)
     * ========================= */
    public function index(Request $request)
    {
        $rows = ImeiService::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(function ($z) use ($term) {
                    $z->where('name',  'like', $term)
                      ->orWhere('alias', 'like', $term)
                      ->orWhere('info',  'like', $term);
                });
            })
            ->when($request->filled('group_id'), fn($q) => $q->where('group_id', $request->integer('group_id')))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $apis        = []; // إن كان عندك موديل RemoteApi أعِدّه هنا
        $routePrefix = 'admin.services.imei';
        $viewPrefix  = 'imei';

        return view('admin.services.imei.index', compact('rows','apis','routePrefix','viewPrefix'));
    }

    /* =========================
     * لا صفحة create مستقلة
     * ========================= */
    public function create()
    {
        return redirect()->route('admin.services.imei.index');
    }

    /* =========================
     * مودال الإنشاء (Ajax)
     * ========================= */
    public function modalCreate()
    {
        $groups = ServiceGroup::query()->orderBy('name')->get(['id','name']);

        // قيم افتراضية منطقية حسب أعمدة الجدول لديك
        $model = new ImeiService([
            'type'                 => 'imei',
            'active'               => 1,
            'allow_bulk'           => 0,
            'allow_duplicates'     => 0,
            'reply_with_latest'    => 0,
            'allow_cancel'         => 0,
            'reply_expiration'     => 0,
            'profit_type'          => 1, // 1=Credits, 2=Percent
            'cost'                 => 0,
            'profit'               => 0,
            'time'                 => null,
            'source'               => 1, // 1=manual
        ]);

        return view('admin.services.imei._modal_create', compact('model','groups'));
    }

    /* =========================
     * حفظ خدمة جديدة
     * ========================= */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required','string','max:255'],
            'alias'    => ['nullable','regex:/^[a-z0-9\-]+$/','max:255','unique:imei_services,alias'],
            'time'     => ['nullable','string','max:255'],
            'group_id' => ['nullable','integer','exists:service_groups,id'],

            'type'        => ['required','in:imei,server,file'],
            'cost'        => ['required','numeric','min:0'],
            'profit'      => ['required','numeric','min:0'],
            'profit_type' => ['required','in:1,2'], // 1=Credits, 2=Percent

            'source'   => ['required','in:manual,api,supplier,local'],
            'info'     => ['nullable','string'],

            // API (اختياري)
            'api_provider_id'       => ['nullable','integer'],
            'api_service_remote_id' => ['nullable','integer'],

            // سويتشات
            'active'           => ['sometimes','boolean'],
            'allow_bulk'       => ['sometimes','boolean'],
            'allow_duplicates' => ['sometimes','boolean'],
            'reply_expiration' => ['nullable','integer','min:0'],
        ]);

        // سوّيات
        $data['active']           = $request->boolean('active');
        $data['allow_bulk']       = $request->boolean('allow_bulk');
        $data['allow_duplicates'] = $request->boolean('allow_duplicates');

        // تحويل source إلى int لو جدولك يستخدم أرقام
        $sourceMap = ['manual'=>1, 'api'=>2, 'supplier'=>3, 'local'=>4];
        $sourceInt = $sourceMap[$data['source']] ?? 1;

        // توليد alias فريد تلقائيًا إن لم يُرسل
        if (empty($data['alias'])) {
            $base  = Str::slug($data['name']);
            $alias = $base ?: 'svc';
            $i = 2;
            while (DB::table('imei_services')->where('alias', $alias)->exists()) {
                $alias = $base.'-'.$i;
                $i++;
            }
            $data['alias'] = $alias;
        }

        DB::transaction(function () use ($data) {
            ImeiService::create([
                'icon'        => null,
                'alias'       => $data['alias'],
                'name'        => $data['name'],
                'time'        => $data['time'] ?? null,
                'info'        => $data['info'] ?? null,

                'cost'        => round((float)$data['cost'],   2),
                'profit'      => round((float)$data['profit'], 2),
                'profit_type' => (int)$data['profit_type'],

                'main_field'  => null,
                'params'      => null,

                'active'           => $data['active'] ? 1 : 0,
                'allow_bulk'       => $data['allow_bulk'] ? 1 : 0,
                'allow_duplicates' => $data['allow_duplicates'] ? 1 : 0,

                'reply_with_latest'     => 0,
                'allow_report'          => 1,
                'allow_report_time'     => 0,
                'allow_cancel'          => 0,
                'allow_cancel_time'     => 0,
                'use_remote_cost'       => 0,
                'use_remote_price'      => 0,
                'stop_on_api_change'    => 0,
                'needs_approval'        => 0,

                'reply_expiration' => (int)($data['reply_expiration'] ?? 0),
                'expiration_text'  => null,

                'type'        => $data['type'],
                'group_id'    => $data['group_id'] ?? null,
                'source'      => $sourceInt,        // INT
                'remote_id'   => !empty($data['api_service_remote_id']) ? (int)$data['api_service_remote_id'] : null,
                'supplier_id' => null,
                'local_source_id' => null,

                'device_based' => 1,
                'reject_on_missing_reply' => 0,
                'ordering' => 1,
            ]);
        });

        if ($request->ajax()) {
            return response()->noContent(); // 204
        }

        return redirect()->route('admin.services.imei.index')->with('ok','Service created');
    }

    /* =========================
     * مودال التعديل
     * ========================= */
    public function modalEdit(ImeiService $service)
    {
        $groups = ServiceGroup::query()->orderBy('name')->get(['id','name']);

        return view('admin.services.partials._form', [
            'action' => route('admin.services.imei.update', $service->id),
            'method' => 'PUT',
            'record' => $service,
            'groups' => $groups,
        ]);
    }

    /* =========================
     * تحديث خدمة
     * ========================= */
    public function update(Request $request, ImeiService $service)
    {
        $data = $request->validate([
            'name'     => ['required','string','max:255'],
            'alias'    => ['nullable','regex:/^[a-z0-9\-]+$/','max:255','unique:imei_services,alias,'.$service->id],
            'time'     => ['nullable','string','max:255'],
            'group_id' => ['nullable','integer','exists:service_groups,id'],

            'cost'        => ['required','numeric','min:0'],
            'profit'      => ['required','numeric','min:0'],
            'profit_type' => ['required','in:1,2'],

            'info' => ['nullable','string'],

            'active'           => ['sometimes','boolean'],
            'allow_bulk'       => ['sometimes','boolean'],
            'allow_duplicates' => ['sometimes','boolean'],
            'reply_expiration' => ['nullable','integer','min:0'],
        ]);

        $data['active']           = $request->boolean('active');
        $data['allow_bulk']       = $request->boolean('allow_bulk');
        $data['allow_duplicates'] = $request->boolean('allow_duplicates');

        if (empty($data['alias'])) {
            $base  = Str::slug($data['name']);
            $alias = $base ?: 'svc';
            $i = 2;
            while (DB::table('imei_services')->where('alias', $alias)->where('id', '!=', $service->id)->exists()) {
                $alias = $base.'-'.$i;
                $i++;
            }
            $data['alias'] = $alias;
        }

        $service->update([
            'alias'       => $data['alias'],
            'name'        => $data['name'],
            'time'        => $data['time'] ?? null,
            'info'        => $data['info'] ?? null,
            'cost'        => round((float)$data['cost'],   2),
            'profit'      => round((float)$data['profit'], 2),
            'profit_type' => (int)$data['profit_type'],
            'group_id'    => $data['group_id'] ?? null,
            'active'           => $data['active'] ? 1 : 0,
            'allow_bulk'       => $data['allow_bulk'] ? 1 : 0,
            'allow_duplicates' => $data['allow_duplicates'] ? 1 : 0,
            'reply_expiration' => (int)($data['reply_expiration'] ?? 0),
        ]);

        return back()->with('ok','Service updated');
    }

    /* =========================
     * حذف خدمة
     * ========================= */
    public function destroy(ImeiService $service)
    {
        $service->delete();
        return back()->with('ok','Service deleted');
    }

    /* =========================
     * تبديل حالة Active سريعًا
     * ========================= */
    public function toggleActive(ImeiService $service)
    {
        $service->active = $service->active ? 0 : 1;
        $service->save();

        return back()->with('ok', 'Status updated');
    }

    /* =========================
     * JSON لجدول Ajax (اختياري)
     * GET /admin/services/imei/list.json?q=&page=1
     * ========================= */
    public function listJson(Request $request)
    {
        $rows = ImeiService::query()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(function ($z) use ($term) {
                    $z->where('name', 'like', $term)->orWhere('alias','like',$term);
                });
            })
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json([
            'data'  => $rows->items(),
            'total' => $rows->total(),
            'page'  => $rows->currentPage(),
            'per'   => $rows->perPage(),
        ]);
    }
}
