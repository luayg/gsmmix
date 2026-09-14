<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditProductIntegrity extends Command
{
    protected $signature = 'products:integrity-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of product, category, source, order, and user references.';

    public function handle(): int
    {
        $state = [
            'categories_scanned' => 0,
            'products_scanned' => 0,
            'product_orders_scanned' => 0,
            'product_missing_category' => 0,
            'product_missing_source' => 0,
            'order_missing_product' => 0,
            'order_missing_user' => 0,
            'empty_alias' => 0,
            'duplicate_alias' => 0,
            'negative_cost' => 0,
            'negative_price' => 0,
            'negative_converted_price' => 0,
            'invalid_profit_type' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int) $this->option('details')));

        $categoryIds = Schema::hasTable('product_categories')
            ? DB::table('product_categories')->pluck('id')->map(fn ($id) => (int) $id)->flip()->all()
            : [];
        $state['categories_scanned'] = count($categoryIds);

        $sourceIds = Schema::hasTable('local_sources')
            ? DB::table('local_sources')->pluck('id')->map(fn ($id) => (int) $id)->flip()->all()
            : [];

        $userIds = Schema::hasTable('users')
            ? DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->flip()->all()
            : [];

        $productIds = [];
        $seenAliases = [];

        if (Schema::hasTable('products')) {
            $columns = Schema::getColumnListing('products');
            $select = array_values(array_intersect([
                'id', 'product_category_id', 'local_source_id', 'alias', 'cost', 'price',
                'converted_price', 'profit_type',
            ], $columns));

            if (in_array('id', $select, true)) {
                foreach (DB::table('products')->orderBy('id')->get($select) as $product) {
                    $id = (int) $product->id;
                    $productIds[$id] = true;
                    $state['products_scanned']++;
                    $risks = [];

                    $categoryId = (int) ($product->product_category_id ?? 0);
                    if ($categoryId > 0 && !isset($categoryIds[$categoryId])) {
                        $state['product_missing_category']++;
                        $risks[] = 'product_missing_category';
                    }

                    $sourceId = (int) ($product->local_source_id ?? 0);
                    if ($sourceId > 0 && !isset($sourceIds[$sourceId])) {
                        $state['product_missing_source']++;
                        $risks[] = 'product_missing_source';
                    }

                    if (in_array('alias', $columns, true)) {
                        $alias = trim((string) ($product->alias ?? ''));
                        if ($alias === '') {
                            $state['empty_alias']++;
                            $risks[] = 'empty_alias';
                        } else {
                            $aliasKey = mb_strtolower($alias);
                            if (isset($seenAliases[$aliasKey])) {
                                $state['duplicate_alias']++;
                                $risks[] = 'duplicate_alias';
                            } else {
                                $seenAliases[$aliasKey] = $id;
                            }
                        }
                    }

                    foreach (['cost' => 'negative_cost', 'price' => 'negative_price', 'converted_price' => 'negative_converted_price'] as $column => $risk) {
                        if (in_array($column, $columns, true) && isset($product->{$column}) && (float) $product->{$column} < 0) {
                            $state[$risk]++;
                            $risks[] = $risk;
                        }
                    }

                    if (in_array('profit_type', $columns, true)) {
                        $profitType = trim((string) ($product->profit_type ?? ''));
                        if ($profitType !== '' && !in_array($profitType, ['credits', 'percent'], true)) {
                            $state['invalid_profit_type']++;
                            $risks[] = 'invalid_profit_type';
                        }
                    }

                    if ($risks !== [] && count($details) < $limit) {
                        $details[] = ['entity' => 'product', 'id' => $id, 'risk' => implode(',', $risks)];
                    }
                }
            }
        }

        if (Schema::hasTable('product_orders')) {
            $columns = Schema::getColumnListing('product_orders');
            $select = array_values(array_intersect(['id', 'product_id', 'user_id'], $columns));

            if (in_array('id', $select, true)) {
                foreach (DB::table('product_orders')->orderBy('id')->get($select) as $order) {
                    $state['product_orders_scanned']++;
                    $risks = [];
                    $productId = (int) ($order->product_id ?? 0);
                    $userId = (int) ($order->user_id ?? 0);

                    if ($productId > 0 && !isset($productIds[$productId])) {
                        $state['order_missing_product']++;
                        $risks[] = 'order_missing_product';
                    }

                    if ($userId > 0 && !isset($userIds[$userId])) {
                        $state['order_missing_user']++;
                        $risks[] = 'order_missing_user';
                    }

                    if ($risks !== [] && count($details) < $limit) {
                        $details[] = ['entity' => 'product_order', 'id' => (int) $order->id, 'risk' => implode(',', $risks)];
                    }
                }
            }
        }

        $this->info('Read-only product integrity audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Product integrity risks (read-only):');
            $this->table(
                ['Entity', 'ID', 'Risk'],
                array_map(fn (array $row) => [$row['entity'], $row['id'], $row['risk']], $details)
            );
        }

        $problemKeys = array_values(array_diff(array_keys($state), [
            'categories_scanned', 'products_scanned', 'product_orders_scanned',
        ]));

        return collect($problemKeys)->contains(fn ($key) => $state[$key] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }
}
