<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('access_logs') && !Schema::hasColumn('access_logs','ip_address')) {
            Schema::table('access_logs', fn (Blueprint $table) => $table->string('ip_address',45)->nullable()->index()->after('ip_hash'));
        }
        if (!Schema::hasTable('blocked_ips')) Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();$table->string('ip_address',45)->unique();$table->string('reason')->nullable();$table->boolean('active')->default(true)->index();$table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('expires_at')->nullable()->index();$table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
        if (Schema::hasTable('access_logs') && Schema::hasColumn('access_logs','ip_address')) Schema::table('access_logs', fn (Blueprint $table) => $table->dropColumn('ip_address'));
    }
};
