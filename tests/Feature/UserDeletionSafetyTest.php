<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserDeletionSafetyTest extends TestCase
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
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->decimal('balance', 18, 4)->default(0);
            $table->timestamps();
        });

        // Spatie permission traits clean these pivots during User::delete().
        // The isolated test DB needs the same pivot surface even though the
        // test users have no assigned roles or direct permissions.
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders', 'product_orders'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
            });
        }

        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('locked_amount', 12, 2)->default(0);
            $table->decimal('total_receipts', 12, 2)->default(0);
            $table->decimal('paid_credits', 12, 2)->default(0);
            $table->decimal('overdraft_limit', 12, 2)->default(0);
        });

        Schema::create('finance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });
    }

    public function test_user_with_historical_order_cannot_be_deleted(): void
    {
        $user = User::create([
            'name' => 'History User',
            'email' => 'history@example.test',
            'password' => 'password',
        ]);

        DB::table('imei_orders')->insert(['user_id' => $user->id]);

        $response = app(UserController::class)->destroy($user, app(\App\Services\Users\UserDeletionGuard::class));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_with_financial_history_cannot_be_deleted(): void
    {
        $user = User::create([
            'name' => 'Finance User',
            'email' => 'finance@example.test',
            'password' => 'password',
        ]);

        DB::table('finance_transactions')->insert(['user_id' => $user->id]);

        $response = app(UserController::class)->destroy($user, app(\App\Services\Users\UserDeletionGuard::class));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_zero_history_zero_balance_user_can_be_deleted(): void
    {
        $user = User::create([
            'name' => 'Unused User',
            'email' => 'unused@example.test',
            'password' => 'password',
            'balance' => 0,
        ]);

        DB::table('finance_accounts')->insert([
            'user_id' => $user->id,
            'locked_amount' => 0,
            'total_receipts' => 0,
            'paid_credits' => 0,
            'overdraft_limit' => 0,
        ]);

        $response = app(UserController::class)->destroy($user, app(\App\Services\Users\UserDeletionGuard::class));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
