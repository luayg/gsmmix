<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use Illuminate\Http\Request;

class CloneController extends Controller
{
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

        $query = $model::query()
            ->where('api_provider_id', $providerId)
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(500);

        // ✅ اختَر أعمدة موجودة فعلاً بكل جدول
        if ($type === 'imei') {
            $rows = $query->get([
                'remote_id',
                'name',
                'credit',
                'price',
                'time',
                'info',
                'additional_fields',
            ]);
        } else {
            // server / file (لا يوجد credit)
            $rows = $query->get([
                'remote_id',
                'name',
                'price',
                'time',
                'info',
                'additional_fields',
            ]);
        }

        // ✅ طبّع الإخراج لشكل واحد ثابت للـ JS
        $out = $rows->map(function ($r) use ($type) {
            $credit = 0.0;

            if ($type === 'imei') {
                $credit = (float) ($r->credit ?? $r->price ?? 0);
            } else {
                $credit = (float) ($r->price ?? 0);
            }

            // additional_fields أحياناً تكون JSON string
            $af = $r->additional_fields ?? null;
            if (is_string($af) && $af !== '') {
                $decoded = json_decode($af, true);
                $af = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
            }

            return [
                'remote_id' => (string) $r->remote_id,
                'id'        => (string) $r->remote_id,
                'name'      => (string) ($r->name ?? ''),
                'credit'    => $credit,
                'time'      => (string) ($r->time ?? ''),
                'info'      => (string) ($r->info ?? ''),
                'additional_fields' => is_array($af) ? $af : [],
            ];
        })->values();

        return response()->json($out);
    }
}
