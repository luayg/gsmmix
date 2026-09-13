<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveApiProviderRequest;
use App\Models\ApiProvider;
use Illuminate\Http\RedirectResponse;

/** Credential writes are separated from the large import/sync controller. */
final class ApiProviderWriteController extends Controller
{
    public function store(SaveApiProviderRequest $request): RedirectResponse
    {
        ApiProvider::create($request->providerData());
        return redirect()->route('admin.apis.index')->with('ok', 'API Provider created.');
    }

    public function update(SaveApiProviderRequest $request, ApiProvider $provider): RedirectResponse
    {
        $provider->update($request->providerData($provider));
        return redirect()->route('admin.apis.index')->with('ok', 'API Provider updated.');
    }
}
