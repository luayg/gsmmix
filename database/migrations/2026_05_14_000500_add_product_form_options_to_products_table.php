<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('main_image')->nullable()->after('alias');
            $table->string('delivery_time')->nullable()->after('description');
            $table->decimal('converted_price', 12, 2)->default(0)->after('price');
            $table->string('currency', 10)->default('USD')->after('converted_price');
            $table->decimal('profit', 12, 2)->default(0)->after('currency');
            $table->string('profit_type', 20)->default('credits')->after('profit');
            $table->boolean('unlimited')->default(false)->after('device_based');
            $table->boolean('hot')->default(false)->after('unlimited');
            $table->boolean('new')->default(false)->after('hot');
            $table->boolean('sale')->default(false)->after('new');
            $table->string('meta_title')->nullable()->after('ordering');
            $table->text('meta_keywords')->nullable()->after('meta_title');
            $table->longText('meta_description')->nullable()->after('meta_keywords');

            $table->index(['hot']);
            $table->index(['new']);
            $table->index(['sale']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['hot']);
            $table->dropIndex(['new']);
            $table->dropIndex(['sale']);
            $table->dropColumn([
                'main_image',
                'delivery_time',
                'converted_price',
                'currency',
                'profit',
                'profit_type',
                'unlimited',
                'hot',
                'new',
                'sale',
                'meta_title',
                'meta_keywords',
                'meta_description',
            ]);
        });
    }
};
