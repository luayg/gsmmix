<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ImeiOrder;
use App\Models\ImeiService;

class ImeiOrdersController extends BaseOrdersController
{
    protected string $orderModel  = ImeiOrder::class;
    protected string $serviceModel = ImeiService::class;

    protected string $kind = 'imei';
    protected string $viewPrefix = 'admin.orders.imei';
    protected string $routePrefix = 'admin.orders.imei';
}
