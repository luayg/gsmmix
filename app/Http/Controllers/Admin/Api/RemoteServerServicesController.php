<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class RemoteServerServicesController extends Controller
{
    /**
     * في بعض قواعد البيانات العمود يكون api_provider_id أو api_id (legacy)
     * فنعمل detect ديناميكي.
     */
    private function remoteLinkColumn(): string
    {
        $cols = DB::getSchemaBuilder()->getColumnListing('remote_server_services');
        return in_array('api_provider_id', $cols, true) ? 'api_provider_id' : 'api_id';
    }

    public function index(ApiProvider $provider, Request $request)
    {
        $col = $this->remoteLinkColumn();

        $rows = DB::table('remote_server_services')
            ->where($col, $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $groups = $rows->groupBy('group_name');

        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn ($v) => (string)$v)
            ->flip()
            ->all();

        // ✅ لو كان الطلب Ajax/Modal رجّع modal
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.api.remote.server.modal', compact('provider', 'groups', 'existing'));
        }

        // ✅ غير ذلك رجّع page view
        return view('admin.api.remote.server.page', compact('provider', 'groups', 'existing'));
    }


    public function importPage(ApiProvider $provider, Request $request)
    {
        // (لو عندك import views موجودة اتركها، وإلا نعملها لاحقًا)
        $col = $this->remoteLinkColumn();

        $services = DB::table('remote_server_services')
            ->where($col, $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn($v) => (string)$v)
            ->flip()
            ->all();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.api.remote.server.import_modal', compact('provider', 'services', 'existing'));
        }

        // إذا ملف import غير موجود عندك، أنشئه بنفس أسلوب page.blade.php لاحقًا
        return view('admin.api.remote.server.import', compact('provider', 'services', 'existing'));
    }
}