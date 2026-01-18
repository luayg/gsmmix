<?php

namespace App\Jobs;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $providerId;

    public function __construct(int $providerId)
    {
        $this->providerId = $providerId;
    }

    public function handle(ProviderManager $manager)
    {
        $provider = ApiProvider::find($this->providerId);
        if (!$provider) return;

        // balance
        $manager->syncBalance($provider);

        // catalogs
        foreach (['imei','server','file'] as $type) {
            $manager->syncCatalog($provider, $type);
        }

        // ✅ أهم سطر مفقود
        $provider->update([
            'synced' => 1,
            'last_synced_at' => now(),
        ]);
    }
}
