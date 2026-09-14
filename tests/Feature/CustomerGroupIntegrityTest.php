<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\GroupController;
use App\Models\Group;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class CustomerGroupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_audit_reports_clean_references(): void
    {
        $groupId = DB::table('groups')->insertGetId([
            'name' => 'Retail',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'group-audit@example.test',
            'username' => 'group-audit',
            'password' => bcrypt('password'),
            'group_id' => $groupId,
            'balance' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!Schema::hasTable('service_group_prices')) {
            Schema::create('service_group_prices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('service_id');
                $table->string('service_type');
                $table->unsignedBigInteger('group_id');
                $table->decimal('price', 12, 4)->default(0);
                $table->decimal('discount', 12, 4)->default(0);
                $table->tinyInteger('discount_type')->default(1);
                $table->timestamps();
            });
        }

        DB::table('service_group_prices')->insert([
            'service_id' => 999,
            'service_type' => 'imei',
            'group_id' => $groupId,
            'price' => 1,
            'discount' => 0,
            'discount_type' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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
            'name' => 'Linked User',
            'email' => 'linked-group@example.test',
            'username' => 'linked-group',
            'password' => bcrypt('password'),
            'group_id' => $group->id,
            'balance' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(GroupController::class)->destroy($group);

        $this->assertSame(409, $response->getStatusCode());
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
