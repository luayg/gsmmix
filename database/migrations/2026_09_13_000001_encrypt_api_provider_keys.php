<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_providers') || ! Schema::hasColumn('api_providers', 'api_key')) {
            return;
        }

        Schema::table('api_providers', function (Blueprint $table): void {
            $table->text('api_key')->nullable()->change();
        });

        DB::table('api_providers')
            ->select(['id', 'api_key'])
            ->whereNotNull('api_key')
            ->where('api_key', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($providers): void {
                foreach ($providers as $provider) {
                    $key = (string) $provider->api_key;

                    try {
                        Crypt::decryptString($key);
                        continue;
                    } catch (\Throwable) {
                        // Existing plaintext value: encrypt it once.
                    }

                    DB::table('api_providers')
                        ->where('id', $provider->id)
                        ->update(['api_key' => Crypt::encryptString($key)]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: secrets must not be restored to plaintext.
    }
};
