<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

// Models
use App\Models\ServiceGroup;
use App\Models\ImeiService;
use App\Models\ServerService;
use App\Models\FileService;





class ApiProvidersController extends Controller
{
    public function index(Request $request)
    {
        $q      = $request->get('q', '');
        $type   = $request->get('type', '');
        $status = $request->get('status', '');

        $rows = ApiProvider::query()
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->when($type !== '', fn($qq) => $qq->where('type', $type))
            ->when($status !== '', function ($qq) use ($status) {
                // status: synced / not_synced (اختياري حسب UI)
                if ($status === 'synced') $qq->where('synced', 1);
                if ($status === 'not_synced') $qq->where('synced', 0);
            })
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 20))
            ->withQueryString();

        return view('admin.api.providers.index', compact('rows', 'q', 'type', 'status'));
    }

    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required','string','max:255'],
            'type'      => ['required','string','max:50'],
            'url'       => ['nullable','string','max:500'],     // ✅ مهم
            'api_key'   => ['nullable','string','max:500'],
            'username'  => ['nullable','string','max:255'],
            'password'  => ['nullable','string','max:255'],
            'auto_sync' => ['nullable'],
        ]);

        $provider = new ApiProvider();
        $provider->name      = $data['name'];
        $provider->type      = $data['type'];

        // ✅ توحيد الاسم: نخزن URL في عمود url (ولو جدولك اسمه base_url عدله هنا)
        $provider->url       = $data['url'] ?? null;

        $provider->api_key   = $data['api_key'] ?? null;
        $provider->username  = $data['username'] ?? null;
        $provider->password  = $data['password'] ?? null;
        $provider->auto_sync = !empty($data['auto_sync']) ? 1 : 0;

        // synced افتراضيًا No
        $provider->synced    = 0;
        $provider->balance   = 0;

        $provider->save();

        // ✅ Sync تلقائي أول مرة (بالخلفية) إذا auto_sync=Yes
        if ($provider->auto_sync) {
            SyncProviderJob::dispatch($provider->id);
        }

        return redirect()->route('admin.apis.index')->with('success', 'Provider added');
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

    public function update(Request $request, ApiProvider $provider)
    {
        $data = $request->validate([
            'name'      => ['required','string','max:255'],
            'type'      => ['required','string','max:50'],
            'url'       => ['nullable','string','max:500'],     // ✅ مهم
            'api_key'   => ['nullable','string','max:500'],
            'username'  => ['nullable','string','max:255'],
            'password'  => ['nullable','string','max:255'],
            'auto_sync' => ['nullable'],
        ]);

        $provider->name      = $data['name'];
        $provider->type      = $data['type'];
        $provider->url       = $data['url'] ?? null;

        $provider->api_key   = $data['api_key'] ?? null;
        $provider->username  = $data['username'] ?? null;
        $provider->password  = $data['password'] ?? null;

        $provider->auto_sync = !empty($data['auto_sync']) ? 1 : 0;

        // ✅ عند تغيير API، اعتبره غير متزامن حتى يعاد sync
        $provider->synced = 0;

        $provider->save();

        // ✅ Sync تلقائي بعد التعديل إذا auto_sync=Yes
        if ($provider->auto_sync) {
            SyncProviderJob::dispatch($provider->id);
        }

        return redirect()->route('admin.apis.index')->with('success', 'Provider updated');
    }

    public function destroy(ApiProvider $provider)
    {
        $provider->delete();
        return redirect()->route('admin.apis.index')->with('success', 'Provider deleted');
    }

    /**
     * ✅ (FIX) خيارات مزوّدي الـAPI لاستخدامها في المودال (select)
     * Route: admin.apis.options => GET /admin/apis/options
     */
    public function options(Request $request)
    {
        $type = trim((string) $request->get('type', ''));

        $rows = ApiProvider::query()
            ->select(['id', 'name', 'type'])
            ->when($type !== '', fn($q) => $q->where('type', $type))
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'   => $p->id,
                'name' => $p->name,
                'type' => $p->type,
            ])
            ->values();

        return response()->json($rows);
    }

    /**
     * ✅ زر Sync now
     * - لا يعلق الصفحة
     * - يرسل Job
     */
    public function sync(Request $request, ApiProvider $provider)
    {
        // background sync
        SyncProviderJob::dispatch($provider->id);

        // لو AJAX رجّع JSON
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'msg' => 'Sync queued']);
        }

        return back()->with('success', "Sync queued: {$provider->name} (will run in background)");
    }

    /**
     * ✅ عرض خدمات IMEI من جدول remote_imei_services
     * مهم: العمود الصحيح api_id وليس api_provider_id
     */
    public function servicesImei(ApiProvider $provider)
{
    $services = RemoteImeiService::where('api_id', $provider->id)   // ✅ FIX
        ->orderBy('group_name')
        ->orderBy('name')
        ->get()
        ->map(fn ($s) => [
            'GROUPNAME'  => (string)($s->group_name ?? ''),
            'REMOTEID'   => (string)($s->remote_id ?? ''),
            'NAME'       => (string)($s->name ?? ''),
            'CREDIT'     => (float)($s->price ?? $s->credit ?? $s->cost ?? 0),
            'TIME'       => (string)($s->time ?? ''),
        ])
        ->values()
        ->all();

    return view('admin.api.providers.modals.services', [
        'provider' => $provider,
        'kind'     => 'imei',
        'services' => $services,
    ]);
}

public function servicesServer(ApiProvider $provider)
{
    $services = RemoteServerService::where('api_id', $provider->id)  // ✅ FIX
        ->orderBy('group_name')
        ->orderBy('name')
        ->get()
        ->map(fn ($s) => [
            'GROUPNAME'  => (string)($s->group_name ?? ''),
            'REMOTEID'   => (string)($s->remote_id ?? ''),
            'NAME'       => (string)($s->name ?? ''),
            'CREDIT'     => (float)($s->price ?? $s->credit ?? $s->cost ?? 0),
            'TIME'       => (string)($s->time ?? ''),
        ])
        ->values()
        ->all();

    return view('admin.api.providers.modals.services', [
        'provider' => $provider,
        'kind'     => 'server',
        'services' => $services,
    ]);
}

public function servicesFile(ApiProvider $provider)
{
    $services = RemoteFileService::where('api_id', $provider->id)    // ✅ FIX
        ->orderBy('group_name')
        ->orderBy('name')
        ->get()
        ->map(fn ($s) => [
            'GROUPNAME'  => (string)($s->group_name ?? ''),
            'REMOTEID'   => (string)($s->remote_id ?? ''),
            'NAME'       => (string)($s->name ?? ''),
            'CREDIT'     => (float)($s->price ?? $s->credit ?? $s->cost ?? 0),
            'TIME'       => (string)($s->time ?? ''),
        ])
        ->values()
        ->all();

    return view('admin.api.providers.modals.services', [
        'provider' => $provider,
        'kind'     => 'file',
        'services' => $services,
    ]);
}

public function importServicesWizard(Request $request, ApiProvider $provider)
{
    $data = $request->validate([
        'kind'         => 'required|in:imei,server,file',
        'service_ids'  => 'array',
        'service_ids.*'=> 'string',
        'apply_all'    => 'boolean',
        'pricing_mode' => 'required|in:percent,fixed',
        'pricing_value'=> 'nullable|numeric|min:0',
    ]);

    $kind         = $data['kind'];
    $applyAll     = (bool)($data['apply_all'] ?? false);
    $pricingMode  = $data['pricing_mode'];
    $pricingValue = (float)($data['pricing_value'] ?? 0);

    $remoteQ = match ($kind) {
        'imei'   => RemoteImeiService::query(),
        'server' => RemoteServerService::query(),
        'file'   => RemoteFileService::query(),
    };

    // ✅ FIX: الربط الصحيح
    $remoteQ->where('api_id', $provider->id);

    if (!$applyAll) {
        $ids = collect($data['service_ids'] ?? [])->filter()->values()->all();
        if (!count($ids)) {
            return response()->json(['ok'=>false,'msg'=>'No services selected'], 422);
        }
        $remoteQ->whereIn('remote_id', $ids);
    }

    $remoteRows = $remoteQ->get();

    $localModel = match ($kind) {
        'imei'   => ImeiService::class,
        'server' => ServerService::class,
        'file'   => FileService::class,
    };

    $addedRemoteIds = [];
    $count = 0;

    foreach ($remoteRows as $r) {
        $groupName = (string)($r->group_name ?? 'Uncategorized');

        $group = ServiceGroup::firstOrCreate(
            ['type' => $kind, 'name' => $groupName],
            ['slug' => Str::slug($groupName)]
        );

        $cost = (float)($r->price ?? $r->credit ?? $r->cost ?? 0);

        $profitType = ($pricingMode === 'percent') ? 2 : 1;
        $profit     = $pricingValue;

        $payload = [
            'name'        => (string)($r->name ?? ''),
            'alias'       => Str::slug((string)($r->name ?? 'service')),
            'supplier_id' => $provider->id,
            'remote_id'   => (string)($r->remote_id ?? ''),
            'group_id'    => $group->id,
            'cost'        => $cost,
            'profit'      => $profit,
            'profit_type' => $profitType,
            'time'        => (string)($r->time ?? ''),
            'source'      => 2,
            'active'      => 1,
        ];

        $localModel::updateOrCreate(
            ['supplier_id' => $provider->id, 'remote_id' => (string)($r->remote_id ?? '')],
            $payload
        );

        $addedRemoteIds[] = (string)($r->remote_id ?? '');
        $count++;
    }

    return response()->json([
        'ok'               => true,
        'count'            => $count,
        'added_remote_ids' => $addedRemoteIds,
    ]);
}

}
