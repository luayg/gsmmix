<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Services\Providers\ProviderManager;
use Illuminate\Http\Request;

class ApiProvidersController extends Controller
{
    /**
     * صفحة القائمة الرئيسية (واجهة API Management التي عندك)
     * IMPORTANT: هذه الدالة يجب أن ترسل $rows للـ view لأن blade يعتمد عليه.
     */
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $type    = trim((string) $request->get('type', ''));
        $status  = trim((string) $request->get('status', ''));
        $perPage = (int) $request->get('per_page', 20);
        if ($perPage <= 0) $perPage = 20;
        if ($perPage > 200) $perPage = 200;

        $query = ApiProvider::query()->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('url', 'like', "%{$q}%")
                  ->orWhere('username', 'like', "%{$q}%");
            });
        }

        // ✅ دعم فلتر النوع كما في واجهتك (DHRU / Simple link)
        // في DB النوع غالبًا: dhru / simple_link / gsmhub ...
        if ($type !== '') {
            $normalized = $this->normalizeType($type);
            if ($normalized) {
                $query->where('type', $normalized);
            }
        }

        // ✅ دعم فلتر الحالة كما في واجهتك (Active/Inactive)
        if ($status !== '') {
            if (strcasecmp($status, 'Active') === 0) {
                $query->where('active', 1);
            } elseif (strcasecmp($status, 'Inactive') === 0) {
                $query->where('active', 0);
            }
        }

        $rows = $query->paginate($perPage)->withQueryString();

        // ⚠️ لا نغير الواجهة إطلاقًا — فقط نوفر $rows
        return view('admin.api.providers.index', compact('rows'));
    }

    /**
     * Create/Edit/View تُستخدم داخل المودال في صفحتك عبر js-api-modal
     */
    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function view(ApiProvider $provider)
    {
        return view('admin.api.providers.view', compact('provider'));
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

    /**
     * Store/Update
     * نحافظ على نفس سلوكك، فقط نحفظ الحقول المهمة (active/auto_sync/sync flags/ignore_low_balance/params)
     */
    public function store(Request $request)
    {
        $data = $this->validateProvider($request);
        $data['url'] = rtrim((string)$data['url'], '/') . '/';

        ApiProvider::create($data);

        return redirect()->route('admin.apis.index')->with('ok', 'API Provider created.');
    }

    public function update(Request $request, ApiProvider $provider)
    {
        $data = $this->validateProvider($request, $provider->id);
        $data['url'] = rtrim((string)$data['url'], '/') . '/';

        $provider->update($data);

        return redirect()->route('admin.apis.index')->with('ok', 'API Provider updated.');
    }

    public function destroy(Request $request, ApiProvider $provider)
    {
        $provider->delete();
        return redirect()->route('admin.apis.index')->with('ok', 'API Provider deleted.');
    }

    /**
     * ✅ زر Sync now الموجود في واجهتك
     * - لا نغير شكل الصفحة
     * - ننفذ sync مباشرة (سريع لتظهر balance فورًا)
     * - إذا تحب Queue لاحقًا نرجعه
     */
    public function sync(Request $request, ApiProvider $provider, ProviderManager $manager)
    {
        // نفّذ مزامنة + balance + تحديث counters
        $result = $manager->sync($provider, null, false);

        if (!empty($result['errors'])) {
            return redirect()->route('admin.apis.index')
                ->with('ok', 'Sync finished with errors: ' . implode(' | ', $result['errors']));
        }

        return redirect()->route('admin.apis.index')
            ->with('ok', 'Sync done. Balance: ' . number_format((float)$result['balance'], 2));
    }

    /**
     * ✅ إضافة “اختبار اتصال/جلب رصيد فقط” بدون تغيير الواجهة
     * تقدر تناديها من زر/JS أو حتى من رابط.
     */
    public function testBalance(ApiProvider $provider, ProviderManager $manager)
    {
        $result = $manager->sync($provider, null, true);

        return response()->json([
            'ok' => empty($result['errors']),
            'balance' => $result['balance'],
            'errors' => $result['errors'],
        ]);
    }

    /**
     * ✅ هذه الدوال هي سبب الخطأ عندك (كانت موجودة في routes)
     * الآن أرجعناها كما هي حتى تعمل أزرار Services في واجهتك.
     */
    public function servicesImei(Request $request, ApiProvider $provider)
    {
        $services = RemoteImeiService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'kind' => 'imei',
            'services' => $services,
            'grouped' => $services->groupBy(fn($s) => $s->group_name ?: 'Ungrouped'),
        ]);
    }

    public function servicesServer(Request $request, ApiProvider $provider)
    {
        $services = RemoteServerService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'kind' => 'server',
            'services' => $services,
            'grouped' => $services->groupBy(fn($s) => $s->group_name ?: 'Ungrouped'),
        ]);
    }

    public function servicesFile(Request $request, ApiProvider $provider)
    {
        $services = RemoteFileService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'kind' => 'file',
            'services' => $services,
            'grouped' => $services->groupBy(fn($s) => $s->group_name ?: 'Ungrouped'),
        ]);
    }

    private function normalizeType(string $type): ?string
    {
        $t = strtolower(trim($type));
        return match ($t) {
            'dhru' => 'dhru',
            'gsmhub' => 'gsmhub',
            'webx' => 'webx',
            'unlockbase' => 'unlockbase',
            'simple link', 'simple_link', 'simplelink' => 'simple_link',
            default => null,
        };
    }

    private function validateProvider(Request $request, ?int $providerId = null): array
    {
        $types = ['dhru', 'webx', 'gsmhub', 'unlockbase', 'simple_link'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', $types)],
            'url' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],

            'sync_imei' => ['nullable'],
            'sync_server' => ['nullable'],
            'sync_file' => ['nullable'],

            'ignore_low_balance' => ['nullable'],
            'auto_sync' => ['nullable'],
            'active' => ['nullable'],

            'params' => ['nullable'],
        ]);

        $data['sync_imei'] = $request->boolean('sync_imei');
        $data['sync_server'] = $request->boolean('sync_server');
        $data['sync_file'] = $request->boolean('sync_file');

        $data['ignore_low_balance'] = $request->boolean('ignore_low_balance');
        $data['auto_sync'] = $request->boolean('auto_sync');
        $data['active'] = $request->boolean('active');

        $params = $request->input('params');
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $data['params'] = is_array($decoded) ? $decoded : null;
        } elseif (is_array($params)) {
            $data['params'] = $params;
        } else {
            $data['params'] = null;
        }

        return $data;
    }
}
