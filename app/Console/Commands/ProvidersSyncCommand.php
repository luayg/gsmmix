<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiProvider;
use App\Services\Providers\ProviderManager;

class ProvidersSyncCommand extends Command
{
    protected $signature = 'providers:sync
        {--provider_id= : مزود محدد}
        {--type= : imei|server|file}
        {--balance : تحديث الرصيد فقط}';

    protected $description = 'Sync remote services (all providers) and/or balances (single unified system)';

    public function handle(ProviderManager $manager): int
    {
        $providerId  = $this->option('provider_id');
        $typeOpt     = $this->option('type');
        $balanceOnly = (bool)$this->option('balance');

        $q = ApiProvider::query()->where('active', 1);
        if ($providerId) $q->where('id', (int)$providerId);

        $providers = $q->orderBy('id')->get();

        foreach ($providers as $p) {
            $this->info("Syncing #{$p->id} {$p->type} {$p->name}");

            $res = $manager->syncProvider($p, $typeOpt ? strtolower($typeOpt) : null, $balanceOnly);

            if ($balanceOnly) {
                $this->info("  balance=" . ($res['balance'] ?? 0));
                continue;
            }

            if (!empty($res['errors'])) {
                $this->error("  errors:");
                foreach ($res['errors'] as $e) {
                    $this->error("   - {$e}");
                }
            }

            $this->info("  total={$res['total']} synced=" . ($p->synced ? 'true' : 'false'));
        }

        return self::SUCCESS;
    }
}
