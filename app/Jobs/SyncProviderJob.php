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

    public int $timeout = 1200; // 20 دقيقة (بدل 60 ثانية)
    public int $tries = 1;

    public function __construct(
        public int $providerId,
        public ?string $mode = null // null = full sync, 'balance' = balance only
    ) {}

    public function handle(): void
    {
        $p = ApiProvider::query()->findOrFail($this->providerId);

        $adapter = ProviderFactory::make($p);

        try {
            // ✅ رصيد فقط
            if ($this->mode === 'balance') {
                $bal = $adapter->fetchBalance($p);
                $p->update([
                    'balance'  => $bal,
                    'synced'   => 1,
                    'syncing'  => 0,
                ]);
                return;
            }

            $total = 0;

            // ✅ مزامنة حسب الفلاغات
            $types = [];
            if ($p->sync_imei)   $types[] = 'imei';
            if ($p->sync_server) $types[] = 'server';
            if ($p->sync_file)   $types[] = 'file';

            foreach ($types as $t) {
                if ($adapter->supportsCatalog($t)) {
                    $total += (int)$adapter->syncCatalog($p, $t);
                }
            }

            // ✅ تحديث الرصيد بعد الخدمات
            $bal = $adapter->fetchBalance($p);

            $p->update([
                'balance' => $bal,
                'synced'  => 1,
                'syncing' => 0,
            ]);

        } catch (\Throwable $e) {
            // ❌ فشل: رجّع syncing=0 وخلي synced=0
            $p->update([
                'synced'  => 0,
                'syncing' => 0,
            ]);
            throw $e;
        }
    }
}
