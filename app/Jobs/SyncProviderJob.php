<?php

namespace App\Jobs;

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
    public ?string $onlyType;
    public bool $balanceOnly;

    public function __construct(int $providerId, ?string $onlyType = null, bool $balanceOnly = false)
    {
        $this->providerId  = $providerId;
        $this->onlyType    = $onlyType;
        $this->balanceOnly = $balanceOnly;
    }

    public function handle(ProviderManager $manager): void
    {
        // ProviderManager هو المسؤول الوحيد عن تحديث synced/balance
        $manager->syncProviderById($this->providerId, $this->onlyType, $this->balanceOnly);
    }
}
