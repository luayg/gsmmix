<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ServerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemoteServerServicesController extends Controller
{
    private function remoteLinkColumn(): string
    {
        $table = 'remote_server_services';

        if (Schema::hasColumn($table, 'supplier_id')) return 'supplier_id';
        if (Schema::hasColumn($table, 'provider_id')) return 'provider_id';
        if (Schema::hasColumn($table, 'api_provider_id')) return 'api_provider_id';

        // fallback (آخر حل)
        return 'supplier_id';
    }

    public function index(ApiProvider $provider, Request $request)
    {
        $col = $this->remoteLinkColumn();

        $rows = DB::table('remote_server_services')
            ->where($col, $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $groups = $rows->groupBy('group_name');

        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn($v)=>(string)$v)
            ->flip()
            ->all();

        // ✅ إذا جاي من apiModal (AJAX) رجّع محتوى مودال
        if ($request->ajax()) {
            return view('admin.api.remote.server.modal', compact('provider','groups','existing'));
        }

        // ✅ صفحة مستقلة (لو فتحتها مباشرة)
        return view('admin.api.remote.server.page', compact('provider','groups','existing'));
    }

    public function importPage(ApiProvider $provider, Request $request)
    {
        $col = $this->remoteLinkColumn();

        $services = DB::table('remote_server_services')
            ->where($col, $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        $existing = ServerService::where('supplier_id', $provider->id)
            ->pluck('remote_id')
            ->map(fn($v)=>(string)$v)
            ->flip()
            ->all();

        if ($request->ajax()) {
            return view('admin.api.remote.server.import_modal', compact('provider','services','existing'));
        }

        return view('admin.api.remote.server.import', compact('provider','services','existing'));
    }

    public function import(ApiProvider $provider, Request $request)
    {
        // هذا endpoint يُستدعى من صفحة/مودال الاستيراد
        // payload:
        // { apply_all: bool, service_ids: [], pricing_mode: 'percent'|'fixed', pricing_value: number }
        $applyAll     = (bool) $request->input('apply_all', false);
        $serviceIds   = (array) $request->input('service_ids', []);
        $pricingMode  = (string) $request->input('pricing_mode', 'fixed');
        $pricingValue = (float) $request->input('pricing_value', 0);

        $col = $this->remoteLinkColumn();

        $q = DB::table('remote_server_services')->where($col, $provider->id);

        if (!$applyAll) {
            if (!count($serviceIds)) {
                return response()->json(['ok'=>false,'msg'=>'No services selected'], 422);
            }
            $q->whereIn('remote_id', $serviceIds);
        }

        $rows = $q->get();

        $added = [];
        foreach ($rows as $r) {
            $remoteId = (string)($r->remote_id ?? '');
            if (!$remoteId) continue;

            // already exists?
            $exists = ServerService::where('supplier_id', $provider->id)
                ->where('remote_id', $remoteId)
                ->exists();
            if ($exists) continue;

            $name = (string)($r->name ?? $r->service_name ?? '');
            $time = (string)($r->time ?? '');
            $cost = (float)($r->price ?? $r->credit ?? 0);

            // apply pricing
            if ($pricingMode === 'percent') {
                $profit = $cost * ($pricingValue / 100);
            } else {
                $profit = $pricingValue;
            }

            // additional_fields (قد يكون JSON string أو array)
            $afRaw = $r->additional_fields ?? $r->fields ?? null;
            $af = [];
            if (is_string($afRaw) && $afRaw !== '') {
                $af = json_decode($afRaw, true) ?: [];
            } elseif (is_array($afRaw)) {
                $af = $afRaw;
            } elseif (is_object($afRaw)) {
                $af = (array)$afRaw;
            }

            // خزّنها داخل params.custom_fields (نفس منطق create modal)
            $params = [
                'custom_fields' => [],
            ];

            if (is_array($af) && count($af)) {
                $i = 0;
                foreach ($af as $f) {
                    $i++;
                    $label = trim((string)($f['fieldname'] ?? $f['name'] ?? 'Field '.$i));
                    $required = strtolower((string)($f['required'] ?? '')) === 'on' ? 1 : 0;

                    $params['custom_fields'][] = [
                        'active' => 1,
                        'name' => $label,
                        'type' => 'text',
                        'input' => 'service_fields_'.$i,
                        'description' => (string)($f['description'] ?? ''),
                        'minimum' => 0,
                        'maximum' => 0,
                        'validation' => (stripos($label,'email') !== false ? 'email' : ''),
                        'required' => $required,
                        'options' => (string)($f['fieldoptions'] ?? ''),
                    ];
                }
            }

            // main_field بسيط
            $mainField = [
                'type' => 'text',
                'label' => 'Serial',
                'allowed_characters' => 'any',
                'minimum' => 1,
                'maximum' => 50,
            ];

            $service = new ServerService();
            $service->forceFill([
                'supplier_id' => $provider->id,
                'remote_id'   => $remoteId,
                'group_name'  => (string)($r->group_name ?? ''),
                'name'        => $name,
                'name_en'     => $name,
                'alias'       => \Illuminate\Support\Str::slug($name),
                'time'        => $time,
                'cost'        => $cost,
                'profit'      => (float)$profit,
                'profit_type' => 1,
                'source'      => 2,
                'type'        => 'server',
                'main_type'   => 'text',
                'main_field'  => json_encode($mainField, JSON_UNESCAPED_UNICODE),
                'params'      => json_encode($params, JSON_UNESCAPED_UNICODE),
                'active'      => 1,
            ]);

            $service->save();

            $added[] = $remoteId;
        }

        return response()->json([
            'ok' => true,
            'count' => count($added),
            'added_remote_ids' => $added,
        ]);
    }

    public function servicesJson(ApiProvider $provider)
    {
        $col = $this->remoteLinkColumn();

        $services = DB::table('remote_server_services')
            ->where($col, $provider->id)
            ->orderBy('group_name')
            ->orderBy('remote_id')
            ->get();

        return response()->json($services);
    }
}
