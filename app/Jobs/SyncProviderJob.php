<?php

namespace App\Jobs;

use App\Models\ApiProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public function __construct(public int $providerId) {}

    public function handle(): void
    {
        $provider = ApiProvider::find($this->providerId);
        if (!$provider || !$provider->active) {
            return;
        }

        $adapter = app()->make(
            \App\Services\Providers\ProviderManager::class
        )->adapter($provider);

        $total = 0;

        if ($provider->sync_imei) {
            $total += $adapter->syncCatalog($provider, 'imei');
        }

        if ($provider->sync_server) {
            $total += $adapter->syncCatalog($provider, 'server');
        }

        if ($provider->sync_file) {
            $total += $adapter->syncCatalog($provider, 'file');
        }

        // تحديث الرصيد
        $provider->balance = $adapter->fetchBalance($provider);

        // ✅ هذا السطر هو سبب Synced = No سابقاً
        $provider->synced = $total > 0;

        $provider->save();
    }
}
