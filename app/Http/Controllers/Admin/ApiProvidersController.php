<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use Illuminate\Http\Request;

class ApiProvidersController extends Controller
{
    public function index(Request $request)
    {
        // Filters
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', '')); // active|inactive|synced|not_synced
        $perPage = (int) $request->query('per_page', 20);
        if ($perPage <= 0) $perPage = 20;
        if ($perPage > 200) $perPage = 200;

        $query = ApiProvider::query()->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('url', 'like', "%{$q}%")
                  ->orWhere('username', 'like', "%{$q}%");
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        if ($status !== '') {
            if ($status === 'active') {
                $query->where('active', 1);
            } elseif ($status === 'inactive') {
                $query->where('active', 0);
            } elseif ($status === 'synced') {
                $query->where('synced', 1);
            } elseif ($status === 'not_synced') {
                $query->where('synced', 0);
            }
        }

        $rows = $query->paginate($perPage)->withQueryString();

        // For filter dropdown
        $types = ['dhru', 'gsmhub', 'webx', 'unlockbase', 'simple_link'];

        return view('admin.api.providers.index', [
            'rows' => $rows,
            'types' => $types,
            'filters' => [
                'q' => $q,
                'type' => $type,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
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

    public function store(Request $request)
    {
        $data = $this->validateProvider($request);
        $data['url'] = rtrim((string)$data['url'], '/') . '/';

        $provider = ApiProvider::create($data);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'provider' => $provider]);
        }

        return redirect()->route('admin.apis.index')->with('success', 'Provider created');
    }

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

    public function testConnection(Request $request, ApiProvider $provider, ProviderManager $manager)
    {
        $result = $manager->sync($provider, null, true);

        return response()->json([
            'ok' => empty($result['errors']),
            'result' => $result,
        ]);
    }

    public function syncNow(Request $request, ApiProvider $provider)
    {
        $kind = $request->input('kind');
        if ($kind !== null && !in_array($kind, ['imei', 'server', 'file'], true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid kind'], 422);
        }

        dispatch(new SyncProviderJob((int)$provider->id, $kind ?: null, false));

        return response()->json(['ok' => true, 'message' => 'Sync dispatched']);
    }

    public function services(Request $request, ApiProvider $provider, string $kind)
    {
        abort_unless(in_array($kind, ['imei', 'server', 'file'], true), 404);

        $query = match ($kind) {
            'imei' => $provider->remoteImeiServices(),
            'server' => $provider->remoteServerServices(),
            'file' => $provider->remoteFileServices(),
        };

        $services = $query->orderBy('group_name')->orderBy('name')->get();

        if (!$request->expectsJson()) {
            $grouped = $services->groupBy(fn($s) => $s->group_name ?: 'Ungrouped');

            return view('admin.api.providers.modals.services', [
                'provider' => $provider,
                'kind' => $kind,
                'services' => $services,
                'grouped' => $grouped,
            ]);
        }

        return response()->json([
            'ok' => true,
            'provider_id' => $provider->id,
            'kind' => $kind,
            'services' => $services,
        ]);
    }

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

        $data['sync_imei'] = $request->boolean('sync_imei');
        $data['sync_server'] = $request->boolean('sync_server');
        $data['sync_file'] = $request->boolean('sync_file');

        $data['ignore_low_balance'] = $request->boolean('ignore_low_balance');
        $data['auto_sync'] = $request->boolean('auto_sync');
        $data['active'] = $request->boolean('active');

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
