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

    /**
     * ✅
     * عند إضافة مزوّد: نعمل Sync أول مرة "بالخلفية" عبر Queue
     * حتى لا تتجمّد الصفحة (خصوصاً GSMHUB/DHRU عند كثرة الخدمات).
     */
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

        $data['synced'] = false;
        $provider = ApiProvider::create($data);

        // ✅ مزامنة أولية تلقائية "Queue"
        if ($provider->active) {
            $this->queueProviderSync($provider);
        }

        return redirect()->route('admin.apis.index')
            ->with('ok', 'API added. Initial sync started in background.');
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

        $criticalChanged = (
            ($provider->url ?? '')      !== ($data['url'] ?? '') ||
            ($provider->username ?? '') !== ($data['username'] ?? '') ||
            ($provider->api_key ?? '')  !== ($data['api_key'] ?? '') ||
            ($provider->type ?? '')     !== ($data['type'] ?? '')
        );

        $provider->update($data);

        if ($criticalChanged) {
            $provider->forceFill(['synced' => 0])->saveQuietly();
        }

        Cache::forget("api:dhru:balance:{$provider->id}");

        return back()->with('ok', 'Saved');
    }

    public function destroy(ApiProvider $provider)
    {
        $provider->delete();
        return back()->with('ok', 'Deleted');
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

    /**
     * ✅ زر "Sync now" — لا يجمّد الصفحة:
     * نرسل أوامر التزامن للـQueue ثم نرجع مباشرة.
     */
    public function sync(ApiProvider $provider)
    {
        $this->queueProviderSync($provider);

        return back()->with('ok', "Sync queued: {$provider->name} (will run in background)");
    }

    /**
     * ✅ تنفيذ التزامن عبر Queue
     * ملاحظة مهمة:
     * - إذا كان QUEUE_CONNECTION=sync => سيبقى يشتغل داخل نفس الطلب (يعني قد يجمّد)
     * - الأفضل: database/redis + تشغيل: php artisan queue:work
     */
    protected function queueProviderSync(ApiProvider $provider): void
    {
        // علّم أنه غير متزامن حالياً (إلى أن ينتهي الأمر)
        $provider->forceFill(['synced' => 0])->saveQuietly();

        // Balance
        Artisan::queue('providers:sync', [
            '--provider_id' => $provider->id,
            '--balance'     => true,
        ]);

        // Catalog (imei/server/file حسب flags داخل ProvidersSyncCommand)
        Artisan::queue('providers:sync', [
            '--provider_id' => $provider->id,
        ]);
    }
}
