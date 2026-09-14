<?php

namespace App\Observers;

use App\Exceptions\ServiceHasOrdersException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceDeletionGuardObserver
{
    public function deleting(Model $service): void
    {
        $kind = $this->kindFor($service);
        if ($kind === null) {
            return;
        }

        $ordersTable = $kind . '_orders';
        if (!Schema::hasTable($ordersTable) || !Schema::hasColumn($ordersTable, 'service_id')) {
            return;
        }

        $count = DB::table($ordersTable)
            ->where('service_id', (int)$service->getKey())
            ->count();

        if ($count > 0) {
            throw new ServiceHasOrdersException($kind, (int)$service->getKey(), $count);
        }
    }

    private function kindFor(Model $service): ?string
    {
        return match ($service->getTable()) {
            'imei_services' => 'imei',
            'server_services' => 'server',
            'file_services' => 'file',
            'smm_services' => 'smm',
            default => null,
        };
    }
}
