<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')
            ->whereIn('slug', ['pricing', 'support'])
            ->update(['placement' => 'standalone', 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')
            ->whereIn('slug', ['pricing', 'support'])
            ->update(['placement' => 'header', 'updated_at' => now()]);
    }
};
