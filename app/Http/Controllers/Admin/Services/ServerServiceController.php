<?php

namespace App\Http\Controllers\Admin\Services;

use App\Models\ServerService;
use App\Models\RemoteServerService;
use App\Services\Providers\RemoteCustomFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServerServiceController extends BaseServiceController
{
    protected string $model = ServerService::class;
    protected string $viewPrefix = 'server';
    protected string $routePrefix = 'admin.services.server';
    protected string $table = 'server_services';

    public function syncFields(Request $request, int $id, RemoteCustomFields $mapper)
    {
        return DB::transaction(function () use ($id, $mapper) {
            $service = ServerService::query()->lockForUpdate()->findOrFail($id);
            if ((int) $service->source !== 2 || !$service->supplier_id || !$service->remote_id) {
                throw ValidationException::withMessages(['service' => 'Select an API service before syncing fields.']);
            }
            $remote = RemoteServerService::query()
                ->where('api_provider_id', $service->supplier_id)
                ->where('remote_id', $service->remote_id)->first();
            if (!$remote) {
                throw ValidationException::withMessages(['service' => 'The linked service is missing from the provider catalog. Refresh the catalog first.']);
            }
            $fields = $mapper->normalizeRemoteFieldsToLocal($mapper->extractRemoteAdditionalFields($remote));
            if ($fields === []) {
                throw ValidationException::withMessages(['service' => 'No valid provider fields are available. Existing fields were kept.']);
            }
            $fields = $this->normalizeCustomFields($fields, null);
            $params = $service->params;
            for ($depth = 0; $depth < 2 && is_string($params); $depth++) {
                $params = json_decode($params, true);
            }
            $params = is_array($params) ? $params : [];
            $params['custom_fields'] = $fields;
            $this->saveCustomFieldsToTable($service->id, 'server', $fields);
            $service->update(['params' => $params]);

            return response()->json(['ok' => true, 'count' => count($fields)]);
        });
    }
}
