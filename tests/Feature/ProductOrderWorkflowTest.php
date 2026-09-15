<?php

namespace Tests\Feature;

use App\Models\LocalReply;
use App\Models\LocalSource;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
        Schema::table('users', fn (Blueprint $table) => $table->decimal('balance', 14, 4)->default(0));
        foreach ([
            '2026_01_31_000010_create_order_tables.php',
            '2026_05_14_000100_create_local_sources_and_replies_tables.php',
            '2026_05_14_000200_add_local_reply_links_to_product_orders_table.php',
            '2026_05_14_000300_create_store_tables.php',
            '2026_05_14_000400_add_product_id_to_product_orders_table.php',
            '2026_05_14_000500_add_product_form_options_to_products_table.php',
            '2026_09_15_000001_add_product_order_lifecycle.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
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
        $this->product->update(['local_source_id' => $source->id]);
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

    public function test_delivered_result_is_escaped_and_cannot_be_replaced_refunded_or_recycled(): void
    {
        $reply = $this->localStock(['reply' => '<script>stockSecret()</script>']);
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $this->get(route('admin.orders.product.show', $id))->assertOk()
            ->assertSee('&lt;script&gt;stockSecret()&lt;/script&gt;', false)
            ->assertDontSee('<script>stockSecret()</script>', false);
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'cancelled'])->assertUnprocessable();
        $this->putJson(route('admin.orders.product.update', $id), ['status' => 'success', 'response' => 'Replace'])->assertUnprocessable();
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
        $this->flushSession();
        $this->actingAs($staff);
        $this->get(route('admin.orders.product.index'))->assertOk()->assertDontSee('Create order');
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
        $this->get(route('admin.orders.product.index', ['q' => 'Local product', 'status' => 'waiting']))->assertOk()->assertSee('Local product');
        $this->get(route('admin.orders.product.index', ['status' => 'success']))->assertOk()->assertSee('No product orders match');
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

    public function test_used_reply_content_and_reference_deletions_are_protected(): void
    {
        $reply = $this->localStock();
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $snapshot = (array) DB::table('local_replies')->where('id', $reply->id)->first();
        $this->putJson(route('admin.replies.update', $reply->id), [
            'local_source_id' => $reply->local_source_id, 'reply' => 'Replace delivered stock',
        ])->assertStatus(409);
        $this->deleteJson(route('admin.replies.destroy', $reply->id))->assertStatus(409);
        $this->deleteJson(route('admin.store.products.destroy', $this->product->id))->assertStatus(409);
        $this->deleteJson(route('admin.sources.destroy', $reply->local_source_id))->assertStatus(409);
        $this->assertSame($snapshot, (array) DB::table('local_replies')->where('id', $reply->id)->first());
        $this->assertDatabaseHas('product_orders', ['id' => $id, 'local_reply_id' => $reply->id, 'product_id' => $this->product->id]);
    }

    public function test_simultaneous_cancellation_and_reactivation_move_credits_once_on_mariadb(): void
    {
        $this->requireMysql();
        $id = $this->postJson(route('admin.orders.product.store'), $this->payload())->assertOk()->json('id');
        $update = ['_action' => 'update', 'id' => $id, 'user_id' => $this->customer->id, 'status' => 'cancelled'];
        $this->runTogether([$update, $update]);
        $this->assertSame('100.1234', $this->balance());
        $this->assertSame('refunded', ProductOrder::findOrFail($id)->request['financial_state']);
        $update['status'] = 'waiting';
        $this->runTogether([$update, $update]);
        $this->assertSame('87.7834', $this->balance());
        $this->assertSame('charged', ProductOrder::findOrFail($id)->request['financial_state']);
    }

    public static function deletionTargets(): array
    {
        return [['product'], ['reply']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deletionTargets')]
    public function test_concurrent_stock_deletion_rechecks_the_committed_order_on_mariadb(string $target): void
    {
        $this->requireMysql();
        $reply = $this->localStock();
        $model = $target === 'product' ? $this->product : $reply;
        DB::beginTransaction();
        $worker = null;
        try {
            $model->newQuery()->whereKey($model->id)->lockForUpdate()->firstOrFail();
            $worker = new Process([PHP_BINARY, base_path('tests/Support/product-stock-delete-worker.php'),
                $target, (string) $model->id], base_path(), ['APP_ENV' => 'testing']);
            $worker->setTimeout(25);
            $worker->start();
            $deadline = microtime(true) + 10;
            while (!str_contains($worker->getOutput(), 'BARRIER') && $worker->isRunning() && microtime(true) < $deadline) {
                usleep(20000);
            }
            $this->assertStringContainsString('BARRIER', $worker->getOutput(), $worker->getErrorOutput());
            $order = app(\App\Services\Orders\ProductOrderService::class)->create($this->payload(), $this->admin->id);
            DB::commit();
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput() . $worker->getOutput());
            $lines = explode("\n", trim($worker->getOutput()));
            $this->assertSame(409, json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR)['status']);
            $this->assertDatabaseHas('product_orders', ['id' => $order->id,
                'product_id' => $this->product->id, 'local_reply_id' => $reply->id]);
            $this->assertDatabaseHas($model->getTable(), ['id' => $model->id]);
            $this->assertSame('87.7834', $this->balance());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($worker?->isRunning()) {
                $worker->stop();
            }
        }
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
