<?php

namespace App\Jobs;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProviderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $providerId;
    public ?string $onlyKind;
    public bool $balanceOnly;

    public int $uniqueFor = 300;
    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(int $providerId, ?string $onlyKind = null, bool $balanceOnly = false)
    {
        $this->providerId = $providerId;
        $this->onlyKind = $onlyKind;
        $this->balanceOnly = $balanceOnly;
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'provider-sync',
            $this->providerId,
            $this->onlyKind ?: 'all',
            $this->balanceOnly ? 'balance-only' : 'full',
        ]);
    }

    public function handle(ProviderManager $manager): void
    {
        $provider = ApiProvider::find($this->providerId);
        if (!$provider) {
            return;
        }

        $manager->sync($provider, $this->onlyKind, $this->balanceOnly);
    }
}