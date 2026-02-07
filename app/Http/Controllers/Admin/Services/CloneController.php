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
        $providerId = (int) $request->get('provider_id', 0);
        $type       = strtolower((string) $request->get('type', 'imei'));
        $q          = trim((string) $request->get('q', ''));

        if ($providerId <= 0) {
            return response()->json([]);
        }

        $query = match ($type) {
            'server' => RemoteServerService::query()->where('api_provider_id', $providerId),
            'file'   => RemoteFileService::query()->where('api_provider_id', $providerId),
            default  => RemoteImeiService::query()->where('api_provider_id', $providerId),
        };

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('remote_id', 'like', "%{$q}%")
                  ->orWhere('group_name', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderBy('remote_id')->get();

        // ✅ رجّع additional_fields بشكل صريح عشان الـ modal يقرأه
        $out = $rows->map(function ($s) {

            $af = $s->additional_fields ?? null;
            if (!is_array($af)) {
                $af = json_decode((string) $af, true);
                if (!is_array($af)) $af = [];
            }

            // standfield (لو موجود في جدول الريموت)
            $stand = $s->standfield ?? $s->stand_field ?? null;

            return [
                'remote_id'         => (string) ($s->remote_id ?? ''),
                'name'              => (string) ($s->name ?? ''),
                'time'              => (string) ($s->time ?? ''),
                'price'             => (float)  ($s->price ?? 0), // مهم: price مش credit
                'group_name'        => (string) ($s->group_name ?? ''),
                'standfield'        => $stand,
                'additional_fields' => $af,
            ];
        })->values();

        return response()->json($out);
    }
}
