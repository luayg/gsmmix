<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;

class CloneController extends Controller
{
    public function modal()
    {
        return view('admin.api.clone.modal');
    }

    /**
     * Return provider services (for service-modal API dropdown)
     * IMPORTANT:
     * - server/file tables عادة السعر يكون في price وليس credit
     * - لازم نرجّع additional_fields كـ Array (decoded) لكي الواجهة تبني Custom fields
     */
    public function providerServices(Request $request)
    {
        $providerId = (int) $request->get('provider_id');
        $type       = strtolower((string) $request->get('type', 'imei'));

        if (!$providerId) {
            return response()->json([]);
        }

        $model = match ($type) {
            'server' => RemoteServerService::class,
            'file'   => RemoteFileService::class,
            default  => RemoteImeiService::class,
        };

        // ملاحظة: بعض الجداول تحتوي credit (imei)، وبعضها price (server/file)
        $rows = $model::query()
            ->where('supplier_id', $providerId)
            ->orderBy('name')
            ->get()
            ->map(function ($s) {
                // حاول تقرأ السعر من credit أو price
                $credit = null;
                if (isset($s->credit)) $credit = $s->credit;
                if ($credit === null && isset($s->price)) $credit = $s->price;

                // additional_fields لازم يكون Array
                $af = [];
                try {
                    if (isset($s->additional_fields)) {
                        if (is_array($s->additional_fields)) $af = $s->additional_fields;
                        else $af = json_decode((string)$s->additional_fields, true) ?: [];
                    }
                } catch (\Throwable $e) {
                    $af = [];
                }

                return [
                    'remote_id'        => (string) ($s->remote_id ?? ''),
                    'name'             => (string) ($s->name ?? ''),
                    'time'             => (string) ($s->time ?? ''),
                    'credit'           => (float) ($credit ?? 0),
                    'additional_fields'=> $af,
                ];
            })
            ->values();

        return response()->json($rows);
    }
}
