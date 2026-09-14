<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\GroupController;
use App\Models\Group;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class CustomerGroupIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable();
        });

        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('group_id');
        });
    }

    public function test_group_audit_reports_clean_references(): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'name' => 'Retail',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'group_id' => $groupId,
        ]);

        DB::table('service_group_prices')->insert([
            'group_id' => $groupId,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('users:group-audit', [], $output);

        $this->assertSame(0, $exit);
        $text = $output->fetch();
        $this->assertStringContainsString('users_missing_group', $text);
        $this->assertStringContainsString('group_prices_missing_group', $text);
        $this->assertDatabaseHas('users', ['id' => $userId, 'group_id' => $groupId]);
    }

    public function test_group_delete_is_blocked_when_referenced_by_user_or_price_rule(): void
    {
        $group = Group::create(['name' => 'Wholesale']);

        DB::table('users')->insert([
            'group_id' => $group->id,
        ]);

        DB::table('service_group_prices')->insert([
            'group_id' => $group->id,
        ]);

        $response = app(GroupController::class)->destroy($group);

        $this->assertSame(409, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame(1, $payload['users']);
        $this->assertSame(1, $payload['service_group_prices']);
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }

    public function test_unreferenced_group_can_be_deleted(): void
    {
        $group = Group::create(['name' => 'Unused']);

        $response = app(GroupController::class)->destroy($group);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }
}
