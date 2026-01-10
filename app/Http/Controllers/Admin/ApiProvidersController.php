<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Services\Api\DhruClient;
use App\Models\ServiceGroup;
use Illuminate\Support\Str;

class ApiProvidersController extends Controller
{
    public function index(Request $r)
    {
        $qText   = trim((string) $r->input('q', ''));
        $type    = trim((string) $r->input('type', ''));
        $status  = trim((string) $r->input('status', ''));
        $perPage = (int) $r->input('per_page', 20);
        if (!in_array($perPage, [10, 20, 25, 50, 100], true)) $perPage = 20;

        $q = ApiProvider::query()->orderBy('id');

        if ($type !== '') $q->where('type', $type);

        if ($status !== '') {
            $statusBool = strtolower($status) === 'active';
            $q->where('active', $statusBool);
        }

        if ($qText !== '') {
            $q->where(function ($qq) use ($qText) {
                $qq->where('name', 'like', "%{$qText}%")
                   ->orWhere('url', 'like', "%{$qText}%")
                   ->orWhere('username', 'like', "%{$qText}%");
            });
        }

        $rows = $q->paginate($perPage)->appends([
            'q' => $qText, 'type' => $type, 'status' => $status, 'per_page' => $perPage,
        ]);

        // رصيد DHRU (كاش)
        $rows->getCollection()->transform(function ($p) {
            if (strtolower($p->type) !== 'dhru' || !$p->active) {
                $p->balance = $p->balance ?? 0;
                return $p;
            }
            $key = "api:dhru:balance:{$p->id}";
            $bal = Cache::remember($key, 600, function () use ($p) {
                try {
                    $client = new DhruClient($p->url, (string)$p->username, (string)$p->api_key);
                    $acc    = $client->accountInfo();
                    $last   = (float)($acc['credits'] ?? 0);
                    if ($last > 0 || ($acc['currency'] ?? null) !== null) {
                        $p->balance = $last;
                        $p->save();
                        return $last;
                    }
                } catch (\Throwable $e) {}
                return (float)($p->balance ?? 0);
            });
            $p->balance = $bal;
            return $p;
        });

        return view('admin.api.providers.index', compact('rows'));
    }

    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'               => 'required',
            'type'               => 'required|in:dhru,webx,gsmhub,unlockbase,simple_link',
            'url'                => 'required',
            'username'           => 'nullable',
            'api_key'            => 'nullable',
            'sync_imei'          => 'boolean',
            'sync_server'        => 'boolean',
            'sync_file'          => 'boolean',
            'ignore_low_balance' => 'boolean',
            'auto_sync'          => 'boolean',
            'active'             => 'boolean',
        ]);

        ApiProvider::create($data);
        return redirect()->route('admin.apis.index')->with('ok', 'API added');
    }

    public function update(Request $r, ApiProvider $provider)
    {
        $data = $r->validate([
            'name'               => 'required',
            'type'               => 'required|in:dhru,webx,gsmhub,unlockbase,simple_link',
            'url'                => 'required',
            'username'           => 'nullable',
            'api_key'            => 'nullable',
            'sync_imei'          => 'boolean',
            'sync_server'        => 'boolean',
            'sync_file'          => 'boolean',
            'ignore_low_balance' => 'boolean',
            'auto_sync'          => 'boolean',
            'active'             => 'boolean',
        ]);

        $provider->update($data);
        Cache::forget("api:dhru:balance:{$provider->id}");
        return back()->with('ok', 'Saved');
    }

    public function destroy(ApiProvider $provider)
    {
        $provider->delete();
        return back()->with('ok', 'Deleted');
    }

    /**
     * ✅ Bulk Import (من صفحة IMEI/Server/File services)
     * يعمل على remote_* table ويدخل على *_services table (Service Management)
     */
    public function importServices(Request $request, ApiProvider $provider)
    {
        $data = $request->validate([
            'kind'          => 'required|in:imei,server,file',
            'service_ids'   => 'nullable|array',
            'service_ids.*' => 'string',
            'apply_all'     => 'nullable|boolean',
            'group_mode'    => 'required|in:pick,auto',
            'group_id'      => 'nullable|integer',
            'pricing_mode'  => 'required|in:percent,fixed',
            'pricing_value' => 'required|numeric|min:0',
        ]);

        $kind         = $data['kind'];
        $applyAll     = (bool)($data['apply_all'] ?? false);
        $pricingMode  = $data['pricing_mode'];
        $pricingValue = (float)$data['pricing_value'];

        // ✅ Remote model حسب النوع
        $remoteModel = match ($kind) {
            'server' => RemoteServerService::class,
            'file'   => RemoteFileService::class,
            default  => RemoteImeiService::class,
        };

        // ✅ Local model حسب النوع
        $localModel = match ($kind) {
            'server' => \App\Models\ServerService::class,
            'file'   => \App\Models\FileService::class,
            default  => \App\Models\ImeiService::class,
        };

        // ✅ Query remote by api_id
        $q = $remoteModel::query()->where('api_id', $provider->id);

        if (!$applyAll) {
            $ids = $data['service_ids'] ?? [];
            if (empty($ids)) {
                return response()->json(['ok' => false, 'msg' => 'No services selected'], 422);
            }
            $q->whereIn('remote_id', $ids);
        }

        $remoteServices = $q->get();

        if ($remoteServices->isEmpty()) {
            return response()->json(['ok' => false, 'msg' => 'No services found'], 404);
        }

        $added = 0;
        $updated = 0;

        foreach ($remoteServices as $s) {
            $baseCost = (float)($s->price ?? 0);

            // ✅ profit + profit_type
            if ($pricingMode === 'percent') {
                $profit = ($baseCost * $pricingValue) / 100;
                $profitType = 2; // percent
            } else {
                $profit = $pricingValue;
                $profitType = 1; // fixed
            }

            // ✅ Group mode
            if ($data['group_mode'] === 'auto') {
                $groupName = trim((string)($s->group_name ?: 'Default'));
                $group = ServiceGroup::firstOrCreate([
                    'type' => $kind,
                    'name' => $groupName,
                ], [
                    'active' => 1
                ]);
                $groupId = $group->id;
            } else {
                $groupId = (int)$data['group_id'];
            }

            // ✅ payload (لا يوجد api_id ولا price)
            $payload = [
                'supplier_id' => $provider->id,
                'group_id'    => $groupId,
                'remote_id'   => (int)$s->remote_id,
                'alias'       => (string)$s->remote_id,
                'name'        => (string)$s->name,
                'time'        => (string)$s->time,
                'info'        => (string)$s->info,
                'cost'        => $baseCost,
                'profit'      => (float)$profit,
                'profit_type' => $profitType,
                'active'      => 1,
            ];

            // ✅ Update if exists (remote_id + supplier_id)
            $row = $localModel::query()
                ->where('remote_id', (int)$s->remote_id)
                ->where('supplier_id', $provider->id)
                ->first();

            if ($row) {
                $row->update($payload);
                $updated++;
            } else {
                $localModel::create($payload);
                $added++;
            }
        }

        return response()->json([
            'ok'      => true,
            'count'   => $added,
            'updated' => $updated,
            'msg'     => "Imported: {$added}, Updated: {$updated}",
        ]);
    }

    /**
     * ✅ Wizard Import (Step 1 Select, Step 2 Pricing, Step 3 Finish)
     */
    public function importServicesWizard(Request $r, ApiProvider $provider)
{
    try {

        $data = $r->validate([
            'kind'          => 'required|in:imei,server,file',
            'apply_all'     => 'required|boolean',
            'service_ids'   => 'array',
            'service_ids.*' => 'string',
            'pricing_mode'  => 'required|in:percent,fixed',
            'pricing_value' => 'required|numeric|min:0',
        ]);

        $kind         = $data['kind'];
        $applyAll     = (bool)$data['apply_all'];
        $serviceIds   = $data['service_ids'] ?? [];
        $pricingMode  = $data['pricing_mode'];
        $pricingValue = (float)$data['pricing_value'];

        $remoteModel = match ($kind) {
            'server' => RemoteServerService::class,
            'file'   => RemoteFileService::class,
            default  => RemoteImeiService::class,
        };

        $localModel = match ($kind) {
            'server' => \App\Models\ServerService::class,
            'file'   => \App\Models\FileService::class,
            default  => \App\Models\ImeiService::class,
        };

        $q = $remoteModel::query()->where('api_id', $provider->id);

        if (!$applyAll) {
            if (empty($serviceIds)) {
                return response()->json(['ok' => false, 'msg' => 'No services selected'], 422);
            }
            $q->whereIn('remote_id', $serviceIds);
        }

        $rows = $q->get();

        if ($rows->isEmpty()) {
            return response()->json(['ok' => false, 'msg' => 'No remote services found'], 404);
        }

        $added   = 0;
        $updated = 0;

        foreach ($rows as $srv) {

            $remoteId = (int)$srv->remote_id;  // ✅ اجبار remote_id يكون int
            $baseCost = (float)($srv->price ?? 0);

            // ✅ profit
            if ($pricingMode === 'percent') {
                $profit     = ($baseCost * $pricingValue) / 100;
                $profitType = 2;
            } else {
                $profit     = $pricingValue;
                $profitType = 1;
            }

            // ✅ group
            $groupName = trim((string)($srv->group_name ?: 'Uncategorized'));

            $group = ServiceGroup::firstOrCreate([
                'type' => $kind,
                'name' => $groupName,
            ], [
                'active' => 1
            ]);

            $payload = [
                'supplier_id' => $provider->id,
                'group_id'    => $group->id,
                'remote_id'   => $remoteId,
                'alias'       => (string)$remoteId,
                'name'        => (string)$srv->name,
                'time'        => (string)$srv->time,
                'info'        => (string)$srv->info,
                'cost'        => $baseCost,
                'profit'      => (float)$profit,
                'profit_type' => $profitType,
                'active'      => 1,
            ];

            // ✅ updateOrCreate يحدد هل Create أو Update بشكل صحيح
            $row = $localModel::updateOrCreate(
                [
                    'supplier_id' => $provider->id,
                    'remote_id'   => $remoteId
                ],
                $payload
            );

            // ✅ إذا تم إنشاؤه جديد
            if ($row->wasRecentlyCreated) $added++;
            else $updated++;
        }

        return response()->json([
            'ok'      => true,
            'count'   => $added,     // ✅ added الصحيح
            'updated' => $updated,
            'msg'     => "Imported: {$added}, Updated: {$updated}",
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'ok'  => false,
            'msg' => $e->getMessage(),
        ], 500);
    }
}


    /* ====================== Modals ====================== */

    public function view(ApiProvider $provider)
    {
        $info = null;
        if (strtolower($provider->type) === 'dhru' && $provider->active) {
            try {
                $client = new DhruClient($provider->url, (string)$provider->username, (string)$provider->api_key);
                $acc    = $client->accountInfo();
                $last   = (float)($acc['credits'] ?? 0);
                if ($last > 0 || ($acc['currency'] ?? null) !== null) {
                    $provider->balance = $last;
                    $provider->save();
                    Cache::put("api:dhru:balance:{$provider->id}", $last, 600);
                }
                $info = $acc;
            } catch (\Throwable $e) {
                $info = ['error' => $e->getMessage()];
            }
        }
        return view('admin.api.providers.modals.view', compact('provider', 'info'));
    }

    public function servicesImei(ApiProvider $provider)
    {
        $services = RemoteImeiService::query()
            ->where('api_id', $provider->id)
            ->orderBy('group_name')->orderBy('name')
            ->get()
            ->map(function ($s) {
                return [
                    'SERVICEID'   => $s->remote_id,
                    'SERVICENAME' => $s->name,
                    'CREDIT'      => $s->price,
                    'TIME'        => $s->time,
                    'INFO'        => $s->info,
                    'GROUPNAME'   => $s->group_name,
                ];
            })->toArray();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'title'    => "IMEI services — {$provider->name}",
            'services' => $services,
            'kind'     => 'imei',
        ]);
    }

    public function servicesServer(ApiProvider $provider)
    {
        $services = RemoteServerService::query()
            ->where('api_id', $provider->id)
            ->orderBy('group_name')->orderBy('name')
            ->get()
            ->map(function ($s) {
                return [
                    'SERVICEID'   => $s->remote_id,
                    'SERVICENAME' => $s->name,
                    'CREDIT'      => $s->price,
                    'TIME'        => $s->time,
                    'INFO'        => $s->info,
                    'GROUPNAME'   => $s->group_name,
                ];
            })->toArray();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'title'    => "Server services — {$provider->name}",
            'services' => $services,
            'kind'     => 'server',
        ]);
    }

    public function servicesFile(ApiProvider $provider)
    {
        $services = RemoteFileService::query()
            ->where('api_id', $provider->id)
            ->orderBy('group_name')->orderBy('name')
            ->get()
            ->map(function ($s) {
                return [
                    'SERVICEID'   => $s->remote_id,
                    'SERVICENAME' => $s->name,
                    'CREDIT'      => $s->price,
                    'TIME'        => $s->time,
                    'INFO'        => $s->info,
                    'ALLOWED'     => $s->allowed_extensions,
                    'GROUPNAME'   => $s->group_name,
                ];
            })->toArray();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'title'    => "File services — {$provider->name}",
            'services' => $services,
            'kind'     => 'file',
        ]);
    }

    public function options()
    {
        $rows = \DB::table('api_providers')->select('id', 'name')->orderBy('name')->get();
        return response()->json($rows);
    }

    public function sync(ApiProvider $provider)
    {
        Artisan::call('dhru:sync', ['--provider' => [$provider->id]]);
        return back()->with('ok', "Sync queued for {$provider->name}");
    }
}
