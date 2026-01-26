<?php

namespace App\Console\Commands;

use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use Illuminate\Console\Command;

class ProvidersSyncCommand extends Command
{
    protected $signature = 'providers:sync
        {--provider= : Provider ID}
        {--type= : imei|server|file}
        {--balance-only : Only fetch balance}';

    protected $description = 'Sync API providers (balance + remote catalogs)';

    public function handle(): int
    {
        $providerId = $this->option('provider');
        $type = $this->option('type');
        $balanceOnly = (bool) $this->option('balance-only');

        $query = ApiProvider::query()->where('active', 1);

        if ($providerId) {
            $query->where('id', (int)$providerId);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->warn('No providers found');
            return self::SUCCESS;
        }

        foreach ($providers as $provider) {
            dispatch(new SyncProviderJob((int)$provider->id, $type ?: null, $balanceOnly));
            $this->info("Dispatched sync for provider #{$provider->id} ({$provider->type})");
        }

        return self::SUCCESS;
    }
}
