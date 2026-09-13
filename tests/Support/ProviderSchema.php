<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SQL-backup column shape only. No real rows, accounts, URLs or secrets. */
final class ProviderSchema
{
    public static function create(): void
    {
        Schema::create('api_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->enum('type', ['dhru', 'webx', 'gsmhub', 'unlockbase', 'simple_link', 'smm']);
            $table->string('url');
            $table->string('username')->nullable();
            $table->string('api_key', 255)->nullable();
            $table->longText('params')->nullable();
            foreach (['sync_imei' => true, 'sync_server' => false, 'sync_file' => false, 'sync_smm' => true,
                'ignore_low_balance' => false, 'auto_sync' => false, 'active' => true, 'synced' => false] as $name => $default) {
                $table->boolean($name)->default($default);
            }
            $table->decimal('balance', 12, 2)->default(0);
            foreach (['imei', 'server', 'file', 'smm'] as $kind) {
                $table->unsignedInteger('available_' . $kind)->default(0);
                $table->unsignedInteger('used_' . $kind)->default(0);
            }
            $table->timestamps();
        });
    }
}
