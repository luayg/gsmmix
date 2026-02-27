<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Models\RemoteFileService;

class CloneController extends Controller
{
    /**
     * Optional: returns the create form partial by kind (if you still use this route anywhere).
     */
    public function modal(Request $request)
    {
        $kind = strtolower((string) $request->get('kind', 'imei'));
        if (!in_array($kind, ['imei', 'server', 'file'], true)) {
            abort(404);
        }

        // Return the correct create form partial
        return view('admin.services.' . $kind . '._modal_create');
    }

    /**
     * ✅ This is the endpoint used by the "API service" dropdown inside service modal.
     * Route: admin.services.clone.provider_services
     */
    public function providerServices(Request $request)
    {
        $providerId = (int) $request->get('provider_id', 0);
        $type       = strtolower((string) $request->get('type', 'imei'));

        if ($providerId <= 0) {
            return response()->json([]);
        }

        $services = match ($type) {
            'server' => RemoteServerService::query()
                ->where('api_provider_id', $providerId)
                ->orderBy('group_name')
                ->orderBy('name')
                ->get(),

            'file' => RemoteFileService::query()
                ->where('api_provider_id', $providerId)
                ->orderBy('group_name')
                ->orderBy('name')
                ->get(),

            default => RemoteImeiService::query()
                ->where('api_provider_id', $providerId)
                ->orderBy('group_name')
                ->orderBy('name')
                ->get(),
        };

        $out = $services->map(function ($s) use ($type) {

            // Standfield can be array/json/string
            $stand = $s->standfield ?? null;
            if (is_string($stand)) {
                $decoded = json_decode($stand, true);
                $stand = is_array($decoded) ? $decoded : $stand;
            }

            // Additional fields can be array/json/string
            $af = $s->additional_fields ?? [];
            if (is_string($af)) {
                $decoded = json_decode($af, true);
                $af = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($af)) $af = [];

            // ✅ THE MISSING PART: info
            $info = (string) ($s->info ?? '');

            // ✅ For file services: allowed extensions
            $allowExt = '';
            $formatHint = '';
            if ($type === 'file') {
                $allowExt = (string) (
                    $s->allowed_extensions
                    ?? $s->allow_extensions
                    ?? $s->allow_extension
                    ?? ''
                );

                $formatHint = (string) (
                    $s->format
                    ?? $s->file_format
                    ?? $s->format_hint
                    ?? ''
                );
            }

            return [
                'remote_id'          => (string) ($s->remote_id ?? ''),
                'name'               => (string) ($s->name ?? ''),
                'time'               => (string) ($s->time ?? ''),
                'price'              => (float)  ($s->price ?? 0),
                'group_name'         => (string) ($s->group_name ?? ''),
                'info'               => $info,
                'allowed_extensions' => $allowExt,
                'allow_extensions'   => $allowExt, // legacy (اختياري)
                'format'             => $formatHint,
                'active'             => (int) ($s->active ?? 1),
                'allow_bulk'         => (int) ($s->allow_bulk ?? 0),
                'allow_duplicates'   => (int) ($s->allow_duplicates ?? 0),
                'reply_with_latest'  => (int) ($s->reply_with_latest ?? 0),
                'allow_report'       => (int) ($s->allow_report ?? 0),
                'allow_report_time'  => (int) ($s->allow_report_time ?? 0),
                'allow_cancel'       => (int) ($s->allow_cancel ?? 0),
                'allow_cancel_time'  => (int) ($s->allow_cancel_time ?? 0),
                'use_remote_cost'    => (int) ($s->use_remote_cost ?? 0),
                'use_remote_price'   => (int) ($s->use_remote_price ?? 0),
                'stop_on_api_change' => (int) ($s->stop_on_api_change ?? 0),
                'needs_approval'     => (int) ($s->needs_approval ?? 0),
                'reply_expiration'   => (int) ($s->reply_expiration ?? 0),
                'standfield'         => $stand,
                'additional_fields'  => $af,
            ];
        });

        return response()->json($out);
    }
}