<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class ServiceEditorTestCase extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('groups', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('groups')->insert(['id' => 1]);
        Schema::create('service_group_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('group_id');
            $table->string('service_type');
            $table->decimal('price', 12, 4);
            $table->boolean('auto_price')->default(false);
            $table->decimal('discount', 12, 4);
            $table->integer('discount_type');
            $table->timestamps();
        });
        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->string('service_type');
        });
        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            Schema::create($kind . '_services', function (Blueprint $table): void {
                $table->id();
                foreach (['alias', 'type', 'name', 'time', 'info', 'main_field', 'params', 'remote_id'] as $name) {
                    $table->text($name)->nullable();
                }
                foreach (['group_id', 'source', 'supplier_id', 'profit_type', 'active', 'allow_bulk',
                    'allow_duplicates', 'reply_with_latest', 'allow_report', 'allow_report_time',
                    'allow_cancel', 'allow_cancel_time', 'use_remote_cost', 'use_remote_price',
                    'stop_on_api_change', 'needs_approval', 'reply_expiration',
                    'reject_on_missing_reply', 'ordering'] as $name) {
                    $table->integer($name)->nullable();
                }
                $table->decimal('cost', 12, 4)->default(0);
                $table->decimal('profit', 12, 4)->default(0);
                $table->timestamps();
            });
        }
        Schema::table('custom_fields', function (Blueprint $table): void {
            foreach (['name', 'input', 'field_type', 'field_options', 'description', 'validation'] as $name) {
                $table->text($name)->nullable();
            }
            foreach (['minimum', 'maximum', 'required', 'active', 'ordering'] as $name) {
                $table->integer($name)->nullable();
            }
            $table->timestamps();
        });
        $this->actingAs($this->user('Administrator'));
    }

}
