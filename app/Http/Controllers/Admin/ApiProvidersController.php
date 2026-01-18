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
        $q      = trim((string) $request->get('q', ''));
        $type   = trim((string) $request->get('type', ''));
        $status = trim((string) $request->get('status', ''));
        $perPage = (int) $request->get('per_page', 20);
        if ($perPage <= 0) $perPage = 20;

        $query = ApiProvider::query();

        if ($q !== '') {
            $query->where(function($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                   ->orWhere('type', 'like', "%{$q}%")
                   ->orWhere('id', (int)$q);
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($status !== '') {
            // حسب ما هو ظاهر في الـBlade "Active/Inactive"
            if ($status === 'Active') {
                $query->where('active', 1);
            } elseif ($status === 'Inactive') {
                $query->where('active', 0);
            }
        }

        // ✅ أهم تعديل: الواجهة تتوقع $rows
        $rows = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return view('admin.api.providers.index', compact('rows'));
    }

    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'type'      => 'required|string|max:50',
            'base_url'  => 'nullable|string|max:255',
            'api_key'   => 'nullable|string|max:255',
            'username'  => 'nullable|string|max:255',
            'password'  => 'nullable|string|max:255',
            'active'    => 'nullable|boolean',
            'auto_sync' => 'nullable|boolean',
        ]);

        $data['active']    = (bool)($data['active'] ?? 0);
        $data['auto_sync'] = (bool)($data['auto_sync'] ?? 0);

        ApiProvider::create($data);

        return redirect()->route('admin.apis.index')->with('ok', 'API created');
    }

    public function view(ApiProvider $provider)
    {
        return view('admin.api.providers.view', compact('provider'));
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

    public function update(Request $request, ApiProvider $provider)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'type'      => 'required|string|max:50',
            'base_url'  => 'nullable|string|max:255',
            'api_key'   => 'nullable|string|max:255',
            'username'  => 'nullable|string|max:255',
            'password'  => 'nullable|string|max:255',
            'active'    => 'nullable|boolean',
            'auto_sync' => 'nullable|boolean',
        ]);

        $data['active']    = (bool)($data['active'] ?? 0);
        $data['auto_sync'] = (bool)($data['auto_sync'] ?? 0);

        $provider->update($data);

        return redirect()->route('admin.apis.index')->with('ok', 'API updated');
    }

    public function destroy(ApiProvider $provider)
    {
        $provider->delete();
        return redirect()->route('admin.apis.index')->with('ok', 'API deleted');
    }

    public function options()
    {
        $rows = ApiProvider::orderBy('name')->get(['id','name','type']);
        return response()->json($rows);
    }

    public function sync(ApiProvider $provider)
    {
        // ✅ يضعه بالطابور
        SyncProviderJob::dispatch($provider->id);

        return redirect()
            ->route('admin.apis.index')
            ->with('ok', "Sync queued: {$provider->name} (will run in background)");
    }

    // ✅ خدمات IMEI
    public function servicesImei(ApiProvider $provider)
    {
        // جلب من الجداول المحلية (بعد syncCatalog)
        $services = RemoteImeiService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        // Grouped by group_name
        $groups = $services->groupBy(fn($s) => (string)($s->group_name ?? ''));

        return view('admin.api.providers.imei_services', compact('provider', 'groups'));
    }

    // ✅ خدمات SERVER
    public function servicesServer(ApiProvider $provider)
    {
        $services = RemoteServerService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $groups = $services->groupBy(fn($s) => (string)($s->group_name ?? ''));

        return view('admin.api.providers.server_services', compact('provider', 'groups'));
    }

    // ✅ خدمات FILE
    public function servicesFile(ApiProvider $provider)
    {
        $services = RemoteFileService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $groups = $services->groupBy(fn($s) => (string)($s->group_name ?? ''));

        return view('admin.api.providers.file_services', compact('provider', 'groups'));
    }

    // (لو عندك importServices / importServicesWizard خليهم كما هم عندك — لم أغيرهم هنا)
}
