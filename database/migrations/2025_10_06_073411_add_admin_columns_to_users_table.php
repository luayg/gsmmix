<?php
// [انسخ]
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'group_id')) {
                $table->unsignedBigInteger('group_id')->nullable()->after('username');
            }
            if (!Schema::hasColumn('users', 'balance')) {
                $table->decimal('balance', 12, 2)->default(0)->after('group_id');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active','inactive'])->default('active')->after('balance');
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username','group_id','balance','status']);
        });
    }
};
