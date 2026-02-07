<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;
use App\Models\ImeiService;
use App\Models\ServerService;
use App\Models\FileService;
use App\Models\ServiceGroup;
use App\Services\Providers\ProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        return view('admin.api.providers.index', compact('rows'));
    }

    /**
     * ✅ API options endpoint (يُستخدم في service-modal لاختيار المزودين)
     * يرجّع JSON بسيط: [{id,name,type,active}, ...]
     */
    public function options(Request $request)
    {
        $onlyActive = $request->boolean('active', false);

        $q = ApiProvider::query()
            ->select(['id', 'name', 'type', 'active'])
            ->orderBy('name');

        if ($onlyActive) {
            $q->where('active', 1);
        }

        return response()->json($q->get()->toArray());
    }

    /**
     * Create/Edit/View تُستخدم داخل المودال في صفحتك عبر js-api-modal
     */
    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function view(Request $request, ApiProvider $provider)
    {
        // نفس المتغير الذي يستخدمه مودال view.blade.php
        $info = [];

        // إذا جاء الطلب من المودال (fetch) رجّع ملف المودال فقط
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.api.providers.modals.view', compact('provider', 'info'));
        }

        // لو أحد فتح الرابط مباشرة من المتصفح (اختياري)
        return view('admin.api.providers.view', compact('provider'));
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

    /**
     * Store/Update
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
     * ✅ زر Sync now
     */
    public function sync(Request $request, ApiProvider $provider, ProviderManager $manager)
    {
        $result = $manager->sync($provider);

        $provider->refresh();

        $msg = [];

        if (!empty($result['errors'])) {
            $msg[] = 'Sync finished with errors: ' . implode(' | ', $result['errors']);
        } else {
            $msg[] = 'Sync done.';
        }

        if (!empty($result['warnings'])) {
            $msg[] = implode(' | ', $result['warnings']);
        }

        $msg[] = 'Balance: $' . number_format((float)$provider->balance, 2);

        return redirect()->route('admin.apis.index')->with('ok', implode(' ', $msg));
    }

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
     * ✅ Services modals (View)
     */
    public function servicesImei(Request $request, ApiProvider $provider)
    {
        $rows = RemoteImeiService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        $services = $rows->map(function ($s) {
            return [
                'GROUPNAME' => (string)($s->group_name ?? ''),
                'REMOTEID'  => (string)($s->remote_id ?? ''),
                'NAME'      => (string)($s->name ?? ''),
                'CREDIT'    => (float)($s->price ?? 0),
                'TIME'      => (string)($s->time ?? ''),
            ];
        })->values()->all();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'kind' => 'imei',
            'services' => $services,
        ]);
    }

    public function servicesServer(Request $request, ApiProvider $provider)
    {
        $rows = RemoteServerService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        $services = $rows->map(function ($s) {
            return [
                'GROUPNAME' => (string)($s->group_name ?? ''),
                'REMOTEID'  => (string)($s->remote_id ?? ''),
                'NAME'      => (string)($s->name ?? ''),
                'CREDIT'    => (float)($s->price ?? 0),
                'TIME'      => (string)($s->time ?? ''),
            ];
        })->values()->all();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'kind' => 'server',
            'services' => $services,
        ]);
    }

    public function servicesFile(Request $request, ApiProvider $provider)
    {
        $rows = RemoteFileService::where('api_provider_id', $provider->id)
            ->orderBy('group_name')
            ->orderBy('name')
            ->get();

        $services = $rows->map(function ($s) {
            return [
                'GROUPNAME' => (string)($s->group_name ?? ''),
                'REMOTEID'  => (string)($s->remote_id ?? ''),
                'NAME'      => (string)($s->name ?? ''),
                'CREDIT'    => (float)($s->price ?? 0),
                'TIME'      => (string)($s->time ?? ''),
            ];
        })->values()->all();

        return view('admin.api.providers.modals.services', [
            'provider' => $provider,
            'kind' => 'file',
            'services' => $services,
        ]);
    }

    /**
     * ✅ IMPORT endpoint
     */
    public function importServices(Request $request, ApiProvider $provider)
    {
        // يدعم JSON
        $kind = strtolower((string)$request->input('kind', ''));
        if (!in_array($kind, ['imei', 'server', 'file'], true)) {
            return response()->json(['ok' => false, 'msg' => 'Invalid kind'], 422);
        }

        $applyAll = (bool)$request->boolean('apply_all', false);

        // ✅ أهم إصلاح: نقبل service_ids (والقديم imported لو موجود)
        $ids = $request->input('service_ids', null);
        if ($ids === null) $ids = $request->input('imported', null);

        if (is_string($ids)) {
            $ids = array_filter(array_map('trim', explode(',', $ids)));
        }

        if (!$applyAll) {
            if (!is_array($ids) || count($ids) === 0) {
                return response()->json(['ok' => false, 'msg' => 'service_ids is required'], 422);
            }
        }

        $mode  = (string)($request->input('profit_mode') ?? $request->input('pricing_mode') ?? 'fixed');
        $mode  = strtolower(trim($mode));
        if (!in_array($mode, ['fixed', 'percent'], true)) $mode = 'fixed';

        $value = (float)($request->input('profit_value') ?? $request->input('pricing_value') ?? 0);

        try {
            $result = $this->doBulkImport($provider, $kind, $applyAll, $ids ?: [], $mode, $value);

            return response()->json([
                'ok' => true,
                'count' => $result['count'],
                'added_remote_ids' => $result['added_remote_ids'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'msg' => $e->getMessage(),
            ], 500);
        }
    }

    public function importServicesWizard(Request $request, ApiProvider $provider)
    {
        return $this->importServices($request, $provider);
    }

    /**
     * =========================
     * Internal helpers
     * =========================
     */

    /**
     * ✅ decode helper (string JSON أو array)
     */
    private function decodeJsonMaybe($val): array
    {
        if (is_array($val)) return $val;
        if (is_string($val) && trim($val) !== '') {
            $decoded = json_decode($val, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * ✅ تحويل remote additional_fields إلى صيغة params.custom_fields
     * (نفس منطق ServerServiceController::mapAdditionalFieldsToCustomFields) :contentReference[oaicite:4]{index=4}
     */
    private function mapAdditionalFieldsToCustomFields(array $additionalFields): array
    {
        $out = [];
        $i = 1;

        foreach ($additionalFields as $f) {
            if (!is_array($f)) continue;

            $name = trim((string)($f['fieldname'] ?? $f['name'] ?? ''));
            if ($name === '') $name = 'Field ' . $i;

            $type = strtolower(trim((string)($f['fieldtype'] ?? $f['type'] ?? 'text')));
            if (in_array($type, ['textbox','string'], true)) $type = 'text';
            if (in_array($type, ['textarea','text_area'], true)) $type = 'textarea';
            if (in_array($type, ['dropdown','select'], true)) $type = 'select';
            if (in_array($type, ['email'], true)) $type = 'email';
            if (in_array($type, ['number','numeric','int','integer'], true)) $type = 'number';

            $required = strtolower((string)($f['required'] ?? '')) === 'on' ? 1 : 0;

            $out[] = [
                'active'      => 1,
                'name'        => $name,
                'input'       => 'service_fields_' . $i,
                'description' => (string)($f['description'] ?? ''),
                'minimum'     => (int)($f['minimum'] ?? 0),
                'maximum'     => (int)($f['maximum'] ?? 0),
                'validation'  => (string)($f['regexpr'] ?? $f['validation'] ?? ''),
                'required'    => $required,
                'type'        => $type,
                'options'     => (string)($f['fieldoptions'] ?? $f['options'] ?? ''),
            ];

            $i++;
        }

        return $out;
    }

    /**
     * ✅ تخمين main_field.type + label من additional_fields
     * (نفس فكرة guess في الواجهة)
     */
    private function guessMainFieldFromAdditionalFields(array $additionalFields, string $kind): array
    {
        // defaults حسب نوع الخدمة
        $default = match ($kind) {
            'imei'   => ['type' => 'imei',   'label' => 'IMEI'],
            'server' => ['type' => 'serial', 'label' => 'Serial'],
            'file'   => ['type' => 'text',   'label' => 'Text'],
            default  => ['type' => 'serial', 'label' => 'Serial'],
        };

        if (count($additionalFields) < 1) return $default;

        $names = [];
        foreach ($additionalFields as $f) {
            if (!is_array($f)) continue;
            $n = strtolower(trim((string)($f['fieldname'] ?? $f['name'] ?? '')));
            if ($n !== '') $names[] = $n;
        }
        if (empty($names)) return $default;

        $hasImei   = (bool)collect($names)->first(fn($n) => str_contains($n, 'imei'));
        $hasSerial = (bool)collect($names)->first(fn($n) => str_contains($n, 'serial'));
        $hasEmail  = (bool)collect($names)->first(fn($n) => str_contains($n, 'email'));

        if ($hasImei)   return ['type' => 'imei',   'label' => 'IMEI'];
        if ($hasSerial) return ['type' => 'serial', 'label' => 'Serial'];
        if ($hasEmail && count($names) === 1) return ['type' => 'email', 'label' => 'Email'];

        // fallback: أول حقل
        $first = $additionalFields[0] ?? [];
        $lab = trim((string)($first['fieldname'] ?? $first['name'] ?? 'Text'));
        return ['type' => 'text', 'label' => ($lab !== '' ? $lab : $default['label'])];
    }

    /**
     * ✅ بناء main_field بنفس شكل مشروعك (مثل ImeiServiceController) :contentReference[oaicite:5]{index=5}
     */
    private function buildMainField(string $type, string $label): array
    {
        return [
            'type'  => $type,
            'rules' => [
                'allowed' => 'any',
                'minimum' => 1,
                'maximum' => 50,
            ],
            'label' => [
                'en' => $label,
                'fallback' => $label,
            ],
        ];
    }

    private function doBulkImport(ApiProvider $provider, string $kind, bool $applyAll, array $remoteIds, string $profitMode, float $profitValue): array
    {
        [$remoteModel, $localModel] = match ($kind) {
            'imei'   => [RemoteImeiService::class, ImeiService::class],
            'server' => [RemoteServerService::class, ServerService::class],
            'file'   => [RemoteFileService::class, FileService::class],
        };

        $remoteQ = $remoteModel::query()->where('api_provider_id', $provider->id);
        if (!$applyAll) {
            $remoteIds = array_values(array_unique(array_map('strval', $remoteIds)));
            $remoteQ->whereIn('remote_id', $remoteIds);
        }
        $remoteRows = $remoteQ->get();

        $added = [];
        $count = 0;

        DB::transaction(function () use ($provider, $kind, $remoteRows, $localModel, $profitMode, $profitValue, &$added, &$count) {

            foreach ($remoteRows as $r) {
                $remoteId = (string)($r->remote_id ?? '');
                if ($remoteId === '') continue;

                // ✅ منع التكرار الحقيقي
                $exists = $localModel::query()
                    ->where('supplier_id', $provider->id)
                    ->where('remote_id', $remoteId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $groupName = trim((string)($r->group_name ?? ''));
                $groupId = null;

                // ✅ أنشئ group تلقائيًا لو موجود اسم مجموعة
                if ($groupName !== '') {
                    $groupType = $this->serviceGroupType($kind);
                    $group = ServiceGroup::firstOrCreate(
                        ['type' => $groupType, 'name' => $groupName],
                        ['ordering' => 0]
                    );
                    $groupId = $group->id;
                }

                $nameText = trim(strip_tags((string)($r->name ?? '')));
                if ($nameText === '') {
                    $nameText = "{$kind}-{$provider->id}-{$remoteId}";
                }

                $timeText = trim(strip_tags((string)($r->time ?? '')));
                $infoText = trim(strip_tags((string)($r->info ?? '')));

                $nameJson = json_encode(['en' => $nameText, 'fallback' => $nameText], JSON_UNESCAPED_UNICODE);
                $timeJson = json_encode(['en' => $timeText, 'fallback' => $timeText], JSON_UNESCAPED_UNICODE);
                $infoJson = json_encode(['en' => $infoText, 'fallback' => $infoText], JSON_UNESCAPED_UNICODE);

                $cost = (float)($r->price ?? 0);
                $profitType = ($profitMode === 'percent') ? 2 : 1;

                $aliasBase = Str::slug(Str::limit($nameText, 160, ''), '-');
                if ($aliasBase === '') $aliasBase = 'service';
                $alias = $aliasBase . '-' . $provider->id . '-' . $remoteId;

                /**
                 * ✅ NEW: Sync additional_fields -> params.custom_fields
                 * المشروع يعرضها من params decoded meta :contentReference[oaicite:6]{index=6}
                 */
                $additionalRaw = $r->additional_fields ?? ($r->fields ?? null);
                $additional = $this->decodeJsonMaybe($additionalRaw);

                $customFields = [];
                if (!empty($additional)) {
                    $customFields = $this->mapAdditionalFieldsToCustomFields($additional);
                }

                /**
                 * ✅ NEW: main_field config (type + label)
                 * نفس شكل مشروعك في إنشاء الخدمات :contentReference[oaicite:7]{index=7}
                 */
                $mf = $this->guessMainFieldFromAdditionalFields($additional, $kind);
                $mainField = $this->buildMainField($mf['type'], $mf['label']);

                /**
                 * ✅ NEW: params JSON (احتفظ بمفاتيح meta فاضية + custom_fields)
                 * نفس نمط ImeiServiceController :contentReference[oaicite:8]{index=8}
                 */
                $params = [
                    'meta_keywords'           => '',
                    'meta_description'        => '',
                    'after_head_tag_opening'  => '',
                    'before_head_tag_closing' => '',
                    'after_body_tag_opening'  => '',
                    'before_body_tag_closing' => '',
                    'custom_fields'           => $customFields,
                ];

                $data = [
                    'alias' => $alias,
                    'group_id' => $groupId,
                    'type' => $kind,
                    'name' => $nameJson,
                    'time' => $timeJson,
                    'info' => $infoJson,

                    // ✅ NEW: main_field + params
                    'main_field' => json_encode($mainField, JSON_UNESCAPED_UNICODE),
                    'params'     => json_encode($params, JSON_UNESCAPED_UNICODE),

                    // تسعير
                    'cost' => $cost,
                    'profit' => $profitValue,
                    'profit_type' => $profitType,

                    // API linking
                    'source' => 2,                 // API
                    'supplier_id' => $provider->id,
                    'remote_id' => $remoteId,

                    // flags
                    'active' => 1,
                    'allow_bulk' => 0,
                    'allow_duplicates' => 0,
                    'reply_with_latest' => 0,
                    'allow_report' => 0,
                    'allow_cancel' => 0,
                    'reply_expiration' => 0,
                ];

                $localModel::query()->create($data);

                $added[] = $remoteId;
                $count++;
            }
        });

        return ['count' => $count, 'added_remote_ids' => $added];
    }

    private function serviceGroupType(string $kind): string
    {
        return match ($kind) {
            'imei'   => 'imei_service',
            'server' => 'server_service',
            'file'   => 'file_service',
            default  => 'imei_service',
        };
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
