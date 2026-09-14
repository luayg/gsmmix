<?php

namespace Tests\Feature;

use App\Exceptions\ServiceHasOrdersException;
use App\Models\ServerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServiceDeletionGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            Schema::create($kind . '_orders', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id')->nullable();
            });
        }
    }

    public function test_service_without_orders_can_be_deleted(): void
    {
        $service = ServerService::query()->create([
            'name' => ['en' => 'Unused', 'fallback' => 'Unused'],
            'active' => true,
        ]);

        $service->delete();

        $this->assertDatabaseMissing('server_services', ['id' => $service->id]);
    }

    public function test_service_with_historical_orders_cannot_be_deleted(): void
    {
        $service = ServerService::query()->create([
            'name' => ['en' => 'Used', 'fallback' => 'Used'],
            'active' => true,
        ]);

        DB::table('server_orders')->insert([
            'id' => 50,
            'service_id' => $service->id,
        ]);

        try {
            $service->delete();
            $this->fail('Expected ServiceHasOrdersException was not thrown.');
        } catch (ServiceHasOrdersException $e) {
            $this->assertSame('server', $e->kind);
            $this->assertSame((int)$service->id, $e->serviceId);
            $this->assertSame(1, $e->orderCount);
        }

        $this->assertDatabaseHas('server_services', ['id' => $service->id]);
    }

    public function test_reference_audit_reports_orphaned_order_service_id(): void
    {
        DB::table('server_orders')->insert([
            'id' => 99,
            'service_id' => 12345,
        ]);

        $exit = Artisan::call('services:reference-audit');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('orphan_order_service_refs', Artisan::output());
        $this->assertStringContainsString('12345', Artisan::output());
    }
}
