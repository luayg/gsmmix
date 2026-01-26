<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiProvidersController extends Controller
{
    public function index()
    {
        return view('admin.api.providers.index');
    }

    public function create()
    {
        return view('admin.api.providers.create');
    }

    public function edit(ApiProvider $provider)
    {
        return view('admin.api.providers.edit', compact('provider'));
    }

    public function view(ApiProvider $provider)
    {
        return view('admin.api.providers.view', compact('provider'));
    }

    /**
     * Create provider (Store)
     */
    public function store(Request $request)
    {
        $data = $this->validateProvider($request);

        // Normalize url
        $data['url'] = rtrim((string)$data['url'], '/') . '/';

        $provider = ApiProvider::create($data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'provider' => $provider]);
        }

        return redirect()->route('admin.apis.index')->with('success', 'Provider created');
    }

    /**
     * Update provider
     */
    public function update(Request $request, ApiProvider $provider)
    {
        $data = $this->validateProvider($request, $provider->id);

        $data['url'] = rtrim((string)$data['url'], '/') . '/';

        $provider->update($data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'provider' => $provider]);
        }

        return redirect()->route('admin.apis.index')->with('success', 'Provider updated');
    }

    public function destroy(Request $request, ApiProvider $provider)
    {
        $provider->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.apis.index')->with('success', 'Provider deleted');
    }

    /**
     * Test connection = fetch balance only (no catalog sync)
     */
    public function testConnection(Request $request, ApiProvider $provider, ProviderManager $manager)
    {
        $result = $manager->sync($provider, null, true);

        return response()->json([
            'ok' => empty($result['errors']),
            'result' => $result,
        ]);
    }

    /**
     * Sync now (dispatch job)
     * You can pass: kind=imei|server|file
     */
    public function syncNow(Request $request, ApiProvider $provider)
    {
        $kind = $request->input('kind');
        if ($kind !== null && !in_array($kind, ['imei', 'server', 'file'], true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid kind'], 422);
        }

        dispatch(new SyncProviderJob((int)$provider->id, $kind ?: null, false));

        return response()->json(['ok' => true, 'message' => 'Sync dispatched']);
    }

    /**
     * Services list (remote)
     * kind: imei|server|file
     */
    public function services(Request $request, ApiProvider $provider, string $kind)
    {
        abort_unless(in_array($kind, ['imei', 'server', 'file'], true), 404);

        $query = match ($kind) {
            'imei' => $provider->remoteImeiServices(),
            'server' => $provider->remoteServerServices(),
            'file' => $provider->remoteFileServices(),
        };

        $services = $query->orderBy('group_name')->orderBy('name')->get();

        // If you want view modal:
        if (!$request->expectsJson()) {
            $grouped = $services->groupBy(function ($s) {
                return $s->group_name ?: 'Ungrouped';
            });

            return view('admin.api.providers.modals.services', [
                'provider' => $provider,
                'kind' => $kind,
                'services' => $services,
                'grouped' => $grouped,
            ]);
        }

        // JSON for frontend
        return response()->json([
            'ok' => true,
            'provider_id' => $provider->id,
            'kind' => $kind,
            'services' => $services,
        ]);
    }

    /**
     * Validation shared (store/update)
     */
    private function validateProvider(Request $request, ?int $providerId = null): array
    {
        $types = ['dhru', 'webx', 'gsmhub', 'unlockbase', 'simple_link'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . implode(',', $types)],
            'url' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],

            'sync_imei' => ['nullable'],
            'sync_server' => ['nullable'],
            'sync_file' => ['nullable'],

            'ignore_low_balance' => ['nullable'],
            'auto_sync' => ['nullable'],
            'active' => ['nullable'],

            'params' => ['nullable'],
        ]);

        // checkboxes normalization
        $data['sync_imei'] = $request->boolean('sync_imei');
        $data['sync_server'] = $request->boolean('sync_server');
        $data['sync_file'] = $request->boolean('sync_file');

        $data['ignore_low_balance'] = $request->boolean('ignore_low_balance');
        $data['auto_sync'] = $request->boolean('auto_sync');
        $data['active'] = $request->boolean('active');

        // normalize params: accept JSON string or array
        $params = $request->input('params');
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $data['params'] = is_array($decoded) ? $decoded : null;
        } elseif (is_array($params)) {
            $data['params'] = $params;
        } else {
            $data['params'] = null;
        }

        return $data;
    }
}
