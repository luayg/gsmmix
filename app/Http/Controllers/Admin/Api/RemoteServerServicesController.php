<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ServerService;

class RemoteServerServicesController extends Controller
{
    // صفحة الريموت (Clone فقط + زر يودّيك لصفحة Import)
    public function index(ApiProvider $provider)
    {
        // 1) هات خدمات الريموت من الجدول عندك (remote_server_services أو حسب اسمك)
        // ملاحظة: عدّل اسم الموديل/الجدول حسب مشروعك
        $groups = \DB::table('remote_server_services')
            ->where('supplier_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get()
            ->groupBy('group_name');

        // 2) existing لمنع التكرار
        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn($v)=>(string)$v)
            ->flip()
            ->all();

        return view('admin.api.remote.server.index', compact('provider','groups','existing'));
    }

    // صفحة Import مستقلة (Wizard لحاله)
    public function importPage(ApiProvider $provider)
    {
        // نفس مصدر الريموت
        $services = \DB::table('remote_server_services')
            ->where('supplier_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn($v)=>(string)$v)
            ->flip()
            ->all();

        return view('admin.api.remote.server.import', compact('provider','services','existing'));
    }

    // POST Import (يبقى نفس منطقك الحالي، بس الآن أصبح منفصل)
    public function import(ApiProvider $provider)
    {
        $data = request()->all();

        // TODO: هنا انقل نفس منطق import_wizard / import_selected الموجود عندك حالياً
        // الفكرة: يضيف services محلياً ويعمل return json { ok:true, count:..., added_remote_ids:[...] }

        return response()->json([
            'ok' => false,
            'msg' => 'Import logic not moved here yet.',
        ], 422);
    }

    public function servicesJson(ApiProvider $provider)
    {
        $services = \DB::table('remote_server_services')
            ->where('supplier_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        return response()->json($services);
    }
}
