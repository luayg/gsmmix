<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

final class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $category = ProductCategory::query()->firstOrCreate(
            ['name' => 'Professional GSM Tools'],
            ['active' => true, 'ordering' => 10],
        );

        $products = [
            ['Chimera Tool Pro', 'chimera-tool-pro-demo', 289, 'unlock-suite.webp', 'Ultimate phone servicing suite for unlock, repair and device maintenance.'],
            ['UnlockTool Annual', 'unlocktool-annual-demo', 99, 'unlock-suite.webp', 'Multi-brand unlocking and software repair access with one-year activation.'],
            ['UFI Service Box', 'ufi-service-box-demo', 349, 'service-box.webp', 'Professional eMMC and UFS hardware interface for advanced service work.'],
            ['Pandora Mobile Tool', 'pandora-mobile-tool-demo', 199, 'service-box.webp', 'Advanced mobile software and hardware service toolkit for technicians.'],
            ['EFT Pro Activation', 'eft-pro-activation-demo', 150, 'unlock-suite.webp', 'All-in-one service software activation for daily professional workflows.'],
            ['Borneo Schematics', 'borneo-schematics-demo', 99, 'schematics-suite.webp', 'Hardware diagrams, board views and repair layouts for mobile technicians.'],
        ];

        foreach ($products as $index => [$name, $alias, $price, $image, $description]) {
            Product::query()->updateOrCreate(['alias' => $alias], [
                'product_category_id' => $category->id,
                'source_type' => 'manual',
                'name' => $name,
                'main_image' => 'images/demo-products/'.$image,
                'description' => $description,
                'delivery_time' => 'Instant digital delivery',
                'cost' => 0,
                'price' => $price,
                'converted_price' => $price,
                'currency' => 'USD',
                'profit' => 0,
                'profit_type' => 'credits',
                'active' => true,
                'device_based' => false,
                'unlimited' => true,
                'hot' => $index < 2,
                'new' => true,
                'sale' => false,
                'ordering' => ($index + 1) * 10,
            ]);
        }
    }
}
