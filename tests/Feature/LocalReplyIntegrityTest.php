<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LocalReplyController;
use App\Http\Controllers\Admin\LocalSourceController;
use App\Models\LocalReply;
use App\Models\LocalSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class LocalReplyIntegrityTest extends TestCase
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

        Schema::create('local_replies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('local_source_id')->nullable();
            $table->boolean('device_based')->default(false);
            $table->string('device_identifier')->nullable();
            $table->text('reply');
            $table->unsignedBigInteger('used_by_product_order_id')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('local_source_id')->nullable();
            $table->unsignedBigInteger('local_reply_id')->nullable();
        });
    }

    public function test_clean_links_pass_audit(): void
    {
        $source = LocalSource::create(['name' => 'Pool A']);

        $orderId = DB::table('product_orders')->insertGetId([
            'local_source_id' => $source->id,
            'local_reply_id' => null,
        ]);

        $reply = LocalReply::create([
            'local_source_id' => $source->id,
            'device_based' => false,
            'reply' => 'OK',
            'used_by_product_order_id' => $orderId,
            'used_at' => now(),
        ]);

        DB::table('product_orders')->where('id', $orderId)->update(['local_reply_id' => $reply->id]);

        $output = new BufferedOutput();
        $exit = Artisan::call('replies:integrity-audit', [], $output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('reply_order_backlink_mismatch', $output->fetch());
    }

    public function test_broken_backlink_fails_audit(): void
    {
        $source = LocalSource::create(['name' => 'Pool A']);

        $reply = LocalReply::create([
            'local_source_id' => $source->id,
            'device_based' => true,
            'device_identifier' => null,
            'reply' => 'BAD',
            'used_by_product_order_id' => null,
            'used_at' => now(),
        ]);

        DB::table('product_orders')->insert([
            'local_source_id' => $source->id,
            'local_reply_id' => $reply->id,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('replies:integrity-audit', [], $output);
        $text = $output->fetch();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('reply_order_backlink_mismatch', $text);
        $this->assertStringContainsString('unused_reply_with_used_at', $text);
        $this->assertStringContainsString('device_reply_missing_identifier', $text);
    }

    public function test_source_delete_is_blocked_while_referenced(): void
    {
        $source = LocalSource::create(['name' => 'Pool A']);
        LocalReply::create([
            'local_source_id' => $source->id,
            'device_based' => false,
            'reply' => 'OK',
        ]);

        $response = app(LocalSourceController::class)->destroy($source);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas('local_sources', ['id' => $source->id]);
    }

    public function test_used_reply_delete_is_blocked(): void
    {
        $source = LocalSource::create(['name' => 'Pool A']);
        $reply = LocalReply::create([
            'local_source_id' => $source->id,
            'device_based' => false,
            'reply' => 'OK',
            'used_by_product_order_id' => 42,
            'used_at' => now(),
        ]);

        $response = app(LocalReplyController::class)->destroy($reply);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas('local_replies', ['id' => $reply->id]);
    }
}
