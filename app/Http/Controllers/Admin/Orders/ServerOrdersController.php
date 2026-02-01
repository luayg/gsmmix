<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ServerOrder;
use App\Models\ServerService;

class ServerOrdersController extends BaseOrdersController
{
    public function __construct()
    {
        $this->orderModel = ServerOrder::class;
        $this->serviceModel = ServerService::class;

        $this->routePrefix = 'admin.orders.server';
        $this->title = 'Server Orders';
        $this->kind = 'server';
    }
}
