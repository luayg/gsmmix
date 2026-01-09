<?php
// [انسخ]
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        // علاقة اختيارية
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','group_id')) {
                $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','group_id')) {
                $table->dropForeign(['group_id']);
            }
        });
        Schema::dropIfExists('groups');
    }
};
