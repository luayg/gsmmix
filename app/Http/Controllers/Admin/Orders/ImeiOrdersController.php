<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ImeiOrder;
use App\Models\ImeiService;

class ImeiOrdersController extends BaseOrdersController
{
    protected string $kind = 'imei';
    protected string $orderModel = ImeiOrder::class;
    protected string $serviceModel = ImeiService::class;
    protected string $indexView = 'admin.orders.imei.index';
    protected string $routePrefix = 'admin.orders.imei';
}
