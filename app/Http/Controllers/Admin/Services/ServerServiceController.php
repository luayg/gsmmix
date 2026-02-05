<?php

namespace App\Http\Controllers\Admin\Services;

use App\Http\Controllers\Controller;
use App\Models\ServerService;
use App\Models\RemoteServerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServerServiceController extends BaseServiceController
{
    protected string $model = ServerService::class;
    protected string $viewPrefix = 'server';
    protected string $routePrefix = 'admin.services.server';
    protected string $table = 'server_services';

    /**
     * ✅ Sync custom_fields from RemoteServerService.additional_fields
     * Route: POST admin/services/server/{id}/sync-fields
     */
    public function syncFields(Request $request, int $id): RedirectResponse
    {
        /** @var ServerService $service */
        $service = ServerService::query()->findOrFail($id);

        $remoteId   = $service->remote_id;
        $supplierId = $service->supplier_id;

        if (!$remoteId || !$supplierId) {
            return redirect()->back()->with('err', 'This service has no remote_id or supplier_id.');
        }

        $remote = RemoteServerService::query()
            ->where('remote_id', (string)$remoteId)
            ->where('api_provider_id', (int)$supplierId)
            ->first();

        if (!$remote) {
            return redirect()->back()->with('err', 'Remote service not found in remote_server_services.');
        }

        // additional_fields قد تكون JSON string أو array (حسب casts)
        $additional = $remote->additional_fields;

        if (is_string($additional)) {
            $decoded = json_decode($additional, true);
            $additional = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($additional)) $additional = [];

        if (count($additional) < 1) {
            return redirect()->back()->with('err', 'Remote service has no additional_fields.');
        }

        $customFields = $this->mapAdditionalFieldsToCustomFields($additional);

        // params ممكن يكون string أو array
        $params = $service->params;
        if (is_string($params)) $params = json_decode($params, true) ?: [];
        if (!is_array($params)) $params = [];

        $params['custom_fields'] = $customFields;

        $service->params = $params;
        $service->save();

        return redirect()->back()->with('ok', 'Custom fields synced successfully.');
    }

    /**
     * تحويل remote additional_fields إلى صيغة server_services.params.custom_fields (صيغة مشروعك)
     */
    private function mapAdditionalFieldsToCustomFields(array $additionalFields): array
    {
        $out = [];
        $i = 1;

        foreach ($additionalFields as $f) {
            if (!is_array($f)) continue;

            $name = trim((string)($f['fieldname'] ?? ''));
            if ($name === '') $name = 'Field ' . $i;

            $type = strtolower(trim((string)($f['fieldtype'] ?? 'text')));
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
}
