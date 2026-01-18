<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use Illuminate\Http\Request;

class ApiProvidersController extends Controller
{
    /**
     * ✅ صفحة مزودي الـAPI (حل مشكلة Undefined variable $rows)
     */
    public function index(Request $request)
    {
        $q      = trim((string)$request->get('q', ''));
        $type   = trim((string)$request->get('type', ''));
        $status = trim((string)$request->get('status', ''));

        $rows = ApiProvider::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('url', 'like', "%{$q}%")
                      ->orWhere('username', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', fn($qq) => $qq->where('type', $type))
            ->when($status !== '', function ($qq) use ($status) {
                // status يمكن تستعمله كـ active/synced لو عندك فلتر بالواجهة
                if ($status === 'active') $qq->where('active', 1);
                if ($status === 'inactive') $qq->where('active', 0);
                if ($status === 'synced') $qq->where('synced', 1);
                if ($status === 'not_synced') $qq->where('synced', 0);
            })
            ->orderBy('id', 'desc')
            ->paginate((int)($request->get('per_page', 20)))
            ->appends($request->query());

        // ✅ مهم: تمرير $rows للـblade
        return view('admin.api.providers.index', compact('rows', 'q', 'type', 'status'));
    }

    /**
     * ✅ زر Sync now (بدون تعليق الصفحة)
     * يضع synced=0 ويشغّل Job بالخلفية
     */
    public function sync(Request $request, ApiProvider $provider)
    {
        // ضع synced = 0 قبل البدء حتى يظهر Not Synced أثناء التنفيذ
        $provider->update([
            'synced'  => 0,
            'syncing' => 1, // لو عندك العمود (إن لم يوجد احذفه من هنا)
        ]);

        // تشغيل بالخلفية
        SyncProviderJob::dispatch($provider->id);

        return back()->with('success', "Sync queued: {$provider->name} (will run in background)");
    }

    /**
     * ✅ مزامنة الرصيد فقط (اختياري)
     */
    public function syncBalance(Request $request, ApiProvider $provider)
    {
        $provider->update(['synced' => 0, 'syncing' => 1]);

        SyncProviderJob::dispatch($provider->id, 'balance');

        return back()->with('success', "Balance sync queued: {$provider->name}");
    }

    /**
     * ✅ عرض خدمات IMEI لمزود
     */
    public function servicesImei(ApiProvider $provider)
    {
        $services = RemoteImeiService::query()
            ->where('api_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->paginate(50);

        return view('admin.api.providers.services_imei', compact('provider', 'services'));
    }

    /**
     * ✅ عرض خدمات SERVER لمزود (حل خطأ servicesServer does not exist)
     */
    public function servicesServer(ApiProvider $provider)
    {
        $services = RemoteServerService::query()
            ->where('api_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->paginate(50);

        return view('admin.api.providers.services_server', compact('provider', 'services'));
    }

    /**
     * ✅ عرض خدمات FILE لمزود
     */
    public function servicesFile(ApiProvider $provider)
    {
        $services = RemoteFileService::query()
            ->where('api_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->paginate(50);

        return view('admin.api.providers.services_file', compact('provider', 'services'));
    }
}
