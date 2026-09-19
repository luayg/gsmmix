<?php

namespace Tests\Feature;

use App\Models\LocalReply;
use App\Models\LocalSource;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\Group;
use App\Models\ServiceGroupPrice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Tests\Support\SecurityTestCase;
use Tests\Support\ProductOrderMysqlDatabase;
use Symfony\Component\Process\Process;

class ProductOrderWorkflowTest extends SecurityTestCase
{
    protected User $customer;
    protected User $admin;
    protected Product $product;

    protected function configureTestDatabase(): void
    {
        if (getenv('PRODUCT_ORDERS_TEST_MYSQL') !== '1') {
            parent::configureTestDatabase();
            return;
        }
        ProductOrderMysqlDatabase::configure();
        // The helper requires testing mode and one exact disposable database name.
        Schema::dropAllTables();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('balance', 14, 4)->default(0);
            $table->unsignedBigInteger('group_id')->nullable();
        });
        foreach ([
            '2026_01_31_000010_create_order_tables.php',
            '2026_05_14_000100_create_local_sources_and_replies_tables.php',
            '2026_05_14_000200_add_local_reply_links_to_product_orders_table.php',
            '2026_05_14_000300_create_store_tables.php',
            '2026_05_14_000400_add_product_id_to_product_orders_table.php',
            '2026_05_14_000500_add_product_form_options_to_products_table.php',
            '2026_09_15_000001_add_product_order_lifecycle.php',
            '2026_09_16_000001_link_products_to_services.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
        Schema::create('imei_services', function (Blueprint $table): void {
            $table->id();
            $table->boolean('active')->default(true);
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('remote_id')->nullable();
            $table->timestamps();
        });
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
            $table->unsignedBigInteger('group_id');
            $table->decimal('price', 12, 4)->default(0);
            $table->boolean('auto_price')->default(false);
            $table->decimal('discount', 12, 4)->default(0);
            $table->unsignedTinyInteger('discount_type')->default(1);
            $table->timestamps();
            $table->unique(['service_type', 'service_id', 'group_id']);
        });
        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
            $table->string('name');
            $table->string('input')->nullable();
            $table->string('field_type')->default('text');
            $table->text('field_options')->nullable();
            $table->string('description')->nullable();
            $table->unsignedInteger('minimum')->default(0);
            $table->unsignedInteger('maximum')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('ordering')->default(0);
        });
        $this->admin = $this->user('Administrator');
        $this->customer = $this->user();
        $this->customer->forceFill(['balance' => '100.1234'])->save();
        $this->product = Product::create(['name' => 'Local product', 'price' => '12.34', 'cost' => '8.10', 'active' => true]);
        $this->actingAs($this->admin);
    }

    protected function payload(array $extra = []): array
    {
        return array_replace([
            'request_uid' => (string) Str::uuid(),
            'user_id' => $this->customer->id,
            'product_id' => $this->product->id,
        ], $extra);
    }

    protected function balance(): string
    {
        return number_format((float) $this->customer->fresh()->balance, 4, '.', '');
    }

    protected function localStock(array $attributes = []): LocalReply
    {
        $source = LocalSource::create(['name' => 'Stock ' . Str::uuid()]);
        $this->product->update(['source_type' => 'local_source', 'local_source_id' => $source->id]);
        return LocalReply::create(array_replace([
            'local_source_id' => $source->id, 'device_based' => false, 'reply' => 'SYNTHETIC-CODE-1',
        ], $attributes));
    }

    public function test_manual_order_uses_catalog_credits_and_retry_never_charges_again(): void
    {
        $data = $this->payload(['price' => 0, 'status' => 'success', 'quantity' => 99]);
        $first = $this->postJson(route('admin.orders.product.store'), $data)->assertOk();
        $this->assertSame('waiting', $first->json('status'));
        $this->assertSame('87.7834', $this->balance());
        $this->product->update(['price' => '30.00']);
        $this->postJson(route('admin.orders.product.store'), $data)->assertOk()->assertJsonPath('id', $first->json('id'));
        $this->assertSame('87.7834', $this->balance());
        $this->assertDatabaseCount('product_orders', 1);
        $order = ProductOrder::firstOrFail();
        $this->assertSame('12.3400', $order->request['charged_amount']);
        $this->assertSame(1, $order->request['quantity']);
        $this->assertSame('12.34', $order->order_price);
        Http::assertNothingSent();
    }

    public function test_service_product_creates_one_linked_service_order_without_double_charge(): void
    {
        $serviceId = DB::table('imei_services')->insertGetId([
            'active' => true,
            'cost' => 4.25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->product->update([
            'source_type' => 'service',
            'service_type' => 'imei',
            'service_id' => $serviceId,
        ]);

        $response = $this->postJson(route('admin.orders.product.store'), $this->payload(['device' => '123456789012345']))
            ->assertOk()->assertJsonPath('status', 'waiting');

        $order = ProductOrder::findOrFail($response->json('id'));
        $this->assertSame('imei', $order->service_order_type);
        $this->assertNotNull($order->service_order_id);
        $this->assertDatabaseHas('imei_orders', [
            'id' => $order->service_order_id,
            'service_id' => $serviceId,
            'user_id' => $this->customer->id,
            'device' => '123456789012345',
            'status' => 'waiting',
        ]);
        $linked = DB::table('imei_orders')->find($order->service_order_id);
        $request = json_decode($linked->request, true);
        $this->assertSame(0, $request['charged_amount']);
        $this->assertSame($order->id, $request['product_order_id']);
        $this->assertSame('87.7834', $this->balance());

        auth()->guard('web')->logout();
        $this->flushSession();
        $this->resetSessionRuntime();
        $this->actingAs($this->customer, 'web')->get(route('customer.orders.type','product'))->assertOk()
            ->assertSee('Local product');
        $this->get(route('customer.orders.type','imei'))->assertOk()
            ->assertDontSee('Imei order');
    }

    public function test_admin_can_reject_a_service_product_and_linked_order_from_the_product_editor(): void
    {
        $serviceId = DB::table('imei_services')->insertGetId([
            'active' => true, 'cost' => 4.25, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->product->update(['source_type' => 'service', 'service_type' => 'imei', 'service_id' => $serviceId]);
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload(['device' => '123456789012345']))
            ->assertOk()->json('id');
        $order = ProductOrder::findOrFail($id);

        $this->get(route('admin.orders.product.modal.edit', $id))->assertOk()
            ->assertSee('Reply HTML')->assertSee('data-editor="summernote"', false)
            ->assertSee('<option value="rejected"', false)->assertSee('<option value="success"', false);
        $this->putJson(route('admin.orders.product.update', $id), [
            'status' => 'rejected', 'provider_reply_html' => '<p>Rejected by administrator</p>',
        ])->assertOk();

        $this->assertSame('100.1234', $this->balance());
        $this->assertDatabaseHas('product_orders', ['id' => $id, 'status' => 'rejected']);
        $this->assertDatabaseHas('imei_orders', ['id' => $order->service_order_id, 'status' => 'rejected']);
        $this->assertStringContainsString('Rejected by administrator', ProductOrder::findOrFail($id)->response);
    }

    public function test_service_product_uses_and_validates_the_linked_service_fields(): void
    {
        $serviceId = DB::table('imei_services')->insertGetId([
            'active' => true, 'cost' => 4.25, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('custom_fields')->insert([
            'service_id' => $serviceId, 'service_type' => 'imei_service', 'name' => 'Account email',
            'input' => 'account_email', 'field_type' => 'email', 'required' => true, 'active' => true,
        ]);
        $this->product->update(['source_type' => 'service', 'service_type' => 'imei', 'service_id' => $serviceId]);

        $this->actingAs($this->customer)->get(route('site.store'))
            ->assertOk()->assertSee('Account email')->assertSee('account_email');
        $this->postJson(route('customer.product-orders.store'), $this->payload(['device' => '123456789012345']))
            ->assertUnprocessable()->assertJsonValidationErrors('required.account_email');
        $this->postJson(route('customer.product-orders.store'), $this->payload([
            'device' => '123456789012345', 'required' => ['account_email' => 'not-an-email'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('required.account_email');
        $this->postJson(route('customer.product-orders.store'), $this->payload([
            'device' => '123456789012345', 'required' => ['account_email' => 'buyer@example.test'],
        ]))->assertOk();

        $linked = DB::table('imei_orders')->latest('id')->first();
        $this->assertSame('buyer@example.test', json_decode($linked->params, true)['fields']['account_email']);
    }

    public function test_product_order_uses_the_customer_group_price(): void
    {
        $group = Group::create(['name' => 'VIP']);
        $this->customer->update(['group_id' => $group->id]);
        ServiceGroupPrice::create([
            'service_type' => 'product', 'service_id' => $this->product->id, 'group_id' => $group->id,
            'price' => 10, 'auto_price' => false, 'discount' => 2, 'discount_type' => 1,
        ]);

        $response = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk();
        $this->assertDatabaseHas('product_orders', ['id' => $response->json('id'), 'order_price' => 8]);
        $this->assertSame('92.1234', $this->balance());
    }

    public function test_product_creation_accepts_only_a_valid_source_configuration(): void
    {
        Storage::fake('public');
        $group = Group::create(['name' => 'VIP']);
        $source = LocalSource::create(['name' => 'Codes']);
        $serviceId = DB::table('imei_services')->insertGetId([
            'active' => true,
            'cost' => 7.25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson(route('admin.store.products.store'), [
            'name' => 'Manual product', 'price' => 3, 'source_type' => 'manual', 'active' => 1,
            'main_image_file' => UploadedFile::fake()->image('product.png', 120, 120),
        ])->assertOk();
        $this->postJson(route('admin.store.products.store'), [
            'name' => 'Stock product', 'price' => 4, 'source_type' => 'local_source',
            'local_source_id' => $source->id, 'active' => 1,
        ])->assertOk();
        $this->postJson(route('admin.store.products.store'), [
            'name' => 'Service product', 'price' => 999, 'cost' => 0, 'profit' => 2,
            'profit_type' => 'credits', 'source_type' => 'service',
            'service_type' => 'imei', 'service_id' => $serviceId, 'active' => 1,
            'group_prices' => [$group->id => ['price' => 0, 'auto_price' => 1, 'discount' => 1, 'discount_type' => 1]],
        ])->assertOk();

        $this->assertDatabaseHas('products', [
            'name' => 'Manual product', 'source_type' => 'manual',
            'local_source_id' => null, 'service_type' => null, 'service_id' => null,
        ]);
        $serviceProductId = Product::where('name', 'Service product')->value('id');
        $this->assertDatabaseHas('service_group_prices', [
            'service_type' => 'product', 'service_id' => $serviceProductId, 'group_id' => $group->id,
            'price' => 9.25, 'auto_price' => 1, 'discount' => 1,
        ]);
        $manualImage = Product::where('name', 'Manual product')->value('main_image');
        $this->assertStringStartsWith('/storage/products/', $manualImage);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $manualImage));
        $this->assertDatabaseHas('products', [
            'name' => 'Stock product', 'source_type' => 'local_source', 'local_source_id' => $source->id,
        ]);
        $this->assertDatabaseHas('products', [
            'name' => 'Service product', 'source_type' => 'service',
            'service_type' => 'imei', 'service_id' => $serviceId,
            'cost' => 7.25, 'profit' => 2, 'price' => 9.25,
        ]);
        $this->get(route('admin.store.products.modal.create'))
            ->assertOk()
            ->assertSee('data-service-cost="7.25"', false)
            ->assertSee('name="main_image_file"', false)
            ->assertSee('class="form-control summernote"', false)
            ->assertSee('data-summernote-hidden="#infoHidden"', false)
            ->assertSee('data-product-group-price', false)
            ->assertSee('VIP');
        $this->postJson(route('admin.store.products.store'), [
            'name' => 'Broken product', 'price' => 1, 'source_type' => 'service',
            'service_type' => 'imei', 'service_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors('service_id');
    }

    public function test_local_delivery_links_both_sides_and_cannot_reuse_last_reply(): void
    {
        $reply = $this->localStock();
        $first = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('product_orders', ['id' => $first->json('id'), 'local_reply_id' => $reply->id, 'response' => 'SYNTHETIC-CODE-1']);
        $this->assertSame((int) $first->json('id'), (int) $reply->fresh()->used_by_product_order_id);
        $this->assertNotNull($reply->fresh()->used_at);
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->assertDatabaseCount('product_orders', 1);
        $this->assertSame('87.7834', $this->balance());
        $this->artisan('replies:integrity-audit', ['--details' => 0])->assertExitCode(0);
        $this->artisan('products:integrity-audit', ['--details' => 0])->assertExitCode(0);
    }

    public function test_insufficient_credit_and_inactive_records_leave_stock_and_balance_untouched(): void
    {
        $reply = $this->localStock();
        $this->customer->forceFill(['balance' => '1.1234'])->save();
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertUnprocessable();
        $this->assertSame('1.1234', $this->balance());
        $this->assertNull($reply->fresh()->used_at);
        $this->assertDatabaseCount('product_orders', 0);
        $this->customer->forceFill(['balance' => '100.1234', 'status' => 'inactive'])->save();
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertUnprocessable();
        $this->customer->forceFill(['status' => 'active'])->save();
        $this->product->update(['active' => false]);
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('product_orders', 0);
        $this->assertNull($reply->fresh()->used_by_product_order_id);
    }

    public function test_device_matching_skips_expired_stock_and_rejects_missing_identifier(): void
    {
        $expired = $this->localStock(['device_based' => true, 'device_identifier' => 'DEVICE-1', 'expires_at' => now()->subMinute()]);
        $this->product->update(['device_based' => true]);
        $other = LocalReply::create(['local_source_id' => $expired->local_source_id, 'device_based' => true, 'device_identifier' => 'OTHER', 'reply' => 'OTHER-CODE']);
        $valid = LocalReply::create(['local_source_id' => $expired->local_source_id, 'device_based' => true, 'device_identifier' => 'DEVICE-1', 'reply' => 'MATCHING-CODE']);
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('device');
        $this->postJson(route('admin.orders.product.store'), $this->payload(['device' => 'DEVICE-1']))->assertOk();
        $this->assertSame($valid->id, (int) ProductOrder::firstOrFail()->local_reply_id);
        $this->assertNull($expired->fresh()->used_at);
        $this->assertNull($other->fresh()->used_at);
    }

    public function test_cancellation_refunds_once_and_reactivation_uses_original_price_once(): void
    {
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $url = route('admin.orders.product.update', $id);
        foreach (['cancelled', 'cancelled', 'rejected'] as $status) {
            $this->putJson($url, ['status' => $status])->assertOk();
            $this->assertSame('100.1234', $this->balance());
        }
        $this->product->update(['price' => '90.00']);
        foreach (['waiting', 'waiting', 'inprogress'] as $status) {
            $this->putJson($url, ['status' => $status])->assertOk();
            $this->assertSame('87.7834', $this->balance());
        }
        $this->putJson($url, ['status' => 'success'])->assertUnprocessable()->assertJsonValidationErrors('response');
        $this->putJson($url, ['status' => 'success', 'response' => 'Manual delivery result'])->assertOk();
        $this->putJson($url, ['status' => 'success', 'comments' => 'No extra debit'])->assertOk();
        $this->assertSame('87.7834', $this->balance());
        $this->assertSame('charged', ProductOrder::findOrFail($id)->request['financial_state']);
    }

    public function test_insufficient_recharge_keeps_cancelled_order_and_comments_intact(): void
    {
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload(['comments' => 'Keep']))->json('id');
        $url = route('admin.orders.product.update', $id);
        $this->putJson($url, ['status' => 'cancelled'])->assertOk();
        $this->customer->forceFill(['balance' => '1.1234'])->save();
        $this->putJson($url, ['status' => 'waiting', 'comments' => 'Changed'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('product_orders', ['id' => $id, 'status' => 'cancelled', 'comments' => 'Keep']);
        $this->assertSame('1.1234', $this->balance());
    }

    public function test_delivered_result_can_be_corrected_but_cannot_be_refunded_or_recycled(): void
    {
        $reply = $this->localStock(['reply' => '<script>stockSecret()</script>']);
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $this->get(route('admin.orders.product.show', $id))->assertOk()
            ->assertSee('&lt;script&gt;stockSecret()&lt;/script&gt;', false)
            ->assertDontSee('<script>stockSecret()</script>', false);
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'cancelled'])->assertUnprocessable();
        $this->putJson(route('admin.orders.product.update', $id), [
            'status' => 'success', 'provider_reply_html' => '<p>Corrected reply</p>',
        ])->assertOk();
        $this->assertStringContainsString('Corrected reply', ProductOrder::findOrFail($id)->response);
        $this->assertSame('87.7834', $this->balance());
        $this->assertSame($id, (int) $reply->fresh()->used_by_product_order_id);
    }

    public function test_historical_order_requires_review_before_financial_transition(): void
    {
        $id = DB::table('product_orders')->insertGetId(['product_id' => $this->product->id, 'user_id' => $this->customer->id, 'status' => 'waiting', 'order_price' => 20]);
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'cancelled'])->assertUnprocessable();
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'waiting', 'comments' => 'Historical note'])->assertOk();
        $this->assertSame('100.1234', $this->balance());
        $this->assertNull(ProductOrder::findOrFail($id)->request);
    }

    public function test_view_only_staff_cannot_create_or_change_orders(): void
    {
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $staff = $this->user();
        $staff->givePermissionTo(['admin.access', 'orders.view']);
        auth()->guard('web')->logout();
        $this->flushSession();
        $this->resetSessionRuntime();
        $this->actingAs($staff, 'web');
        $this->get(route('admin.orders.product.index'))->assertOk()->assertDontSee('New order');
        $this->get(route('admin.orders.product.show', $id))->assertOk()->assertDontSee('Save changes');
        $this->get(route('admin.orders.product.create'))->assertForbidden();
        $this->get(route('admin.orders.product.modal.create'))->assertForbidden();
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertForbidden();
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'cancelled'])->assertForbidden();
        $this->assertSame('87.7834', $this->balance());
    }

    public function test_form_lists_products_and_index_filters_real_orders(): void
    {
        $this->get(route('admin.orders.product.create'))->assertOk()->assertSee('Local product')->assertSee('12.34 credits');
        $this->get(route('admin.orders.product.modal.create'))->assertOk()->assertSee('name="request_uid"', false);
        $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk();
        $this->get(route('admin.orders.product.index', ['q' => 'Local product', 'status' => 'waiting']))
            ->assertOk()->assertSee('Local product')->assertSee('Provider')->assertSee('Actions')->assertSee('WAITING');
        $this->get(route('admin.orders.product.index', ['status' => 'success']))->assertOk()->assertSee('No product orders');
    }

    public function test_product_order_admin_actions_open_modals_and_customer_sees_it_in_all_orders(): void
    {
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload(['device' => 'PRODUCT-TARGET']))
            ->assertOk()->json('id');

        $this->get(route('admin.orders.product.index'))->assertOk()
            ->assertSee(route('admin.orders.product.modal.view', $id), false)
            ->assertSee(route('admin.orders.product.modal.edit', $id), false)
            ->assertSee('js-open-modal', false);
        $this->get(route('admin.orders.product.modal.view', $id))->assertOk()
            ->assertSee('View Product Order #'.$id)->assertSee('PRODUCT-TARGET');
        $this->get(route('admin.orders.product.modal.edit', $id))->assertOk()
            ->assertSee('Product Order #'.$id)->assertSee('js-ajax-form', false);

        if (!Schema::hasTable('smm_orders')) {
            Schema::create('smm_orders', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }
        auth()->guard('web')->logout();
        $this->flushSession();
        $this->resetSessionRuntime();
        $this->actingAs($this->customer, 'web')->get(route('customer.orders'))->assertOk()
            ->assertSee('Local product')->assertSee('PRODUCT-TARGET')->assertSee('Waiting')
            ->assertSee('Product orders');
    }

    public function test_submission_key_cannot_be_reused_for_a_different_product_or_customer(): void
    {
        $data = $this->payload();
        $this->postJson(route('admin.orders.product.store'), $data)->assertOk();
        $other = Product::create(['name' => 'Other product', 'price' => 1, 'active' => true]);
        $this->postJson(route('admin.orders.product.store'), array_replace($data, ['product_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('request_uid');
        $this->postJson(route('admin.orders.product.store'), array_replace($data, ['user_id' => $this->admin->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('request_uid');
        $this->assertDatabaseCount('product_orders', 1);
        $this->assertSame('87.7834', $this->balance());
    }

    public function test_free_order_and_repeated_migration_preserve_financial_history(): void
    {
        $this->product->update(['price' => 0]);
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'cancelled'])->assertOk();
        $this->assertSame('refunded', ProductOrder::findOrFail($id)->request['financial_state']);
        $snapshot = (array) DB::table('product_orders')->where('id', $id)->first();
        (require database_path('migrations/2026_09_15_000001_add_product_order_lifecycle.php'))->up();
        $this->assertSame($snapshot, (array) DB::table('product_orders')->where('id', $id)->first());
        $this->assertSame('100.1234', $this->balance());
    }

    public function test_simultaneous_retries_create_one_order_and_one_charge_on_mariadb(): void
    {
        $this->requireMysql();
        $this->localStock();
        $payload = $this->payload();
        $results = $this->runTogether([$payload, $payload]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('product_orders', 1);
        $this->assertSame('87.7834', $this->balance());
    }

    public function test_two_customers_competing_for_last_reply_only_charge_the_winner_on_mariadb(): void
    {
        $this->requireMysql();
        $this->localStock();
        $other = $this->user();
        $other->forceFill(['balance' => '100.1234'])->save();
        $results = $this->runTogether([$this->payload(), $this->payload(['user_id' => $other->id])]);
        $this->assertCount(1, array_filter($results, fn ($result) => $result['ok']));
        $this->assertDatabaseCount('product_orders', 1);
        $balances = [$this->balance(), number_format((float) $other->fresh()->balance, 4, '.', '')];
        sort($balances, SORT_STRING);
        $this->assertSame(['100.1234', '87.7834'], $balances);
        $this->assertSame(1, LocalReply::whereNotNull('used_by_product_order_id')->count());
    }

    private function requireMysql(): void
    {
        if (getenv('PRODUCT_ORDERS_TEST_MYSQL') !== '1') {
            $this->markTestSkipped('Concurrent worker tests run in the disposable MariaDB workflow.');
        }
    }

    private function runTogether(array $payloads): array
    {
        $workers = [];
        DB::beginTransaction();
        try {
            User::whereIn('id', array_column($payloads, 'user_id'))->lockForUpdate()->get();
            foreach ($payloads as $payload) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/product-order-worker.php'),
                    json_encode($payload, JSON_THROW_ON_ERROR), (string) $this->admin->id], base_path(), ['APP_ENV' => 'testing']);
                $process->setTimeout(25);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 10;
            do {
                $ready = count(array_filter($workers, fn ($worker) => str_contains($worker->getOutput(), 'READY')));
                if ($ready === count($workers)) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(count($workers), $ready, 'Both workers must contend before the parent releases the customer locks.');
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput() . $worker->getOutput());
                $lines = explode("\n", trim($worker->getOutput()));
                $results[] = json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }
}
