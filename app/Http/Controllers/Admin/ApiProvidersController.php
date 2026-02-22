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
use App\Models\ServiceGroupPrice;

class ApiProvidersController extends Controller
{
    /**
     * صفحة القائمة الرئيسية
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

        if ($type !== '') {
            $normalized = $this->normalizeType($type);
            if ($normalized) $query->where('type', $normalized);
        }

        if ($status !== '') {
            if (strcasecmp($status, 'Active') === 0) $query->where('active', 1);
            elseif (strcasecmp($status, 'Inactive') === 0) $query->where('active', 0);
        }

        $rows = $query->paginate($perPage)->withQueryString();
        return view('admin.api.providers.index', compact('rows'));
    }

    /**
     * API options endpoint
     */
    public function options(Request $request)
    {
        $onlyActive = $request->boolean('active', false);

        $q = ApiProvider::query()
            ->select(['id', 'name', 'type', 'active'])
            ->orderBy('name');

        if ($onlyActive) $q->where('active', 1);

        return response()->json($q->get()->toArray());
    }

    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function view(Request $request, ApiProvider $provider)
    {
        $info = [];

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.api.providers.modals.view', compact('provider', 'info'));
        }

        return view('admin.api.providers.view', compact('provider'));
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

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

    /**
     * ✅ FIX:
     * عند حذف Provider لازم نحذف كل خدماته من جداول remote_* حتى لا يحصل تضخم/ازدواجية
     */
    public function destroy(Request $request, ApiProvider $provider)
    {
        DB::transaction(function () use ($provider) {
            $pid = (int)$provider->id;

            // ✅ احذف خدمات الريموت أولاً
            RemoteImeiService::query()->where('api_provider_id', $pid)->delete();
            RemoteServerService::query()->where('api_provider_id', $pid)->delete();
            RemoteFileService::query()->where('api_provider_id', $pid)->delete();

            // ✅ ثم احذف المزود نفسه
            $provider->delete();
        });

        return redirect()->route('admin.apis.index')->with('ok', 'API Provider deleted + remote services purged.');
    }

    public function sync(Request $request, ApiProvider $provider, ProviderManager $manager)
    {
        $result = $manager->sync($provider);
        $provider->refresh();

        $msg = [];
        if (!empty($result['errors'])) $msg[] = 'Sync finished with errors: ' . implode(' | ', $result['errors']);
        else $msg[] = 'Sync done.';
        if (!empty($result['warnings'])) $msg[] = implode(' | ', $result['warnings']);
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
     * Services modals
     */
    public function servicesImei(Request $request, ApiProvider $provider)
{
    $rows = RemoteImeiService::where('api_provider_id', $provider->id)
        ->orderBy('group_name')->orderBy('name')->get();

    $services = $rows->map(function ($s) {
        $af = $s->additional_fields ?? [];
        // نخليه array أو json string حسب الحاجة (services.blade.php يتعامل مع الاثنين)
        $afOut = is_array($af) ? $af : (json_decode((string)$af, true) ?: []);

        return [
            'GROUPNAME' => (string)($s->group_name ?? ''),
            'REMOTEID'  => (string)($s->remote_id ?? ''),
            'NAME'      => (string)($s->name ?? ''),
            'CREDIT'    => (float)($s->price ?? 0),
            'TIME'      => (string)($s->time ?? ''),
            // ✅ هذا هو المهم
            'ADDITIONAL_FIELDS' => $afOut,
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
        ->orderBy('group_name')->orderBy('name')->get();

    $services = $rows->map(function ($s) {
        $af = $s->additional_fields ?? [];
        $afOut = is_array($af) ? $af : (json_decode((string)$af, true) ?: []);

        return [
            'GROUPNAME' => (string)($s->group_name ?? ''),
            'REMOTEID'  => (string)($s->remote_id ?? ''),
            'NAME'      => (string)($s->name ?? ''),
            'CREDIT'    => (float)($s->price ?? 0),
            'TIME'      => (string)($s->time ?? ''),
            'ADDITIONAL_FIELDS' => $afOut,
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
        ->orderBy('group_name')->orderBy('name')->get();

    $services = $rows->map(function ($s) {
        $af = $s->additional_fields ?? [];
        $afOut = is_array($af) ? $af : (json_decode((string)$af, true) ?: []);

        return [
    'GROUPNAME' => (string)($s->group_name ?? ''),
    'REMOTEID'  => (string)($s->remote_id ?? ''),
    'NAME'      => (string)($s->name ?? ''),
    'CREDIT'    => (float)($s->price ?? 0),
    'TIME'      => (string)($s->time ?? ''),
    'ADDITIONAL_FIELDS' => $afOut,

    // ✅ NEW: file extensions from API (ALLOW_EXTENSION)
    'ALLOW_EXTENSION' => (string)($s->allow_extension ?? $s->allow_extensions ?? ''),
];

    })->values()->all();

    return view('admin.api.providers.modals.services', [
        'provider' => $provider,
        'kind' => 'file',
        'services' => $services,
    ]);
}

    /**
     * IMPORT endpoint
     */
    public function importServices(Request $request, ApiProvider $provider)
{
    $kind = strtolower((string)$request->input('kind', ''));
    if (!in_array($kind, ['imei', 'server', 'file'], true)) {
        return response()->json(['ok' => false, 'msg' => 'Invalid kind'], 422);
    }

    $applyAll = (bool)$request->boolean('apply_all', false);

    $ids = $request->input('service_ids', null);
    if ($ids === null) $ids = $request->input('imported', null);
    if (is_string($ids)) $ids = array_filter(array_map('trim', explode(',', $ids)));

    if (!$applyAll) {
        if (!is_array($ids) || count($ids) === 0) {
            return response()->json(['ok' => false, 'msg' => 'service_ids is required'], 422);
        }
    }

    // ✅ pricing mode/value (عرّفهم قبل أي استعمال)
    $mode  = (string)($request->input('profit_mode') ?? $request->input('pricing_mode') ?? 'fixed');
    $mode  = strtolower(trim($mode));
    if (!in_array($mode, ['fixed', 'percent'], true)) $mode = 'fixed';

    $value = (float)($request->input('profit_value') ?? $request->input('pricing_value') ?? 0);

    // ✅ group prices (قد تصل array أو JSON string)
    $groupPrices = $request->input('group_prices', []);
    if (is_string($groupPrices) && trim($groupPrices) !== '') {
        $decoded = json_decode($groupPrices, true);
        if (is_array($decoded)) $groupPrices = $decoded;
    }
    if (!is_array($groupPrices)) $groupPrices = [];

    try {
        $result = $this->doBulkImport(
            $provider,
            $kind,
            $applyAll,
            $ids ?: [],
            $mode,
            $value,
            $groupPrices
        );

        return response()->json([
            'ok' => true,
            'count' => $result['count'],
            'added_remote_ids' => $result['added_remote_ids'],
        ]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'msg' => $e->getMessage()], 500);
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

    private function buildMainFieldJson(string $type = 'serial', string $label = 'Serial', string $allowed = 'any', int $min = 1, int $max = 50): string
    {
        $cfg = [
            'type'  => $type,
            'rules' => [
                'allowed' => $allowed,
                'minimum' => (string)$min,
                'maximum' => (string)$max,
                'label'   => ['en' => $label, 'fallback' => $label],
            ],
        ];
        return json_encode($cfg, JSON_UNESCAPED_UNICODE);
    }

    private function mapRemoteFieldType($t): string
    {
        $x = strtolower(trim((string)$t));
        if (in_array($x, ['dropdown', 'select'], true)) return 'select';
        if (in_array($x, ['textarea', 'text_area'], true)) return 'textarea';
        if ($x === 'password') return 'password';
        if ($x === 'email') return 'email';
        if (in_array($x, ['number', 'numeric', 'int', 'integer'], true)) return 'number';
        return 'text';
    }

    private function saveCustomFieldsToTable(string $serviceType, int $serviceId, array $fields): void
    {
        $now = now();
        $rows = [];

        $ordering = 1;
        foreach ($fields as $f) {
            $name = trim((string)($f['name'] ?? ''));
            $input = trim((string)($f['input'] ?? ''));
            if ($name === '' || $input === '') continue;

            $fieldType = (string)($f['type'] ?? 'text');
            $min = (int)($f['minimum'] ?? 0);
            $max = $f['maximum'];
            $max = ($max === null || $max === '' ? 0 : (int)$max);

            $validation = (string)($f['validation'] ?? '');
            $desc = (string)($f['description'] ?? '');

            $options = $f['options'] ?? [];
            $fieldOptions = is_array($options)
                ? json_encode($options, JSON_UNESCAPED_UNICODE)
                : (string)$options;

            $rows[] = [
                'service_type'  => $serviceType,
                'service_id'    => $serviceId,
                'active'        => (int)($f['active'] ?? 1),
                'required'      => (int)($f['required'] ?? 0),
                'maximum'       => $max,
                'minimum'       => $min,
                'validation'    => $validation ?: null,
                'description'   => $desc ?: null,
                'field_options' => $fieldOptions ?: null,
                'field_type'    => $fieldType,
                'input'         => $input,
                'name'          => json_encode(['en' => $name, 'fallback' => $name], JSON_UNESCAPED_UNICODE),
                'ordering'      => $ordering++,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('custom_fields')
                ->where('service_type', $serviceType)
                ->where('service_id', $serviceId)
                ->delete();

            DB::table('custom_fields')->insert($rows);
        }
    }

    private function extractRemoteAdditionalFields($r): array
    {
        $raw = $r->additional_fields ?? $r->additional_data ?? null;
        if ($raw === null) return [];

        if (is_array($raw)) return $raw;

        $rawStr = trim((string)$raw);
        if ($rawStr === '') return [];

        $decoded = json_decode($rawStr, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function extractRemoteInfoText($r): string
{
    // 1) المصدر الأساسي: عمود info
    $text = trim(strip_tags((string)($r->info ?? '')));
    if ($text !== '') return $text;

    // 2) fallback: additional_data (قد يكون array بسبب casts أو string JSON)
    $ad = $r->additional_data ?? null;
    if ($ad === null) return '';

    if (is_string($ad)) {
        $ad = json_decode($ad, true);
    }

    if (!is_array($ad)) return '';

    // مفاتيح شائعة (DHru غالبًا INFO)
    $text = trim(strip_tags((string)(
        $ad['INFO'] ?? $ad['info'] ??
        $ad['DESCRIPTION'] ?? $ad['description'] ??
        $ad['SERVICEINFO'] ?? $ad['serviceinfo'] ??
        $ad['SERVICE_INFO'] ?? $ad['service_info'] ??
        ''
    )));
    if ($text !== '') return $text;

    // 3) بعض المزودين يضعونها داخل CUSTOM
    $custom = $ad['CUSTOM'] ?? $ad['custom'] ?? null;
    if (is_array($custom)) {
        $text = trim(strip_tags((string)(
            $custom['custominfo'] ?? $custom['info'] ?? ''
        )));
        if ($text !== '') return $text;
    }

    return '';
}
    private function normalizeRemoteFieldsToLocal(array $remoteFields): array
    {
        $out = [];
        foreach ($remoteFields as $idx => $rf) {
            $label = trim((string)($rf['fieldname'] ?? $rf['name'] ?? $rf['label'] ?? ''));
            if ($label === '') $label = 'Field ' . ($idx + 1);

            $required = strtolower(trim((string)($rf['required'] ?? ''))) === 'on' ? 1 : 0;

            $type = $this->mapRemoteFieldType($rf['fieldtype'] ?? $rf['type'] ?? 'text');

            $optionsRaw = $rf['fieldoptions'] ?? $rf['options'] ?? [];
            $optionsArr = [];
            if (is_string($optionsRaw)) {
                $optionsArr = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $optionsRaw))));
            } elseif (is_array($optionsRaw)) {
                $optionsArr = $optionsRaw;
            }

            $out[] = [
                'active'      => 1,
                'name'        => $label,
                'type'        => $type,
                'input'       => 'service_fields_' . ($idx + 1),
                'description' => (string)($rf['description'] ?? ''),
                'minimum'     => 0,
                'maximum'     => 0,
                'validation'  => null,
                'required'    => $required,
                'options'     => $type === 'select' ? $optionsArr : [],
            ];
        }
        return $out;
    }

    private function doBulkImport(
    ApiProvider $provider,
    string $kind,
    bool $applyAll,
    array $remoteIds,
    string $profitMode,
    float $profitValue,
    array $groupPrices = []
): array
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

    $groupPrices = $this->normalizeGroupPrices($groupPrices);

    DB::transaction(function () use ($provider, $kind, $remoteRows, $localModel, $profitMode, $profitValue, $groupPrices, &$added, &$count) {

        foreach ($remoteRows as $r) {
            $remoteId = (string)($r->remote_id ?? '');
            if ($remoteId === '') continue;

            $exists = $localModel::query()
                ->where('supplier_id', $provider->id)
                ->where('remote_id', $remoteId)
                ->exists();
            if ($exists) continue;

            $groupName = trim((string)($r->group_name ?? ''));
            $groupId = null;

            if ($groupName !== '') {
                $groupType = $this->serviceGroupType($kind);
                $group = ServiceGroup::firstOrCreate(
                    ['type' => $groupType, 'name' => $groupName],
                    ['ordering' => 0]
                );
                $groupId = $group->id;
            }

            $nameText = trim(strip_tags((string)($r->name ?? '')));
            if ($nameText === '') $nameText = "{$kind}-{$provider->id}-{$remoteId}";

            $timeText = trim(strip_tags((string)($r->time ?? '')));
            $infoText = $this->extractRemoteInfoText($r);

            $nameJson = json_encode(['en' => $nameText, 'fallback' => $nameText], JSON_UNESCAPED_UNICODE);
            $timeJson = json_encode(['en' => $timeText, 'fallback' => $timeText], JSON_UNESCAPED_UNICODE);
            $infoJson = json_encode(['en' => $infoText, 'fallback' => $infoText], JSON_UNESCAPED_UNICODE);

            $cost = (float)($r->price ?? 0);
            $profitType = ($profitMode === 'percent') ? 2 : 1;

            $aliasBase = Str::slug(Str::limit($nameText, 160, ''), '-');
            if ($aliasBase === '') $aliasBase = 'service';
            $alias = $aliasBase . '-' . $provider->id . '-' . $remoteId;

            $mainField = $this->buildMainFieldJson('serial', 'Serial', 'any', 1, 50);

            $data = [
                'alias' => $alias,
                'group_id' => $groupId,
                'type' => $kind,
                'name' => $nameJson,
                'time' => $timeJson,
                'info' => $infoJson,

                'cost' => $cost,
                'profit' => $profitValue,
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

            $created = $localModel::query()->create($data);

            // ✅ save custom fields
            $remoteFields = $this->extractRemoteAdditionalFields($r);
            if (!empty($remoteFields) && $created?->id) {
                $localFields = $this->normalizeRemoteFieldsToLocal($remoteFields);
                $serviceType = $this->serviceGroupType($kind);
                $this->saveCustomFieldsToTable($serviceType, (int)$created->id, $localFields);
            }

            // ✅ NEW: save group prices template for this service
            if ($created?->id && !empty($groupPrices)) {
                $finalServicePrice = $this->calcFinalPrice($cost, $profitType, $profitValue);
                $this->saveGroupPricesForService($kind, (int)$created->id, $groupPrices, $finalServicePrice);
            }

            $added[] = $remoteId;
            $count++;
        }
    });

    return ['count' => $count, 'added_remote_ids' => $added];
}


    private function calcFinalPrice(float $cost, int $profitType, float $profitValue): float
{
    // profitType: 1 = fixed, 2 = percent
    $price = ($profitType === 2) ? ($cost + ($cost * $profitValue / 100)) : ($cost + $profitValue);
    if (!is_finite($price) || $price < 0) $price = 0;
    return (float)$price;
}

private function normalizeGroupPrices($raw): array
{
    if (!is_array($raw)) return [];

    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) continue;

        $gid = (int)($row['group_id'] ?? 0);
        if ($gid <= 0) continue;

        $out[] = [
            'group_id' => $gid,
            'auto_price' => !empty($row['auto_price']) ? 1 : 0,
            'price' => (float)($row['price'] ?? 0),
            'discount' => (float)($row['discount'] ?? 0),
            'discount_type' => ((int)($row['discount_type'] ?? 1) === 2) ? 2 : 1,
        ];
    }

    return $out;
}

private function saveGroupPricesForService(string $kind, int $serviceId, array $groupPrices, float $finalServicePrice): void
{
    // نفس values المستخدمة في BaseServiceController (service_type = imei/server/file)
    foreach ($groupPrices as $row) {
        $groupId = (int)($row['group_id'] ?? 0);
        if ($groupId <= 0) continue;

        $auto = !empty($row['auto_price']) ? 1 : 0;
        $price = $auto ? $finalServicePrice : (float)($row['price'] ?? 0);
        if (!is_finite($price) || $price < 0) $price = 0;

        $discount = (float)($row['discount'] ?? 0);
        if (!is_finite($discount) || $discount < 0) $discount = 0;

        $dtype = ((int)($row['discount_type'] ?? 1) === 2) ? 2 : 1;

        ServiceGroupPrice::updateOrCreate(
            [
                'service_id' => $serviceId,
                'service_type' => $kind,
                'group_id' => $groupId,
            ],
            [
                'price' => $price,
                'discount' => $discount,
                'discount_type' => $dtype,
            ]
        );
    }
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
