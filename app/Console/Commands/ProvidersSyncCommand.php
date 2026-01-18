<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ApiProvider;
use App\Services\Providers\ProviderFactory;

class ProvidersSyncCommand extends Command
{
    protected $signature = 'providers:sync
        {--provider_id= : مزود محدد}
        {--type= : imei|server|file}
        {--balance : تحديث الرصيد فقط}';

    protected $description = 'Sync remote services (all providers) and/or balances';

    public function handle(): int
    {
        $providerId  = $this->option('provider_id');
        $typeOpt     = $this->option('type');
        $balanceOnly = (bool)$this->option('balance');

        $q = ApiProvider::query()->where('active', 1);
        if ($providerId) $q->where('id', (int)$providerId);

        $providers = $q->orderBy('id')->get();

        foreach ($providers as $p) {
            $adapter = ProviderFactory::make($p);

            $hadError = false;
            $total    = 0;

            // ✅ balance only: لا تلمس synced نهائيًا (الرصيد ممكن يكون 0 ومع ذلك الاتصال صحيح)
            if ($balanceOnly) {
                try {
                    $bal = $adapter->fetchBalance($p);
                    $p->update(['balance' => $bal]); // ✅ فقط تحديث الرصيد
                    $this->info("{$p->id} {$p->type} balance={$bal}");
                } catch (\Throwable $e) {
                    $hadError = true;
                    $p->update(['synced' => false]);
                    $this->error("{$p->id} {$p->type} balance error: " . $e->getMessage());
                }
                continue;
            }

            $types = [];
            if ($typeOpt) {
                $types = [strtolower($typeOpt)];
            } else {
                if ($p->sync_imei)   $types[] = 'imei';
                if ($p->sync_server) $types[] = 'server';
                if ($p->sync_file)   $types[] = 'file';
            }

            foreach ($types as $t) {
                if (!$adapter->supportsCatalog($t)) {
                    $this->line("{$p->id} {$p->type} skip {$t} (no catalog)");
                    continue;
                }

                try {
                    $n = $adapter->syncCatalog($p, $t);
                    $total += (int)$n;
                    $this->info("{$p->id} {$p->type} synced {$t}: {$n}");
                } catch (\Throwable $e) {
                    $hadError = true;
                    $this->error("{$p->id} {$p->type} {$t} error: " . $e->getMessage());
                }
            }

            // ✅ synced true إذا: لا يوجد error + حصلنا على أي خدمات
            $p->update(['synced' => (!$hadError && $total > 0)]);
            $this->info("{$p->id} {$p->type} done, total={$total}");
        }

        return self::SUCCESS;
    }
}
