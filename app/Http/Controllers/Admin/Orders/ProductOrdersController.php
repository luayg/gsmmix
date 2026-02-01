<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ProductOrder;
use App\Models\Product; // لو ما عندك Product الآن، خليها لاحقاً واعتبرها placeholder

class ProductOrdersController extends BaseOrdersController
{
    public function __construct()
    {
        $this->orderModel = ProductOrder::class;     // لازم يكون موجود عندك
        $this->serviceModel = Product::class;        // placeholder

        $this->routePrefix = 'admin.orders.product';
        $this->title = 'Product Orders';
        $this->kind = 'product';
    }
}
