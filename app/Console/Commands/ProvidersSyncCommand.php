<?php

namespace App\Console\Commands;

use App\Jobs\SyncProviderJob;
use App\Models\ApiProvider;
use Illuminate\Console\Command;

class ProvidersSyncCommand extends Command
{
    protected $signature = 'providers:sync
        {--provider= : Provider ID}
        {--type= : imei|server|file|smm}
        {--balance-only : Only fetch balance}';

    protected $description = 'Sync API providers (balance + remote catalogs)';

    public function handle(): int
    {
        $providerId = $this->option('provider');
        $typeInput = $this->option('type');
        $type = $this->normalizeType($typeInput);
        $balanceOnly = (bool) $this->option('balance-only');

        if ($typeInput !== null && trim((string) $typeInput) !== '' && $type === null) {
            $this->error('Invalid --type value. Allowed: imei, server, file, smm');
            return self::INVALID;
        }

        $query = ApiProvider::query()->where('active', 1);

        if ($providerId) {
            $query->where('id', (int) $providerId);
        } else {
            $query->where('auto_sync', 1);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->warn('No providers found');
            return self::SUCCESS;
        }

        foreach ($providers as $provider) {
            SyncProviderJob::dispatch((int) $provider->id, $type, $balanceOnly);
            $this->info("Dispatched sync for provider #{$provider->id} ({$provider->type})");
        }

        return self::SUCCESS;
    }

    private function normalizeType($type): ?string
    {
        $type = strtolower(trim((string) $type));

        if ($type === '') {
            return null;
        }

        return in_array($type, ['imei', 'server', 'file', 'smm'], true)
            ? $type
            : null;
    }
}