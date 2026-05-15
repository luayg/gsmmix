<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalSource;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $categoryId = (int) $request->get('category_id', 0);
        $sourceId = (int) $request->get('source_id', 0);
        $status = trim((string) $request->get('status', ''));
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $rows = Product::query()
            ->with(['category', 'localSource'])
            ->withCount('orders')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($where) use ($q) {
                    $where->where('name', 'like', "%{$q}%")
                        ->orWhere('alias', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('product_category_id', $categoryId))
            ->when($sourceId > 0, fn ($query) => $query->where('local_source_id', $sourceId))
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('ordering')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $categories = ProductCategory::query()->orderBy('name')->get(['id', 'name']);
        $sources = LocalSource::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.store.products.index', compact(
            'rows',
            'categories',
            'sources',
            'q',
            'categoryId',
            'sourceId',
            'status',
            'perPage'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        Product::create($this->payload($request, $data));

        return response()->json(['ok' => true, 'msg' => 'Product created']);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product);

        $product->update($this->payload($request, $data));

        return response()->json(['ok' => true, 'msg' => 'Product updated']);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['ok' => true, 'msg' => 'Product deleted']);
    }

    public function modalCreate()
    {
        return view('admin.store.products.modals.create', $this->formData());
    }

    public function modalView(Product $product)
    {
        $product->load(['category', 'localSource'])->loadCount('orders');

        return view('admin.store.products.modals.view', compact('product'));
    }

    public function modalEdit(Product $product)
    {
        $data = $this->formData();
        $data['product'] = $product;

        return view('admin.store.products.modals.edit', $data);
    }

    public function modalDelete(Product $product)
    {
        $product->loadCount('orders');

        return view('admin.store.products.modals.delete', compact('product'));
    }

    public function options()
    {
        return response()->json(
            Product::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'price'])
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'text' => $product->name,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                ])
                ->values()
        );
    }

    private function formData(): array
    {
        return [
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
            'sources' => LocalSource::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'local_source_id' => ['nullable', 'integer', 'exists:local_sources,id'],
            'name' => ['required', 'string', 'max:255'],
            'alias' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'alias')->ignore($product?->id),
            ],
            'description' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'device_based' => ['nullable', 'boolean'],
            'ordering' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function payload(Request $request, array $data): array
    {
        return [
            'product_category_id' => $data['product_category_id'] ?? null,
            'local_source_id' => $data['local_source_id'] ?? null,
            'name' => $data['name'],
            'alias' => trim((string) ($data['alias'] ?? '')) ?: null,
            'description' => $data['description'] ?? null,
            'cost' => (float) ($data['cost'] ?? 0),
            'price' => (float) ($data['price'] ?? 0),
            'active' => $request->boolean('active'),
            'device_based' => $request->boolean('device_based'),
            'ordering' => (int) ($data['ordering'] ?? 0),
        ];
    }
}
