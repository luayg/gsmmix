<?php

namespace App\Console\Commands;

use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

final class AuditOrderStatuses extends Command
{
    protected $signature = 'orders:status-audit {--json : Print counts as JSON}';
    protected $description = 'Read-only audit of order status/processing/financial invariants; prints counts only.';

    private function requestArray(Model $order): array
    {
        $value = $order->request;
        if (is_array($value)) return $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function financialState(array $request): string
    {
        $state = strtolower(trim((string)($request['financial_state'] ?? '')));
        if (in_array($state, ['charged', 'refunded'], true)) return $state;
        return !empty($request['refunded_at']) && empty($request['recharged_at']) ? 'refunded' : 'charged';
    }

    public function handle(): int
    {
        $counts = [
            'tables_scanned' => 0,
            'total_orders' => 0,
            'unknown_status' => 0,
            'success_refunded' => 0,
            'failed_charged' => 0,
            'final_processing_true' => 0,
            'waiting_with_remote_id' => 0,
            'inprogress_without_remote_id' => 0,
        ];

        $allowed = ['waiting', 'inprogress', 'success', 'rejected', 'cancelled'];
        $final = ['success', 'rejected', 'cancelled'];

        foreach ([ImeiOrder::class, ServerOrder::class, FileOrder::class, SmmOrder::class] as $modelClass) {
            /** @var Model $prototype */
            $prototype = new $modelClass;
            if (!Schema::hasTable($prototype->getTable())) continue;

            $counts['tables_scanned']++;
            $modelClass::query()->select(['id', 'status', 'remote_id', 'processing', 'request'])
                ->orderBy('id')
                ->chunkById(200, function ($orders) use (&$counts, $allowed, $final): void {
                    foreach ($orders as $order) {
                        $counts['total_orders']++;
                        $status = strtolower(trim((string)$order->status));
                        $remote = trim((string)$order->remote_id);
                        $processing = (bool)$order->processing;
                        $financial = $this->financialState($this->requestArray($order));

                        if (!in_array($status, $allowed, true)) $counts['unknown_status']++;
                        if ($status === 'success' && $financial === 'refunded') $counts['success_refunded']++;
                        if (in_array($status, ['rejected', 'cancelled'], true) && $financial !== 'refunded') $counts['failed_charged']++;
                        if (in_array($status, $final, true) && $processing) $counts['final_processing_true']++;
                        if ($status === 'waiting' && $remote !== '') $counts['waiting_with_remote_id']++;
                        if ($status === 'inprogress' && $remote === '') $counts['inprogress_without_remote_id']++;
                    }
                });
        }

        if ($this->option('json')) {
            $this->line(json_encode($counts));
        } else {
            $this->info('Read-only order status audit');
            $this->table(['State', 'Count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        $problems = $counts['unknown_status']
            + $counts['success_refunded']
            + $counts['failed_charged']
            + $counts['final_processing_true']
            + $counts['waiting_with_remote_id']
            + $counts['inprogress_without_remote_id'];

        return $problems > 0 ? self::FAILURE : self::SUCCESS;
    }
}
