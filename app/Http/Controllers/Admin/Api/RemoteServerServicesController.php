<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\RemoteServerService;
use App\Models\ServerService;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        // ajax => modal view
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.api.remote.server.modal', compact('provider', 'groups', 'existing'));
        }

        // normal => page view
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

        // ajax => modal import wizard
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.api.remote.server.import_modal', compact('provider', 'services', 'existing'));
        }

        /**
         * ✅ IMPORTANT:
         * كان المشروع يرمي View اسمها admin.api.remote.server.import
         * والملف غير موجود -> View not found.
         * الآن سنجعلها صفحة كاملة داخل layout.admin
         */
        return view('admin.api.remote.server.import', compact('provider', 'services', 'existing'));
    }

    /**
     * JSON endpoint (route موجود في routes/admin_apis.php)
     */
    public function servicesJson(ApiProvider $provider, Request $request)
    {
        $col = $this->remoteLinkColumn();

        $rows = DB::table('remote_server_services')
            ->where($col, $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn($v) => (string)$v)
            ->flip()
            ->all();

        return response()->json([
            'ok' => true,
            'provider_id' => (int)$provider->id,
            'rows' => $rows,
            'existing_remote_ids' => array_keys($existing),
        ]);
    }

    /**
     * Import selected / Import all
     * يستقبل نفس payload الموجود في import_modal.blade.php:
     * { apply_all: bool, service_ids: [], pricing_mode: fixed|percent, pricing_value: number }
     * :contentReference[oaicite:2]{index=2}
     */
    public function import(ApiProvider $provider, Request $request)
    {
        $applyAll = (bool)$request->boolean('apply_all', false);

        $ids = $request->input('service_ids', []);
        if (is_string($ids)) $ids = array_filter(array_map('trim', explode(',', $ids)));
        if (!is_array($ids)) $ids = [];

        if (!$applyAll && count($ids) === 0) {
            return response()->json(['ok' => false, 'msg' => 'service_ids is required'], 422);
        }

        $pricingMode = strtolower(trim((string)$request->input('pricing_mode', 'fixed')));
        if (!in_array($pricingMode, ['fixed', 'percent'], true)) $pricingMode = 'fixed';

        $pricingValue = (float)$request->input('pricing_value', 0);

        $col = $this->remoteLinkColumn();

        // اجلب خدمات الريموت (إما all أو المحددة)
        $remoteQ = RemoteServerService::query()->where($col, $provider->id);
        if (!$applyAll) {
            $remoteIds = array_values(array_unique(array_map('strval', $ids)));
            $remoteQ->whereIn('remote_id', $remoteIds);
        }
        $remoteRows = $remoteQ->orderBy('group_name')->orderBy('remote_id')->get();

        $added = [];
        $count = 0;

        DB::transaction(function () use ($provider, $remoteRows, $pricingMode, $pricingValue, &$added, &$count) {

            foreach ($remoteRows as $r) {
                $remoteId = (string)($r->remote_id ?? '');
                if ($remoteId === '') continue;

                // امنع التكرار
                $exists = ServerService::query()
                    ->where('supplier_id', $provider->id)
                    ->where('remote_id', $remoteId)
                    ->exists();
                if ($exists) continue;

                // group
                $groupName = trim((string)($r->group_name ?? ''));
                $groupId = null;
                if ($groupName !== '') {
                    $group = ServiceGroup::firstOrCreate(
                        ['type' => 'server_service', 'name' => $groupName],
                        ['ordering' => 0]
                    );
                    $groupId = $group->id;
                }

                // name/time/info
                $nameText = trim(strip_tags((string)($r->name ?? '')));
                if ($nameText === '') $nameText = "server-{$provider->id}-{$remoteId}";

                $timeText = trim(strip_tags((string)($r->time ?? '')));
                $infoText = trim(strip_tags((string)($r->info ?? '')));

                $nameJson = json_encode(['en' => $nameText, 'fallback' => $nameText], JSON_UNESCAPED_UNICODE);
                $timeJson = json_encode(['en' => $timeText, 'fallback' => $timeText], JSON_UNESCAPED_UNICODE);
                $infoJson = json_encode(['en' => $infoText, 'fallback' => $infoText], JSON_UNESCAPED_UNICODE);

                // pricing
                $cost = (float)($r->price ?? 0);
                $profitType = ($pricingMode === 'percent') ? 2 : 1;

                // alias
                $aliasBase = Str::slug(Str::limit($nameText, 160, ''), '-');
                if ($aliasBase === '') $aliasBase = 'service';
                $alias = $aliasBase . '-' . $provider->id . '-' . $remoteId;

                // main_field (افتراضي Serial)
                $mainField = json_encode([
                    'type'  => 'serial',
                    'rules' => [
                        'allowed' => 'any',
                        'minimum' => '1',
                        'maximum' => '50',
                        'label'   => ['en' => 'Serial', 'fallback' => 'Serial'],
                    ],
                ], JSON_UNESCAPED_UNICODE);

                $data = [
                    'alias' => $alias,
                    'group_id' => $groupId,
                    'type' => 'server',
                    'name' => $nameJson,
                    'time' => $timeJson,
                    'info' => $infoJson,

                    'cost' => $cost,
                    'profit' => $pricingValue,
                    'profit_type' => $profitType,

                    'source' => 2,
                    'supplier_id' => $provider->id,
                    'remote_id' => $remoteId,

                    'main_field' => $mainField,

                    'active' => 1,
                    'allow_bulk' => 0,
                    'allow_duplicates' => 0,
                    'reply_with_latest' => 0,
                    'allow_report' => 0,
                    'allow_cancel' => 0,
                    'reply_expiration' => 0,
                ];

                ServerService::query()->create($data);

                $added[] = $remoteId;
                $count++;
            }
        });

        return response()->json([
            'ok' => true,
            'count' => $count,
            'added_remote_ids' => $added,
        ]);
    }
}