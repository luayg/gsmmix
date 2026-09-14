<?php

namespace App\Observers;

use App\Exceptions\ProviderHasActiveOrdersException;
use App\Models\ApiProvider;
use App\Services\Providers\LocalServiceManualFallback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderDeletionFallbackObserver
{
    public function deleting(ApiProvider $provider): void
    {
        $activeOrders = 0;

        foreach (['imei_orders', 'server_orders', 'file_orders', 'smm_orders'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'supplier_id') || !Schema::hasColumn($table, 'status')) {
                continue;
            }

            $activeOrders += (int) DB::table($table)
                ->where('supplier_id', (int)$provider->id)
                ->whereIn('status', ['waiting', 'inprogress'])
                ->count();
        }

        if ($activeOrders > 0) {
            throw new ProviderHasActiveOrdersException((int)$provider->id, $activeOrders);
        }

        $fallback = app(LocalServiceManualFallback::class);

        foreach (['imei', 'server', 'file', 'smm'] as $kind) {
            $fallback->convertAllLinked($provider, $kind, 'provider_deleted');
        }
    }
}
