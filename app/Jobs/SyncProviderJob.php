<?php

namespace App\Jobs;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $providerId;

    public function __construct(int $providerId)
    {
        $this->providerId = $providerId;
    }

    public function handle(ProviderManager $manager): void
    {
        $provider = ApiProvider::find($this->providerId);
        if (!$provider) return;

        try {
            $manager->syncProvider($provider);

            // ✅ أهم شيء: علشان عمود Synced يصير Yes
            $provider->update([
                'synced' => true,
            ]);
        } catch (Throwable $e) {
            // ✅ إذا فشل، خلّيها No حتى تعرف إنه ما اكتمل
            $provider->update([
                'synced' => false,
            ]);

            throw $e;
        }
    }
}
