<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

class ManagementOverviewTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users', fn (Blueprint $table) => $table->decimal('balance', 14, 4)->default(0));
        (require database_path('migrations/2025_10_06_000000_create_finances_tables.php'))->up();
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                $table->boolean('active')->default(false);
            });
        }
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('active')->default(true);
        });
        Schema::create('product_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('status');
            $table->decimal('order_price', 10, 2);
        });
        $admin = $this->user('Administrator');
        $admin->forceFill(['balance' => '33.1234'])->save();
        DB::table('finance_accounts')->insert(['user_id' => $admin->id, 'total_receipts' => 200, 'paid_credits' => 100, 'locked_amount' => 0, 'overdraft_limit' => 0]);
        DB::table('finance_transactions')->insert(['user_id' => $admin->id, 'kind' => 'credit_add', 'direction' => 'income', 'paid' => true, 'amount' => 10, 'balance_after' => 33.12, 'reference' => 'synthetic-adjustment']);
        $this->actingAs($admin);
    }

    public function test_finance_pages_read_existing_balances_and_transactions_without_rewriting_them(): void
    {
        $before = DB::table('users')->pluck('balance', 'id')->all();
        $this->get(route('admin.finances.index'))->assertOk()->assertSee('33.1234');
        $this->get(route('admin.finances.statements.index'))->assertOk()->assertSee('33.1234')->assertSee('200.00')->assertSee('100.00');
        $this->get(route('admin.finances.transactions.index'))->assertOk()->assertSee('synthetic-adjustment');
        $this->get(route('admin.finances.transactions.index', ['direction' => 'expense']))->assertOk()->assertSee('No records match');
        $this->assertSame($before, DB::table('users')->pluck('balance', 'id')->all());
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    public function test_reports_summarize_real_counts_and_only_successful_product_sales(): void
    {
        DB::table('smm_services')->insert([['active' => 1], ['active' => 0]]);
        DB::table('products')->insert(['id' => 1, 'name' => 'Report product', 'price' => 12]);
        DB::table('product_orders')->insert([
            ['product_id' => 1, 'status' => 'success', 'order_price' => 10],
            ['product_id' => 1, 'status' => 'waiting', 'order_price' => 12],
            ['product_id' => 1, 'status' => 'cancelled', 'order_price' => 14],
        ]);
        $this->get(route('admin.reports.users'))->assertOk()->assertSee('active');
        $this->get(route('admin.reports.services'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows[3] === ['SMM', 2, 1, 1]);
        $this->get(route('admin.reports.products'))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->first() === ['Report product', 'Yes', '12.00', 3, 1, '10.00']);
    }

    public function test_modules_without_implementation_report_unavailability_instead_of_fake_success(): void
    {
        foreach ([
            'finances.invoices.index', 'pages.index', 'downloads.index', 'downloads.categories.index',
            'settings.payment',
            'system.filemanager', 'system.update', 'system.maintenance', 'system.backups',
            'logs.access', 'logs.activity', 'logs.error',
        ] as $name) {
            $this->getJson(route('admin.' . $name))->assertStatus(501)->assertJsonPath('ok', false);
        }
        $this->get(route('admin.system.backups'))->assertStatus(501)->assertSee('This module is not implemented');
    }

    public function test_report_permission_does_not_grant_financial_or_system_access(): void
    {
        $staff = $this->user();
        $staff->givePermissionTo(['admin.access', 'reports.view', 'system.view']);
        $this->actingAs($staff);
        $this->get(route('admin.reports.users'))->assertOk();
        $this->get(route('admin.finances.index'))->assertForbidden();
        $this->get(route('admin.finances.statements.index'))->assertForbidden();
        $this->get(route('admin.finances.transactions.index'))->assertForbidden();
        $this->get(route('admin.system.backups'))->assertForbidden();
    }
}
