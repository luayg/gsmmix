<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE `api_providers` MODIFY `type` ENUM('dhru','webx','gsmhub','unlockbase','simple_link','smm') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `api_providers` MODIFY `type` ENUM('dhru','webx','gsmhub','unlockbase','simple_link') NOT NULL");
    }
};