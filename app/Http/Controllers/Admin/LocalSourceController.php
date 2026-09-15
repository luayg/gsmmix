<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        return DB::transaction(function () use ($source) {
            $source = LocalSource::query()->lockForUpdate()->findOrFail($source->id);
            $replyCount = Schema::hasTable('local_replies')
                ? DB::table('local_replies')->where('local_source_id', $source->id)->count()
                : 0;

            $orderCount = Schema::hasTable('product_orders') && Schema::hasColumn('product_orders', 'local_source_id')
                ? DB::table('product_orders')->where('local_source_id', $source->id)->count()
                : 0;

            $productCount = Schema::hasTable('products') && Schema::hasColumn('products', 'local_source_id')
                ? DB::table('products')->where('local_source_id', $source->id)->count()
                : 0;

            if ($replyCount > 0 || $orderCount > 0 || $productCount > 0) {
                return response()->json([
                    'ok' => false,
                    'msg' => "Can't delete this source: {$replyCount} reply/replies, {$productCount} product(s), and {$orderCount} product order(s) still reference it.",
                    'replies' => $replyCount,
                    'products' => $productCount,
                    'product_orders' => $orderCount,
                ], 409);
            }

            $source->delete();

            return response()->json(['ok' => true, 'msg' => 'Source deleted']);
        }, 3);
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
