<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalSource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Group;
use App\Models\ServiceGroupPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Support\ProductService;
use Illuminate\Validation\ValidationException;

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
        DB::transaction(function () use ($request, $data): void {
            $product = Product::create($this->payload($request, $data));
            $this->saveGroupPrices($product, $data['group_prices'] ?? []);
        });
        return response()->json(['ok' => true, 'msg' => 'Product created']);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validateProduct($request, $product);
        DB::transaction(function () use ($request, $data, $product): void {
            $product->update($this->payload($request, $data));
            if (array_key_exists('group_prices', $data)) {
                $this->saveGroupPrices($product, $data['group_prices']);
            }
        });
        return response()->json(['ok' => true, 'msg' => 'Product updated']);
    }

    public function destroy(Product $product)
    {
        $orderCount = Schema::hasTable('product_orders') && Schema::hasColumn('product_orders', 'product_id')
            ? DB::table('product_orders')->where('product_id', $product->id)->count()
            : 0;

        if ($orderCount > 0) {
            return response()->json([
                'ok' => false,
                'msg' => "Can't delete this product: {$orderCount} product order(s) still reference it.",
                'product_orders' => $orderCount,
            ], 409);
        }

        if (Schema::hasTable('service_group_prices')) {
            ServiceGroupPrice::query()->where('service_type', 'product')->where('service_id', $product->id)->delete();
        }
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
        $data = $this->formData($product);
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

    private function formData(?Product $product = null): array
    {
        $customerGroups = Schema::hasTable('groups')
            ? Group::query()->orderBy('id')->get(['id', 'name'])
            : collect();
        $productGroupPrices = $product && Schema::hasTable('service_group_prices')
            ? ServiceGroupPrice::query()->where('service_type', 'product')->where('service_id', $product->id)
                ->get()->keyBy('group_id')
            : collect();

        return [
            'categories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
            'sources' => LocalSource::query()->orderBy('name')->get(['id', 'name']),
            'serviceOptions' => ProductService::options(),
            'customerGroups' => $customerGroups,
            'productGroupPrices' => $productGroupPrices,
        ];
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'source_type' => ['required', Rule::in(['manual', 'service', 'local_source'])],
            'local_source_id' => ['nullable', 'required_if:source_type,local_source', 'integer', 'exists:local_sources,id'],
            'service_type' => ['nullable', 'required_if:source_type,service', Rule::in(ProductService::TYPES)],
            'service_id' => ['nullable', 'required_if:source_type,service', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:255', Rule::unique('products', 'alias')->ignore($product?->id)],
            'description' => ['nullable', 'string'],
            'main_image' => ['nullable', 'string', 'max:255'],
            'main_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'converted_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'profit' => ['nullable', 'numeric', 'min:0'],
            'profit_type' => ['nullable', 'string', 'in:credits,percent'],
            'active' => ['nullable', 'boolean'],
            'device_based' => ['nullable', 'boolean'],
            'unlimited' => ['nullable', 'boolean'],
            'hot' => ['nullable', 'boolean'],
            'new' => ['nullable', 'boolean'],
            'sale' => ['nullable', 'boolean'],
            'ordering' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_description' => ['nullable', 'string'],
            'group_prices' => ['nullable', 'array'],
            'group_prices.*' => ['array'],
            'group_prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'group_prices.*.auto_price' => ['sometimes', 'boolean'],
            'group_prices.*.discount' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'group_prices.*.discount_type' => ['nullable', 'integer', 'in:1,2'],
        ]);

        if ($validated['source_type'] === 'service') {
            $service = ProductService::find((string) $validated['service_type'], (int) $validated['service_id']);
            if (!$service) {
                throw ValidationException::withMessages(['service_id' => 'Choose a service that exists in the selected service type.']);
            }
        }

        return $validated;
    }

    private function saveGroupPrices(Product $product, array $rows): void
    {
        if (!Schema::hasTable('service_group_prices') || !Schema::hasTable('groups')) {
            return;
        }
        $validGroups = Group::query()->whereIn('id', array_keys($rows))->pluck('id')->map(fn ($id) => (int) $id)->all();
        ServiceGroupPrice::query()->where('service_type', 'product')->where('service_id', $product->id)
            ->whereNotIn('group_id', $validGroups ?: [0])->delete();

        foreach ($rows as $groupId => $row) {
            $groupId = (int) $groupId;
            if ($groupId < 1 || !in_array($groupId, $validGroups, true)) {
                throw ValidationException::withMessages(["group_prices.{$groupId}" => 'Choose an existing customer group.']);
            }
            $automatic = (bool) ($row['auto_price'] ?? false);
            $price = $automatic ? (float) $product->price : (float) ($row['price'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $discountType = (int) ($row['discount_type'] ?? 1);
            if (($discountType === 2 && $discount > 100) || ($discountType === 1 && $discount > $price)) {
                throw ValidationException::withMessages(["group_prices.{$groupId}.discount" => 'The discount cannot exceed the group price.']);
            }
            ServiceGroupPrice::updateOrCreate(
                ['service_type' => 'product', 'service_id' => $product->id, 'group_id' => $groupId],
                ['price' => $price, 'auto_price' => $automatic, 'discount' => $discount, 'discount_type' => $discountType]
            );
        }
    }

    private function payload(Request $request, array $data): array
    {
        $profitType = $data['profit_type'] ?? 'credits';
        $cost = (float) ($data['cost'] ?? 0);
        $profit = (float) ($data['profit'] ?? 0);
        $mainImage = trim((string) ($data['main_image'] ?? '')) ?: null;

        if ($request->hasFile('main_image_file')) {
            $path = $request->file('main_image_file')->store('products', 'public');
            $mainImage = Storage::disk('public')->url($path);
        }

        if ($data['source_type'] === 'service') {
            $service = ProductService::find((string) $data['service_type'], (int) $data['service_id']);
            $cost = (float) ($service?->cost ?? 0);
            $data['price'] = $profitType === 'percent'
                ? $cost + ($cost * $profit / 100)
                : $cost + $profit;
        }

        return [
            'product_category_id' => $data['product_category_id'] ?? null,
            'source_type' => $data['source_type'],
            'local_source_id' => $data['source_type'] === 'local_source' ? ($data['local_source_id'] ?? null) : null,
            'service_type' => $data['source_type'] === 'service' ? ($data['service_type'] ?? null) : null,
            'service_id' => $data['source_type'] === 'service' ? (int) ($data['service_id'] ?? 0) : null,
            'name' => $data['name'],
            'alias' => trim((string) ($data['alias'] ?? '')) ?: null,
            'main_image' => $mainImage,
            'description' => $data['description'] ?? null,
            'delivery_time' => trim((string) ($data['delivery_time'] ?? '')) ?: null,
            'cost' => $cost,
            'price' => (float) ($data['price'] ?? 0),
            'converted_price' => (float) ($data['converted_price'] ?? 0),
            'currency' => trim((string) ($data['currency'] ?? 'USD')) ?: 'USD',
            'profit' => $profit,
            'profit_type' => in_array($profitType, ['credits', 'percent'], true)
                ? $profitType : 'credits',
            'active' => $request->boolean('active'),
            'device_based' => $request->boolean('device_based'),
            'unlimited' => $request->boolean('unlimited'),
            'hot' => $request->boolean('hot'),
            'new' => $request->boolean('new'),
            'sale' => $request->boolean('sale'),
            'ordering' => (int) ($data['ordering'] ?? 0),
            'meta_title' => trim((string) ($data['meta_title'] ?? '')) ?: null,
            'meta_keywords' => trim((string) ($data['meta_keywords'] ?? '')) ?: null,
            'meta_description' => trim((string) ($data['meta_description'] ?? '')) ?: null,
        ];
    }
}
