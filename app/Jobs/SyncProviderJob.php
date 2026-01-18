<?php

namespace App\Jobs;

use App\Models\ApiProvider;
use App\Models\RemoteFileService;
use App\Models\RemoteImeiService;
use App\Models\RemoteServerService;
use App\Services\Providers\ProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 min
    public int $tries = 1;

    public function __construct(
        public int $providerId,
        public ?string $onlyType = null // imei/server/file OR null = use provider flags
    ) {}

    public function handle(): void
    {
        DB::disableQueryLog();

        try {
            $provider = ApiProvider::find($this->providerId);
            if (!$provider) {
                throw new ModelNotFoundException("ApiProvider {$this->providerId} not found");
            }

            $adapter = ProviderFactory::make($provider);

            // 1) Balance
            $balance = $adapter->fetchBalance($provider);
            $provider->balance = $balance;
            $provider->save();

            // 2) Catalog sync
            $types = [];

            if ($this->onlyType) {
                $types = [strtolower($this->onlyType)];
            } else {
                if ((int)$provider->sync_imei === 1) $types[] = 'imei';
                if ((int)$provider->sync_server === 1) $types[] = 'server';
                if ((int)$provider->sync_file === 1) $types[] = 'file';
            }

            $done = [];
            foreach ($types as $t) {
                if (!$adapter->supportsCatalog($t)) continue;

                $count = $adapter->syncCatalog($provider, $t);
                $done[$t] = $count;
            }

            // 3) Update available_* counts (اختياري لكنه مفيد)
            $provider->available_imei = RemoteImeiService::where('api_id', $provider->id)->count();
            $provider->available_server = RemoteServerService::where('api_id', $provider->id)->count();
            $provider->available_file = RemoteFileService::where('api_id', $provider->id)->count();

            // ✅ الأهم: Synced = YES بعد نجاح المزامنة
            $provider->synced = 1;
            $provider->save();

            Log::info('SyncProviderJob DONE', [
                'provider_id' => $provider->id,
                'type' => $provider->type,
                'balance' => $balance,
                'synced_counts' => $done,
                'available' => [
                    'imei' => $provider->available_imei,
                    'server' => $provider->available_server,
                    'file' => $provider->available_file,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('SyncProviderJob FAILED', [
                'provider_id' => $this->providerId,
                'onlyType' => $this->onlyType,
                'error' => $e->getMessage(),
            ]);

            // حاول نرجع synced = 0 إذا فشل
            try {
                $p = ApiProvider::find($this->providerId);
                if ($p) {
                    $p->synced = 0;
                    $p->save();
                }
            } catch (Throwable) {}

            throw $e;
        }
    }
}
