<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Jobs\SyncProviderJob;
use Illuminate\Http\Request;

class ApiProvidersController extends Controller
{
    public function index()
    {
        $providers = ApiProvider::orderBy('id')->get();
        return view('admin.api.providers.index', compact('providers'));
    }

    public function sync(ApiProvider $provider)
    {
        // 🔥 الحل النهائي: Queue
        SyncProviderJob::dispatch($provider->id);

        return back()->with('success', "Sync queued: {$provider->name} (will run in background)");
    }

    /* ===================== SERVICES ===================== */

    public function servicesImei(ApiProvider $provider)
    {
        $groups = RemoteImeiService::where('api_id', $provider->id)
            ->get()
            ->groupBy('group_name');

        return view('admin.api.providers.imei_services', compact('provider','groups'));
    }

    public function servicesServer(ApiProvider $provider)
    {
        $groups = RemoteServerService::where('api_id', $provider->id)
            ->get()
            ->groupBy('group_name');

        return view('admin.api.providers.server_services', compact('provider','groups'));
    }

    public function servicesFile(ApiProvider $provider)
    {
        $groups = RemoteFileService::where('api_id', $provider->id)
            ->get()
            ->groupBy('group_name');

        return view('admin.api.providers.file_services', compact('provider','groups'));
    }
}
