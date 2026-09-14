<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLocalReplyIntegrity extends Command
{
    protected $signature = 'replies:integrity-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of local sources, local replies, and product-order reply links.';

    public function handle(): int
    {
        $state = [
            'sources_scanned' => 0,
            'replies_scanned' => 0,
            'product_orders_scanned' => 0,
            'reply_missing_source' => 0,
            'reply_missing_used_order' => 0,
            'order_missing_source' => 0,
            'order_missing_reply' => 0,
            'order_reply_source_mismatch' => 0,
            'reply_order_backlink_mismatch' => 0,
            'used_reply_without_used_at' => 0,
            'unused_reply_with_used_at' => 0,
            'device_reply_missing_identifier' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int)$this->option('details')));

        $sourceIds = [];
        if (Schema::hasTable('local_sources')) {
            $sourceIds = DB::table('local_sources')->pluck('id')->map(fn ($id) => (int)$id)->flip()->all();
            $state['sources_scanned'] = count($sourceIds);
        }

        $orders = [];
        if (Schema::hasTable('product_orders')) {
            $columns = Schema::getColumnListing('product_orders');
            $select = array_values(array_intersect(['id','local_source_id','local_reply_id'], $columns));
            if (in_array('id', $select, true)) {
                foreach (DB::table('product_orders')->orderBy('id')->get($select) as $row) {
                    $orders[(int)$row->id] = $row;
                }
                $state['product_orders_scanned'] = count($orders);
            }
        }

        $replies = [];
        if (Schema::hasTable('local_replies')) {
            $columns = Schema::getColumnListing('local_replies');
            $select = array_values(array_intersect([
                'id','local_source_id','used_by_product_order_id','used_at','device_based','device_identifier'
            ], $columns));

            foreach (DB::table('local_replies')->orderBy('id')->get($select) as $reply) {
                $id = (int)$reply->id;
                $replies[$id] = $reply;
                $state['replies_scanned']++;
                $risks = [];

                $sourceId = (int)($reply->local_source_id ?? 0);
                if ($sourceId > 0 && !isset($sourceIds[$sourceId])) {
                    $state['reply_missing_source']++;
                    $risks[] = 'reply_missing_source';
                }

                $usedOrderId = (int)($reply->used_by_product_order_id ?? 0);
                if ($usedOrderId > 0 && !isset($orders[$usedOrderId])) {
                    $state['reply_missing_used_order']++;
                    $risks[] = 'reply_missing_used_order';
                }

                $usedAt = $reply->used_at ?? null;
                if ($usedOrderId > 0 && empty($usedAt)) {
                    $state['used_reply_without_used_at']++;
                    $risks[] = 'used_reply_without_used_at';
                }
                if ($usedOrderId <= 0 && !empty($usedAt)) {
                    $state['unused_reply_with_used_at']++;
                    $risks[] = 'unused_reply_with_used_at';
                }

                if (!empty($reply->device_based) && trim((string)($reply->device_identifier ?? '')) === '') {
                    $state['device_reply_missing_identifier']++;
                    $risks[] = 'device_reply_missing_identifier';
                }

                if ($risks !== [] && count($details) < $limit) {
                    $details[] = ['entity' => 'reply', 'id' => $id, 'risk' => implode(',', $risks)];
                }
            }
        }

        foreach ($orders as $orderId => $order) {
            $risks = [];
            $sourceId = (int)($order->local_source_id ?? 0);
            $replyId = (int)($order->local_reply_id ?? 0);

            if ($sourceId > 0 && !isset($sourceIds[$sourceId])) {
                $state['order_missing_source']++;
                $risks[] = 'order_missing_source';
            }

            if ($replyId > 0 && !isset($replies[$replyId])) {
                $state['order_missing_reply']++;
                $risks[] = 'order_missing_reply';
            }

            if ($replyId > 0 && isset($replies[$replyId])) {
                $reply = $replies[$replyId];
                $replySourceId = (int)($reply->local_source_id ?? 0);
                $usedOrderId = (int)($reply->used_by_product_order_id ?? 0);

                if ($sourceId > 0 && $replySourceId > 0 && $sourceId !== $replySourceId) {
                    $state['order_reply_source_mismatch']++;
                    $risks[] = 'order_reply_source_mismatch';
                }

                if ($usedOrderId !== $orderId) {
                    $state['reply_order_backlink_mismatch']++;
                    $risks[] = 'reply_order_backlink_mismatch';
                }
            }

            if ($risks !== [] && count($details) < $limit) {
                $details[] = ['entity' => 'product_order', 'id' => $orderId, 'risk' => implode(',', $risks)];
            }
        }

        $this->info('Read-only local source/reply integrity audit');
        $this->table(['State','Count'], collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all());

        if ($details !== []) {
            $this->newLine();
            $this->warn('Local reply risks (read-only):');
            $this->table(['Entity','ID','Risk'], array_map(fn ($r) => [$r['entity'],$r['id'],$r['risk']], $details));
        }

        $problemKeys = array_values(array_diff(array_keys($state), [
            'sources_scanned','replies_scanned','product_orders_scanned'
        ]));

        return collect($problemKeys)->contains(fn ($key) => $state[$key] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
