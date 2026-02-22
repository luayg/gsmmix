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
            // (remote_file_services usually stores allowed_extensions)
            $allowExt = '';
            if ($type === 'file') {
                $allowExt = (string) (
                    $s->allowed_extensions
                    ?? $s->allow_extensions
                    ?? $s->allow_extension
                    ?? ''
                );
            }

            return [
                'remote_id'         => (string) ($s->remote_id ?? ''),
                'name'              => (string) ($s->name ?? ''),
                'time'              => (string) ($s->time ?? ''),
                'price'             => (float)  ($s->price ?? 0),
                'group_name'        => (string) ($s->group_name ?? ''),
                'info'              => (string) ($s->info ?? ''),         // ✅ ADDED
                'allow_extensions'  => $allowExt,       // ✅ ADDED (file)
                'standfield'        => $stand,
                'additional_fields' => $af,
            ];
        });

        return response()->json($out);
    }
}