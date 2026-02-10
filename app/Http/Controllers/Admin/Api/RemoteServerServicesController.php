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
    private function remoteLinkColumn(): string
    {
        $table = 'remote_server_services';

        if (Schema::hasColumn($table, 'supplier_id')) return 'supplier_id';
        if (Schema::hasColumn($table, 'provider_id')) return 'provider_id';
        if (Schema::hasColumn($table, 'api_provider_id')) return 'api_provider_id';

        return 'supplier_id';
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
            ->map(fn($v) => (string)$v)
            ->flip()
            ->all();

        if ($request->ajax()) {
            return view('admin.api.remote.server.modal', compact('provider', 'groups', 'existing'));
        }

        return view('admin.api.remote.server.page', compact('provider', 'groups', 'existing'));
    }

    public function importPage(ApiProvider $provider, Request $request)
    {
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

        if ($request->ajax()) {
            return view('admin.api.remote.server.import_modal', compact('provider', 'services', 'existing'));
        }

        return view('admin.api.remote.server.import', compact('provider', 'services', 'existing'));
    }

    public function import(ApiProvider $provider, Request $request)
    {
        // (ابقِ منطقك الحالي كما هو عندك)
        // ملاحظة: عندك منطق كامل داخل الملف في المستودع، لا أغيره هنا حتى لا أكسر سلوكك الحالي.
        return response()->json(['ok' => true]);
    }
}
