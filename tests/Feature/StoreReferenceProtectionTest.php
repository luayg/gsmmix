<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LocalSourceController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Models\LocalSource;
use App\Models\ProductCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StoreReferenceProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('local_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->unsignedBigInteger('local_source_id')->nullable();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('local_replies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('local_source_id')->nullable();
        });

        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('local_source_id')->nullable();
        });
    }

    public function test_source_delete_is_blocked_when_product_references_it(): void
    {
        $source = LocalSource::create(['name' => 'Pool A']);

        DB::table('products')->insert([
            'local_source_id' => $source->id,
            'name' => 'Product A',
            'alias' => 'product-a',
            'cost' => 1,
            'price' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(LocalSourceController::class)->destroy($source);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(1, $response->getData(true)['products']);
        $this->assertDatabaseHas('local_sources', ['id' => $source->id]);
    }

    public function test_category_delete_is_blocked_when_product_references_it(): void
    {
        $category = ProductCategory::create(['name' => 'Tools']);

        DB::table('products')->insert([
            'product_category_id' => $category->id,
            'name' => 'Product B',
            'alias' => 'product-b',
            'cost' => 1,
            'price' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(ProductCategoryController::class)->destroy($category);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(1, $response->getData(true)['products']);
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    public function test_unreferenced_source_and_category_can_be_deleted(): void
    {
        $source = LocalSource::create(['name' => 'Unused source']);
        $category = ProductCategory::create(['name' => 'Unused category']);

        $sourceResponse = app(LocalSourceController::class)->destroy($source);
        $categoryResponse = app(ProductCategoryController::class)->destroy($category);

        $this->assertSame(200, $sourceResponse->getStatusCode());
        $this->assertSame(200, $categoryResponse->getStatusCode());
        $this->assertDatabaseMissing('local_sources', ['id' => $source->id]);
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
    }
}
