<?php

namespace Tests\Feature;

use App\Models\SmmOrder;
use App\Models\User;
use App\Services\Orders\OrderFinanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderFinancePrecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->decimal('balance', 14, 4)->default(0);
            $table->string('status')->default('active');
            $table->string('password')->default('test');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('smm_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('waiting');
            $table->text('request')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function test_fractional_cent_smm_charge_refunds_and_recharges_exactly(): void
    {
        $user = User::create([
            'name' => 'Precision user',
            'email' => 'precision@example.test',
            'username' => 'precision',
            'balance' => '1.0000',
            'status' => 'active',
            'password' => 'test-password',
        ]);

        $order = SmmOrder::create([
            'user_id' => $user->id,
            'status' => 'rejected',
            'request' => [
                'charged_amount' => 0.0035,
                'financial_state' => 'charged',
            ],
        ]);

        $finance = app(OrderFinanceService::class);
        $this->assertTrue($finance->refundOrderIfNeeded($order, 'precision_test'));
        $this->assertSame('1.0035', number_format((float)$user->fresh()->balance, 4, '.', ''));

        $this->assertTrue($finance->rechargeOrderIfNeeded($order, 'precision_test'));
        $this->assertSame('1.0000', number_format((float)$user->fresh()->balance, 4, '.', ''));
    }
}
