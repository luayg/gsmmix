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
    public function providerServices(\Illuminate\Http\Request $request)
{
    $providerId = (int) $request->get('provider_id', 0);
    $type       = strtolower((string) $request->get('type', 'imei'));

    if ($providerId <= 0) {
        return response()->json([]);
    }

    $rows = match ($type) {
        'server' => \App\Models\Remote\RemoteServerService::query()
            ->where('api_provider_id', $providerId)   // ✅ مهم جدًا
            ->orderBy('remote_id')
            ->get(),

        'file' => \App\Models\Remote\RemoteFileService::query()
            ->where('api_provider_id', $providerId)   // ✅ مهم جدًا
            ->orderBy('remote_id')
            ->get(),

        default => \App\Models\Remote\RemoteImeiService::query()
            ->where('api_provider_id', $providerId)   // ✅ مهم جدًا
            ->orderBy('remote_id')
            ->get(),
    };

    // ✅ رجّع additional_fields بشكل صريح عشان الـ modal يقرأه (service-modal.blade.php يعتمد عليه) :contentReference[oaicite:4]{index=4}
    $out = $rows->map(function ($s) {
        return [
            'remote_id'         => (string) ($s->remote_id ?? ''),
            'name'              => (string) ($s->name ?? ''),
            'time'              => (string) ($s->time ?? ''),
            'price'             => (float)  ($s->price ?? 0),  // أو cost/credit لو موديلك مختلف
            'additional_fields' => is_array($s->additional_fields ?? null)
                ? ($s->additional_fields ?? [])
                : (json_decode((string) ($s->additional_fields ?? '[]'), true) ?: []),
        ];
    })->values();

    return response()->json($out);
}

}
