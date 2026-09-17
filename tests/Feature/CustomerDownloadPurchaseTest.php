<?php

namespace Tests\Feature;

use App\Models\Download;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SecurityTestCase;

final class CustomerDownloadPurchaseTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('balance', 14, 4)->default(0);
            $table->unsignedBigInteger('group_id')->nullable();
        });
        Schema::create('downloads', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('source_type')->default('external');
            $table->text('external_url')->nullable();
            $table->boolean('active')->default(true);
            $table->string('visibility')->default('public');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamps();
        });
        Schema::create('download_purchases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('download_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('price', 12, 2);
            $table->decimal('balance_before', 14, 4);
            $table->decimal('balance_after', 14, 4);
            $table->timestamps();
            $table->unique(['download_id','user_id']);
        });
        Schema::create('finance_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kind');
            $table->string('direction');
            $table->boolean('paid')->default(false);
            $table->decimal('amount', 14, 4);
            $table->string('currency_code',3)->nullable();
            $table->decimal('original_amount',14,4)->nullable();
            $table->decimal('exchange_rate',18,8)->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->decimal('balance_before',14,4)->nullable();
            $table->decimal('balance_after',14,4)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_paid_download_deducts_once_and_writes_customer_ledger(): void
    {
        $user=$this->user();
        $user->forceFill(['balance'=>'25.0000'])->save();
        $download=Download::create(['name'=>'Premium tool','source_type'=>'external','external_url'=>'https://example.test/tool','active'=>true,'visibility'=>'paid','price'=>'10.00']);

        $first=$this->actingAs($user)->postJson(route('customer.downloads.purchase',$download));
        $first->assertOk()->assertJson(['charged'=>true,'amount'=>'10.0000']);
        $this->assertEquals(15.0,(float)$user->fresh()->balance);
        $this->assertDatabaseHas('finance_transactions',['user_id'=>$user->id,'kind'=>'credit_remove','direction'=>'expense','amount'=>10,'balance_after'=>15]);

        $this->postJson(route('customer.downloads.purchase',$download))->assertOk()->assertJson(['charged'=>false]);
        $this->assertDatabaseCount('download_purchases',1);
        $this->assertDatabaseCount('finance_transactions',1);
    }
}
