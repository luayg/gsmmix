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
        $groups = RemoteImeiService::query()
            ->where('api_id', $provider->id)   // ✅ FIX
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get()
            ->groupBy('group_name');

        return view('admin.api.providers.imei_services', compact('provider', 'groups'));
    }

    /**
     * ✅ عرض خدمات SERVER من جدول remote_server_services
     */
    public function servicesServer(ApiProvider $provider)
    {
        $groups = RemoteServerService::query()
            ->where('api_id', $provider->id)   // ✅ FIX
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get()
            ->groupBy('group_name');

        return view('admin.api.providers.server_services', compact('provider', 'groups'));
    }

    /**
     * ✅ عرض خدمات FILE من جدول remote_file_services
     */
    public function servicesFile(ApiProvider $provider)
    {
        $groups = RemoteFileService::query()
            ->where('api_id', $provider->id)   // ✅ FIX
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get()
            ->groupBy('group_name');

        return view('admin.api.providers.file_services', compact('provider', 'groups'));
    }

    // إن كان عندك importServices/importServicesWizard خليهم كما هم عندك (لم ألمسهم هنا)
}
