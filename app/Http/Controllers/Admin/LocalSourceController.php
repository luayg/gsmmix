<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalSource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocalSourceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $rows = LocalSource::query()
            ->withCount('replies')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.local-sources.index', compact('rows', 'q', 'perPage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:local_sources,name'],
        ]);

        LocalSource::create($data);

        return response()->json(['ok' => true, 'msg' => 'Source created']);
    }

    public function update(Request $request, LocalSource $source)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('local_sources', 'name')->ignore($source->id),
            ],
        ]);

        $source->update($data);

        return response()->json(['ok' => true, 'msg' => 'Source updated']);
    }

    public function destroy(LocalSource $source)
    {
        $source->delete();

        return response()->json(['ok' => true, 'msg' => 'Source deleted']);
    }

    public function modalCreate()
    {
        return view('admin.local-sources.modals.create');
    }

    public function modalView(LocalSource $source)
    {
        $source->loadCount('replies');

        return view('admin.local-sources.modals.view', compact('source'));
    }

    public function modalEdit(LocalSource $source)
    {
        return view('admin.local-sources.modals.edit', compact('source'));
    }

    public function modalDelete(LocalSource $source)
    {
        $source->loadCount('replies');

        return view('admin.local-sources.modals.delete', compact('source'));
    }

    public function options()
    {
        return response()->json(
            LocalSource::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($source) => [
                    'id' => $source->id,
                    'text' => $source->name,
                    'name' => $source->name,
                ])
                ->values()
        );
    }
}