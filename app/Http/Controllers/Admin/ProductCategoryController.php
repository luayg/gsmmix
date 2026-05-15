<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $rows = ProductCategory::query()
            ->withCount('products')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('ordering')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.store.categories.index', compact('rows', 'q', 'status', 'perPage'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:product_categories,name'],
            'active' => ['nullable', 'boolean'],
            'ordering' => ['nullable', 'integer', 'min:0'],
        ]);

        ProductCategory::create([
            'name' => $data['name'],
            'active' => $request->boolean('active', true),
            'ordering' => (int) ($data['ordering'] ?? 0),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Category created']);
    }

    public function update(Request $request, ProductCategory $category)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories', 'name')->ignore($category->id),
            ],
            'active' => ['nullable', 'boolean'],
            'ordering' => ['nullable', 'integer', 'min:0'],
        ]);

        $category->update([
            'name' => $data['name'],
            'active' => $request->boolean('active'),
            'ordering' => (int) ($data['ordering'] ?? 0),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Category updated']);
    }

    public function destroy(ProductCategory $category)
    {
        $category->delete();

        return response()->json(['ok' => true, 'msg' => 'Category deleted']);
    }

    public function modalCreate()
    {
        return view('admin.store.categories.modals.create');
    }

    public function modalView(ProductCategory $category)
    {
        $category->loadCount('products');

        return view('admin.store.categories.modals.view', compact('category'));
    }

    public function modalEdit(ProductCategory $category)
    {
        return view('admin.store.categories.modals.edit', compact('category'));
    }

    public function modalDelete(ProductCategory $category)
    {
        $category->loadCount('products');

        return view('admin.store.categories.modals.delete', compact('category'));
    }

    public function options()
    {
        return response()->json(
            ProductCategory::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'text' => $category->name,
                    'name' => $category->name,
                ])
                ->values()
        );
    }
}