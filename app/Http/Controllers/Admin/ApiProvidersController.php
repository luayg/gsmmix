<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use Illuminate\Http\Request;

class ApiProvidersController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string)$request->get('q', ''));
        $type   = trim((string)$request->get('type', ''));
        $status = trim((string)$request->get('status', '')); // optional: synced/active filters
        $perPage = (int)$request->get('per_page', 20);
        if ($perPage <= 0) $perPage = 20;
        if ($perPage > 200) $perPage = 200;

        $query = ApiProvider::query();

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                   ->orWhere('url', 'like', "%{$q}%")
                   ->orWhere('username', 'like', "%{$q}%");
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($status === 'synced') {
            $query->where('synced', 1);
        } elseif ($status === 'not_synced') {
            $query->where('synced', 0);
        } elseif ($status === 'active') {
            $query->where('active', 1);
        } elseif ($status === 'inactive') {
            $query->where('active', 0);
        }

        $rows = $query->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        // IMPORTANT: view عندك يحتاج $rows (ظهر عندك Undefined variable $rows)
        return view('admin.api.providers.index', compact('rows'));
    }

    public function store(Request $request)
    {
        $data = $this->validateProvider($request);

        $p = new ApiProvider();
        $p->name = $data['name'];
        $p->type = $data['type'];
        $p->url = $data['url'];
        $p->username = $data['username'] ?? null;
        $p->api_key = $data['api_key'] ?? null;

        $p->sync_imei = (int)($data['sync_imei'] ?? 0);
        $p->sync_server = (int)($data['sync_server'] ?? 0);
        $p->sync_file = (int)($data['sync_file'] ?? 0);

        $p->auto_sync = (int)($data['auto_sync'] ?? 0);
        $p->ignore_low_balance = (int)($data['ignore_low_balance'] ?? 0);
        $p->active = (int)($data['active'] ?? 1);

        $p->synced = 0; // أول ما ينضاف يكون Not synced إلى أن يكتمل أول Sync
        $p->balance = 0;

        $p->save();

        // ✅ auto first sync immediately (in background)
        $this->dispatchSync($p);

        return redirect()->back()->with('success', "Provider created. Sync queued: {$p->name}");
    }

    public function update(Request $request, ApiProvider $provider)
    {
        $data = $this->validateProvider($request, $provider->id);

        $provider->name = $data['name'];
        $provider->type = $data['type'];
        $provider->url = $data['url'];
        $provider->username = $data['username'] ?? null;
        $provider->api_key = $data['api_key'] ?? null;

        $provider->sync_imei = (int)($data['sync_imei'] ?? 0);
        $provider->sync_server = (int)($data['sync_server'] ?? 0);
        $provider->sync_file = (int)($data['sync_file'] ?? 0);

        $provider->auto_sync = (int)($data['auto_sync'] ?? 0);
        $provider->ignore_low_balance = (int)($data['ignore_low_balance'] ?? 0);
        $provider->active = (int)($data['active'] ?? 1);

        // تغيير API Key/URL يعني لازم نرجع Not synced
        $provider->synced = 0;

        $provider->save();

        // ✅ إذا Auto Sync ON أو المستخدم عدّل البيانات -> نسوي Sync مباشرة
        $this->dispatchSync($provider);

        return redirect()->back()->with('success', "Provider updated. Sync queued: {$provider->name}");
    }

    public function destroy(ApiProvider $provider)
    {
        // تنظيف الخدمات المرتبطة (اختياري)
        RemoteImeiService::where('api_id', $provider->id)->delete();
        RemoteServerService::where('api_id', $provider->id)->delete();
        RemoteFileService::where('api_id', $provider->id)->delete();

        $provider->delete();

        return redirect()->back()->with('success', 'Provider deleted.');
    }

    /**
     * زر Sync now (ScienceNow)
     * لا تنفذ sync داخل الويب أبداً (حتى لا تتجمد الصفحة).
     */
    public function sync(Request $request, ApiProvider $provider)
    {
        // إذا حبيت لاحقاً تعمل sync لنوع واحد:
        // $onlyType = $request->get('type'); // imei/server/file
        $onlyType = null;

        $provider->synced = 0;
        $provider->save();

        SyncProviderJob::dispatch($provider->id, $onlyType)
            ->onQueue('providers');

        return redirect()->back()->with('success', "Sync queued: {$provider->name} (will run in background)");
    }

    // ==========================
    // Services pages / ajax
    // ==========================

    public function servicesImei(Request $request, ApiProvider $provider)
    {
        return $this->servicesList($request, $provider, 'imei');
    }

    public function servicesServer(Request $request, ApiProvider $provider)
    {
        return $this->servicesList($request, $provider, 'server');
    }

    public function servicesFile(Request $request, ApiProvider $provider)
    {
        return $this->servicesList($request, $provider, 'file');
    }

    protected function servicesList(Request $request, ApiProvider $provider, string $type)
    {
        $q = trim((string)$request->get('q', ''));
        $perPage = (int)$request->get('per_page', 50);
        if ($perPage <= 0) $perPage = 50;
        if ($perPage > 200) $perPage = 200;

        $model = match ($type) {
            'imei' => RemoteImeiService::class,
            'server' => RemoteServerService::class,
            'file' => RemoteFileService::class,
            default => RemoteImeiService::class,
        };

        $query = $model::query()->where('api_id', $provider->id);

        if ($q !== '') {
            $query->where('name', 'like', "%{$q}%");
        }

        $rows = $query
            ->orderBy('group_name')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // هذه الـ views لازم تكون موجودة (أنا أعطيك ملفاتها تحت)
        $view = "admin.api.providers.services_{$type}";

        return view($view, compact('provider', 'rows', 'type'));
    }

    protected function dispatchSync(ApiProvider $provider): void
    {
        // لا تنتظر auto_sync فقط، لأنك تريد sync أول مرة بمجرد الإضافة
        SyncProviderJob::dispatch($provider->id, null)
            ->onQueue('providers');
    }

    protected function validateProvider(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:dhru,gsmhub'],
            'url'  => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'api_key'  => ['nullable', 'string', 'max:255'],

            'sync_imei' => ['nullable', 'boolean'],
            'sync_server' => ['nullable', 'boolean'],
            'sync_file' => ['nullable', 'boolean'],

            'auto_sync' => ['nullable', 'boolean'],
            'ignore_low_balance' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
