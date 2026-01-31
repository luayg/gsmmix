<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ServerOrder;
use App\Models\ServerService;

class ServerOrdersController extends BaseOrdersController
{
    protected string $orderModel  = ServerOrder::class;
    protected string $serviceModel = ServerService::class;

    protected string $viewPrefix  = 'server';
    protected string $routePrefix = 'admin.orders.server';
}
