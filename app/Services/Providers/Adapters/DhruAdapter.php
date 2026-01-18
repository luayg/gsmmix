<?php

namespace App\Services\Providers\Adapters;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderAdapterInterface;
use App\Services\Api\DhruClient; // ✅ الصحيح (بدون Dhru\)
use Illuminate\Support\Facades\DB;

class DhruAdapter implements ProviderAdapterInterface
{
    public function type(): string { return 'dhru'; }

    protected function client(ApiProvider $p): DhruClient
    {
        return new DhruClient($p->url, (string)$p->username, (string)$p->api_key);
    }

    public function supportsCatalog(string $serviceType): bool
    {
        $serviceType = strtolower($serviceType);
        return in_array($serviceType, ['imei','server','file'], true);
    }

    public function fetchBalance(ApiProvider $provider): float
    {
        $acc = $this->client($provider)->accountInfo();
        return (float)($acc['credits'] ?? 0);
    }

    public function syncCatalog(ApiProvider $provider, string $serviceType): int
    {
        $serviceType = strtolower($serviceType);
        $c = $this->client($provider);

        if ($serviceType === 'imei') {
            $items = $c->imeiServices();
            return $this->bulkUpsert($provider->id, 'remote_imei_services', $items, [
                // أعمدة موجودة عادة في remote_imei_services
                'name','group_name','price','time','info','min_qty','max_qty',
                'credit_groups','additional_fields','additional_data','params','updated_at'
            ]);
        }

        if ($serviceType === 'server') {
            $items = $c->serverServices();
            return $this->bulkUpsert($provider->id, 'remote_server_services', $items, [
                'name','group_name','price','time','info','min_qty','max_qty',
                'credit_groups','additional_fields','additional_data','params','updated_at'
            ]);
        }

        if ($serviceType === 'file') {
            $items = $c->fileServices();
            return $this->bulkUpsert($provider->id, 'remote_file_services', $items, [
                'name','group_name','price','time','info','allowed_extensions',
                'additional_fields','additional_data','params','updated_at'
            ]);
        }

        return 0;
    }

    /**
     * ✅ Bulk upsert لتفادي timeouts في الويب (Sync now)
     * يفترض أن $items تكون array خدمات (كل عنصر فيه SERVICEID/SERVICENAME...)
     */
    protected function bulkUpsert(int $apiId, string $table, array $items, array $updateColumns, int $chunkSize = 500): int
    {
        $now = now()->toDateTimeString();
        $buffer = [];
        $count = 0;

        foreach ($items as $srv) {
            if (!is_array($srv)) continue;

            $remoteId = (string)($srv['SERVICEID'] ?? '');
            if ($remoteId === '') continue;

            $row = [
                'api_id'    => $apiId,
                'remote_id' => $remoteId,

                'name'       => (string)($srv['SERVICENAME'] ?? ''),
                'group_name' => (string)($srv['group'] ?? ''),

                'price' => (float)($srv['CREDIT'] ?? 0),
                'time'  => (string)($srv['TIME'] ?? ''),
                'info'  => (string)($srv['INFO'] ?? ''),

                // حقول إضافية (قد تكون موجودة حسب migration عندك)
                'min_qty' => isset($srv['MINQNT']) ? (string)$srv['MINQNT'] : null,
                'max_qty' => isset($srv['MAXQNT']) ? (string)$srv['MAXQNT'] : null,

                'credit_groups'     => isset($srv['CREDITGROUPS']) ? json_encode($srv['CREDITGROUPS'], JSON_UNESCAPED_UNICODE) : null,
                'additional_fields' => isset($srv['ADDFIELDS']) ? json_encode($srv['ADDFIELDS'], JSON_UNESCAPED_UNICODE) : null,
                'additional_data'   => isset($srv['ADDDATA']) ? json_encode($srv['ADDDATA'], JSON_UNESCAPED_UNICODE) : null,
                'params'            => isset($srv['PARAMS']) ? json_encode($srv['PARAMS'], JSON_UNESCAPED_UNICODE) : null,

                'created_at' => $now,
                'updated_at' => $now,
            ];

            // خاص بالـ File
            if ($table === 'remote_file_services') {
                $row['allowed_extensions'] = (string)($srv['ALLOW_EXTENSION'] ?? '');
            }

            $buffer[] = $row;
            $count++;

            if (count($buffer) >= $chunkSize) {
                DB::table($table)->upsert($buffer, ['api_id','remote_id'], $updateColumns);
                $buffer = [];
            }
        }

        if ($buffer) {
            DB::table($table)->upsert($buffer, ['api_id','remote_id'], $updateColumns);
        }

        return $count;
    }
}
