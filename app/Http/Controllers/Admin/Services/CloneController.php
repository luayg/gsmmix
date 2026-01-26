<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// Models
use App\Models\ApiProvider;
use App\Models\ServiceGroup;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;

class CloneController extends Controller
{
    public function modal(Request $r)
    {
        $type       = strtolower($r->get('type', 'imei'));   // imei|server|file
        $providerId = (int) $r->get('provider');
        $remoteId   = (string) $r->get('remote_id');

        $typeMap = [
            'imei'   => ['IMEI',          'Numbers',      15, 15, RemoteImeiService::class],
            'server' => ['Custom',        'Alphanumeric', null, null, RemoteServerService::class],
            'file'   => ['Serial number', 'Alphanumeric', null, null, RemoteFileService::class],
        ];
        abort_unless(isset($typeMap[$type]), 404);

        [$mainField, $allowedChars, $min, $max, $RemoteModel] = $typeMap[$type];

        $provider = ApiProvider::findOrFail($providerId);

        $remote = $RemoteModel::query()
            ->where('api_provider_id', $providerId)
            ->where('remote_id', $remoteId)
            ->firstOrFail();

        $price = (float) ($remote->price ?? $remote->credit ?? 0);

        $prefill = [
            'name'                  => $remote->name ?? '',
            'alias'                 => Str::slug($remote->alias ?? $remote->name ?? ''),
            'delivery_time'         => $remote->time ?? 'e.g. 1-24h',
            'group_id'              => null,

            'type'                  => $type,
            'main_field_type'       => $mainField,
            'main_field_label'      => $mainField,
            'allowed_characters'    => $allowedChars,
            'minimum'               => $min,
            'maximum'               => $max,

            'price'                 => $price,
            'converted_price'       => $price,
            'converted_price_ccy'   => 'USD',
            'cost'                  => $price,
            'profit'                => 0,

            'source'                => 'api',
            'api_provider_id'       => $provider->id,
            'api_service_remote_id' => (int) $remote->remote_id,

            'info'                  => $remote->info ?? '',
            'active'                => true,
        ];

        $groups    = ServiceGroup::orderBy('ordering')->orderBy('name')->get(['id','name','type']);
        $providers = ApiProvider::orderBy('name')->get(['id','name']);

        return view('admin.services.partials.service-modal', [
            'mode'      => 'create',
            'type'      => $type,
            'groups'    => $groups,
            'providers' => $providers,
            'prefill'   => $prefill,
        ]);
    }

    /**
     * API: إرجاع خدمات مزوّد محدد (لملء القائمة الثانية)
     * GET /admin/service-management/clone/provider-services?provider_id=1&type=imei&q=...
     */
    public function providerServices(Request $request)
    {
        $providerId = (int) $request->get('provider_id');
        $type       = strtolower($request->get('type', 'imei'));
        $q          = trim((string) $request->get('q', ''));

        $model = match ($type) {
            'server' => RemoteServerService::class,
            'file'   => RemoteFileService::class,
            default  => RemoteImeiService::class,
        };

        // ✅ الصحيح: فلترة حسب api_provider_id (مزود الـ API) وليس remote_id (لأننا نريد "كل الخدمات")
        $rows = $model::query()
            ->where('api_provider_id', $providerId)
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(500)
            ->get([
                'remote_id',
                'remote_id as id',     // مهم لبعض الـ plugins
                'name',
                'price as credit',
                'time',
                'info',
            ]);

        return response()->json($rows);
    }
}
