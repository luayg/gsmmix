<?php

namespace App\Console\Commands;

use App\Models\FileOrder;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\SmmOrder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditOrderFinances extends Command
{
    protected $signature = 'orders:finance-audit {--json : Print counts as JSON}';
    protected $description = 'Read-only audit of order charge/refund state; prints counts only.';

    private function requestArray(Model $order): array
    {
        $request = $order->request;
        if (is_array($request)) {
            return $request;
        }
        if (is_string($request)) {
            $decoded = json_decode($request, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function financialState(array $request): string
    {
        $state = strtolower(trim((string)($request['financial_state'] ?? '')));
        if (in_array($state, ['charged', 'refunded'], true)) {
            return $state;
        }
        return !empty($request['refunded_at']) && empty($request['recharged_at'])
            ? 'refunded'
            : 'charged';
    }

    public function handle(): int
    {
        $counts = [
            'tables_scanned' => 0,
            'total_orders' => 0,
            'charged_orders' => 0,
            'refunded_state' => 0,
            'active_or_success_refunded' => 0,
            'rejected_or_cancelled_not_refunded' => 0,
            'missing_charge_metadata' => 0,
        ];

        foreach ([ImeiOrder::class, ServerOrder::class, FileOrder::class, SmmOrder::class] as $modelClass) {
            /** @var Model $prototype */
            $prototype = new $modelClass;
            if (!Schema::hasTable($prototype->getTable())) {
                continue;
            }

            $counts['tables_scanned']++;
            $modelClass::query()->select(['id', 'user_id', 'status', 'price', 'request'])
                ->orderBy('id')->chunkById(200, function ($orders) use (&$counts): void {
                    foreach ($orders as $order) {
                        $counts['total_orders']++;
                        $request = $this->requestArray($order);
                        $amount = is_numeric($request['charged_amount'] ?? null)
                            ? (float)$request['charged_amount'] : 0.0;

                        if ($amount <= 0) {
                            if ((int)($order->user_id ?? 0) > 0 && (float)($order->price ?? 0) > 0) {
                                $counts['missing_charge_metadata']++;
                            }
                            continue;
                        }

                        $counts['charged_orders']++;
                        $state = $this->financialState($request);
                        $status = strtolower(trim((string)$order->status));

                        if ($state === 'refunded') {
                            $counts['refunded_state']++;
                        }
                        if (in_array($status, ['waiting', 'inprogress', 'success'], true) && $state === 'refunded') {
                            $counts['active_or_success_refunded']++;
                        }
                        if (in_array($status, ['rejected', 'cancelled'], true) && $state !== 'refunded') {
                            $counts['rejected_or_cancelled_not_refunded']++;
                        }
                    }
                });
        }

        if ($this->option('json')) {
            $this->line(json_encode($counts));
        } else {
            $this->info('Read-only order finance audit');
            $this->table(['State', 'Count'], collect($counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        return ($counts['active_or_success_refunded']
            || $counts['rejected_or_cancelled_not_refunded']
            || $counts['missing_charge_metadata']) ? self::FAILURE : self::SUCCESS;
    }
}
