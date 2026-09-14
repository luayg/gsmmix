<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class UserReferenceAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders', 'product_orders'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status')->nullable();
            });
        }

        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });

        Schema::create('finance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kind')->nullable();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
    }

    public function test_clean_user_references_pass(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'User']);
        DB::table('imei_orders')->insert(['user_id' => 1, 'status' => 'success']);
        DB::table('finance_accounts')->insert(['user_id' => 1]);
        DB::table('finance_transactions')->insert(['user_id' => 1, 'kind' => 'payment']);
        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => \App\Models\User::class,
            'model_id' => 1,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('users:reference-audit', [], $output);

        $this->assertSame(0, $exit);
        $text = $output->fetch();
        $this->assertStringContainsString('order_missing_user', $text);
        $this->assertStringContainsString('finance_transaction_missing_user', $text);
        $this->assertStringContainsString('orphan_user_role_pivots', $text);
    }

    public function test_broken_user_references_fail(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'User']);
        DB::table('server_orders')->insert(['user_id' => 999, 'status' => 'waiting']);
        DB::table('finance_accounts')->insert([
            ['user_id' => 999],
            ['user_id' => 1],
            ['user_id' => 1],
        ]);
        DB::table('finance_transactions')->insert(['user_id' => 999, 'kind' => 'adjust']);
        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => \App\Models\User::class,
            'model_id' => 999,
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => 1,
            'model_type' => \App\Models\User::class,
            'model_id' => 999,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('users:reference-audit', [], $output);

        $this->assertSame(1, $exit);
        $text = $output->fetch();
        $this->assertStringContainsString('order_missing_user', $text);
        $this->assertStringContainsString('finance_account_missing_user', $text);
        $this->assertStringContainsString('duplicate_finance_account_user', $text);
        $this->assertStringContainsString('finance_transaction_missing_user', $text);
        $this->assertStringContainsString('orphan_user_role_pivots', $text);
        $this->assertStringContainsString('orphan_user_permission_pivots', $text);
    }
}
