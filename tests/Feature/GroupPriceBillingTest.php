<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\Orders\BaseOrdersController;
use App\Models\FileOrder;
use App\Models\ServerOrder;
use App\Models\ServiceGroupPrice;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupPriceBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_01_10_141033_create_service_group_prices_table.php'))->up();
        (require database_path('migrations/2026_09_15_000001_add_auto_price_to_service_group_prices.php'))->up();
    }

    public function test_initial_order_charge_and_display_map_respect_zero_and_current_automatic_prices(): void
    {
        $charge = new \ReflectionMethod(BaseOrdersController::class, 'calcServiceSellPriceForUser');
        $map = new \ReflectionMethod(BaseOrdersController::class, 'buildServicePriceMap');
        $user = new User;
        $user->group_id = 7;

        foreach (['Imei', 'Server', 'File'] as $name) {
            $kind = strtolower($name);
            $controllerClass = "App\\Http\\Controllers\\Admin\\Orders\\{$name}OrdersController";
            $serviceClass = "App\\Models\\{$name}Service";
            $controller = new $controllerClass;
            $service = new $serviceClass(['cost' => 20, 'profit' => 5, 'profit_type' => 1]);
            $service->id = 10;
            $row = ServiceGroupPrice::create(['service_id' => 10, 'service_type' => $kind,
                'group_id' => 7, 'price' => 0, 'auto_price' => false, 'discount' => 0, 'discount_type' => 1]);

            $this->assertSame(0.0, $charge->invoke($controller, $service, $user));
            $this->assertSame(0.0, $map->invoke($controller, collect([$service]))[10][7]);
            $row->update(['price' => 25]); // An explicit override equal to the old default is still manual.
            $service->cost = 35;
            $this->assertSame(25.0, $charge->invoke($controller, $service, $user));
            $row->update(['auto_price' => true, 'discount' => 10, 'discount_type' => 2]);
            $this->assertSame(36.0, $charge->invoke($controller, $service, $user));
            $this->assertSame(36.0, $map->invoke($controller, collect([$service]))[10][7]);
            $row->update(['discount' => 3, 'discount_type' => 1]);
            $this->assertSame(37.0, $charge->invoke($controller, $service, $user));
            $row->delete();
            $this->assertSame(40.0, $charge->invoke($controller, $service, $user));
        }
    }

    public function test_file_and_server_observers_respect_free_orders_and_automatic_quantity_charges(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->decimal('balance', 12, 2);
            $table->timestamps();
        });
        DB::table('users')->insert(['id' => 20, 'group_id' => 7, 'balance' => 100]);

        foreach (['file', 'server'] as $kind) {
            $serviceClass = $kind === 'file' ? \App\Models\FileService::class : \App\Models\ServerService::class;
            $orderClass = $kind === 'file' ? FileOrder::class : ServerOrder::class;
            $observer = $kind === 'file' ? new \App\Observers\FileOrderGroupPricingObserver
                : new \App\Observers\ServerOrderQuantityBillingObserver;
            $service = new $serviceClass(['cost' => 35, 'profit' => 5, 'profit_type' => 1]);
            $service->id = 10;
            $row = ServiceGroupPrice::create(['service_id' => 10, 'service_type' => $kind,
                'group_id' => 7, 'price' => 0, 'auto_price' => false, 'discount' => 0, 'discount_type' => 1]);

            foreach ([[false, 0.0], [true, $kind === 'file' ? 36.0 : 72.0]] as [$automatic, $total]) {
                $row->update(['auto_price' => $automatic, 'discount' => $automatic ? 10 : 0, 'discount_type' => 2]);
                DB::table('users')->where('id', 20)->update(['balance' => 90]);
                $order = new $orderClass(['user_id' => 20, 'service_id' => 10, 'price' => 10,
                    'order_price' => 5, 'quantity' => 2, 'request' => ['request_uid' => 'test', 'charged_amount' => 10]]);
                $order->setRelation('service', $service);
                DB::transaction(fn () => $observer->creating($order));
                $this->assertSame($total, (float) $order->price);
                $this->assertSame($total, (float) $order->request['charged_amount']);
                $this->assertSame(100.0 - $total, (float) DB::table('users')->where('id', 20)->value('balance'));
            }
        }
    }

    public function test_bulk_import_keeps_automatic_mode_and_manual_zero_for_each_kind(): void
    {
        $save = new \ReflectionMethod(\App\Http\Controllers\Admin\ApiProvidersController::class, 'saveGroupPricesForService');
        $controller = app(\App\Http\Controllers\Admin\ApiProvidersController::class);
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $save->invoke($controller, $kind, 10, [
                ['group_id' => 1, 'auto_price' => 1, 'price' => 0, 'discount' => 10, 'discount_type' => 2],
                ['group_id' => 2, 'auto_price' => 0, 'price' => 0, 'discount' => 0, 'discount_type' => 1],
            ], 25.0);
            $rows = ServiceGroupPrice::where('service_type', $kind)->get()->keyBy('group_id');
            $this->assertTrue($rows[1]->auto_price);
            $this->assertSame(36.0, $rows[1]->finalPrice(['cost' => 35, 'profit' => 5]));
            $this->assertFalse($rows[2]->auto_price);
            $this->assertSame(0.0, $rows[2]->finalPrice(['cost' => 35, 'profit' => 5]));
        }
    }

    public function test_migration_preserves_legacy_prices_including_zero_without_guessing_their_mode(): void
    {
        $migration = require database_path('migrations/2026_09_15_000001_add_auto_price_to_service_group_prices.php');
        $migration->down();
        foreach ([0, 12.3456, 25] as $id => $price) {
            DB::table('service_group_prices')->insert(['service_id' => 1, 'service_type' => 'imei',
                'group_id' => $id + 1, 'price' => $price, 'discount' => 0, 'discount_type' => 1]);
        }
        $migration->up();
        foreach ([0, 12.3456, 25] as $id => $price) {
            $row = ServiceGroupPrice::where('group_id', $id + 1)->firstOrFail();
            $this->assertFalse($row->auto_price);
            $this->assertSame((float) $price, $row->basePrice(['cost' => 100, 'profit' => 5]));
        }
    }
}
