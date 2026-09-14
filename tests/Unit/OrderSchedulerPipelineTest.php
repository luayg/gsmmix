<?php

namespace Tests\Unit;

use Tests\TestCase;

class OrderSchedulerPipelineTest extends TestCase
{
    public function test_imei_retry_is_manual_only_and_not_a_second_scheduled_dispatch_path(): void
    {
        $source = file_get_contents(app_path('Console/Kernel.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString("schedule->command('orders:retry-imei", $source);
        $this->assertStringContainsString('RetryImeiApiOrders::class', $source);
        $this->assertStringContainsString("schedule->command('orders:dispatch-pending-imei --limit=50')", $source);
    }

    public function test_every_automatic_order_dispatch_and_sync_job_is_single_server_and_non_overlapping(): void
    {
        $source = (string) file_get_contents(app_path('Console/Kernel.php'));

        $commands = [
            'orders:dispatch-pending-imei --limit=50',
            'orders:sync-imei --limit=50',
            'orders:dispatch-pending-server --limit=50',
            'orders:sync-server --limit=50',
            'orders:dispatch-pending-file --limit=50',
            'orders:sync-file --limit=50',
            'orders:dispatch-pending-smm --limit=50',
            'orders:sync-smm --limit=50',
        ];

        foreach ($commands as $command) {
            $start = strpos($source, "schedule->command('{$command}')");
            $this->assertNotFalse($start, "Missing scheduled command: {$command}");

            $end = strpos($source, ';', $start);
            $this->assertNotFalse($end, "Missing schedule terminator: {$command}");

            $block = substr($source, $start, $end - $start + 1);
            $this->assertStringContainsString('->withoutOverlapping()', $block, $command);
            $this->assertStringContainsString('->onOneServer()', $block, $command);
        }
    }
}
