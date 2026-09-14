<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchedulerReadinessAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->boolean('api_order')->default(true);
                $table->string('status')->default('waiting');
                $table->boolean('processing')->default(false);
                $table->string('remote_id')->nullable();
                $table->text('request')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_readiness_audit_reports_registered_scheduler_and_no_duplicate_retry_path(): void
    {
        $exit = Artisan::call('orders:scheduler-audit', ['--json' => true]);
        $data = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertIsArray($data);
        $this->assertSame(0, $data['scheduled_pipeline_missing'] ?? null);
        $this->assertSame(0, $data['scheduled_pipeline_duplicates'] ?? null);
        $this->assertSame(0, $data['scheduled_retry_imei'] ?? null);
        $this->assertSame(0, $data['without_overlap_missing'] ?? null);
        $this->assertSame(0, $data['one_server_missing'] ?? null);
        $this->assertSame(0, $data['background_dispatch_missing'] ?? null);
        $this->assertSame(4, $data['order_tables_scanned'] ?? null);
    }

    public function test_audit_counts_dispatchable_hold_and_syncable_orders_without_modifying_them(): void
    {
        DB::table('imei_orders')->insert([
            'api_order' => 1,
            'status' => 'waiting',
            'processing' => 0,
            'remote_id' => null,
            'request' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('server_orders')->insert([
            'api_order' => 1,
            'status' => 'waiting',
            'processing' => 0,
            'remote_id' => null,
            'request' => json_encode(['dispatch_hold' => true]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('file_orders')->insert([
            'api_order' => 1,
            'status' => 'inprogress',
            'processing' => 1,
            'remote_id' => 'REMOTE-1',
            'request' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = [
            DB::table('imei_orders')->count(),
            DB::table('server_orders')->count(),
            DB::table('file_orders')->count(),
        ];

        $exit = Artisan::call('orders:scheduler-audit', ['--json' => true]);
        $data = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertSame(1, $data['dispatchable_now'] ?? null);
        $this->assertSame(1, $data['dispatch_hold'] ?? null);
        $this->assertSame(1, $data['syncable_now'] ?? null);
        $this->assertSame(0, $data['inprogress_without_remote_id'] ?? null);

        $this->assertSame($before, [
            DB::table('imei_orders')->count(),
            DB::table('server_orders')->count(),
            DB::table('file_orders')->count(),
        ]);
    }

    public function test_inprogress_order_without_remote_id_fails_readiness_audit(): void
    {
        DB::table('smm_orders')->insert([
            'api_order' => 1,
            'status' => 'inprogress',
            'processing' => 1,
            'remote_id' => null,
            'request' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exit = Artisan::call('orders:scheduler-audit', ['--json' => true]);
        $data = json_decode(trim(Artisan::output()), true);

        $this->assertSame(1, $exit, Artisan::output());
        $this->assertSame(1, $data['inprogress_without_remote_id'] ?? null);
    }
}
