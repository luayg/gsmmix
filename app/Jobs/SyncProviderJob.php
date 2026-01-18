<?php

namespace App\Jobs;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 دقيقة
    public int $tries = 1;

    public function __construct(public int $providerId) {}

    public function handle(): void
    {
        $provider = ApiProvider::find($this->providerId);
        if (!$provider || !$provider->active) return;

        $adapter = ProviderFactory::make($provider);

        // balance
        $balance = $adapter->fetchBalance($provider);
        $provider->update(['balance' => $balance]);

        $total = 0;

        if ($provider->sync_imei && $adapter->supportsCatalog('imei')) {
            $total += $adapter->syncCatalog($provider, 'imei');
        }

        if ($provider->sync_server && $adapter->supportsCatalog('server')) {
            $total += $adapter->syncCatalog($provider, 'server');
        }

        if ($provider->sync_file && $adapter->supportsCatalog('file')) {
            $total += $adapter->syncCatalog($provider, 'file');
        }

        // ✅ انتهى بنجاح
        $provider->update([
            'synced'  => 1,
            'syncing'=> 0,
        ]);
    }

    public function failed(): void
    {
        ApiProvider::where('id', $this->providerId)->update([
            'syncing' => 0,
        ]);
    }
}
