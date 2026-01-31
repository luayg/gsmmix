<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ProductOrder;
use App\Models\Product;

class ProductOrdersController extends BaseOrdersController
{
    protected string $orderModel  = ProductOrder::class;
    protected string $serviceModel = Product::class;

    protected string $viewPrefix  = 'product';
    protected string $routePrefix = 'admin.orders.product';
}
