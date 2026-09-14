<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OrderSchedulerPipelineTest extends TestCase
{
    public function test_schedule_list_contains_the_active_order_pipeline(): void
    {
        $exit = Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('providers:sync --balance-only', $output);
        $this->assertStringContainsString('providers:sync', $output);
        $this->assertStringContainsString('orders:dispatch-pending-imei --limit=50', $output);
        $this->assertStringContainsString('orders:sync-imei --limit=50', $output);
        $this->assertStringContainsString('orders:dispatch-pending-server --limit=50', $output);
        $this->assertStringContainsString('orders:sync-server --limit=50', $output);
        $this->assertStringContainsString('orders:dispatch-pending-file --limit=50', $output);
        $this->assertStringContainsString('orders:sync-file --limit=50', $output);
        $this->assertStringContainsString('orders:dispatch-pending-smm --limit=50', $output);
        $this->assertStringContainsString('orders:sync-smm --limit=50', $output);
        $this->assertStringNotContainsString('orders:retry-imei', $output);
        $this->assertStringNotContainsString('No scheduled tasks have been defined', $output);
    }

    public function test_scheduler_is_defined_in_the_console_routes_loaded_by_bootstrap(): void
    {
        $bootstrap = (string) file_get_contents(base_path('bootstrap/app.php'));
        $console = (string) file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("commands: __DIR__.'/../routes/console.php'", $bootstrap);
        $this->assertStringContainsString("Schedule::command('providers:sync --balance-only')", $console);
        $this->assertStringContainsString('->everyFiveMinutes()', $console);
        $this->assertStringContainsString("Schedule::command('providers:sync')", $console);
        $this->assertStringContainsString('->hourlyAt(17)', $console);
        $this->assertStringContainsString("Schedule::command('orders:dispatch-pending-imei --limit=50')", $console);
        $this->assertStringNotContainsString("Schedule::command('orders:retry-imei", $console);
    }
}
