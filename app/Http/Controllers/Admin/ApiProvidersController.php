<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Jobs\SyncProviderJob;
use Illuminate\Http\Request;

class ApiProvidersController extends Controller
{
    public function index(Request $request)
    {
        $rows = ApiProvider::query()
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->status !== null, fn($q) => $q->where('active', $request->status))
            ->orderBy('id')
            ->paginate(20);

        return view('admin.api.providers.index', compact('rows'));
    }

    /**
     * Sync now (async)
     */
    public function sync(ApiProvider $provider)
    {
        SyncProviderJob::dispatch($provider->id);

        return back()->with('success', "Sync queued: {$provider->name} (will run in background)");
    }
}
