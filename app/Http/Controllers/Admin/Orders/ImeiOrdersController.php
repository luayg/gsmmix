<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ImeiOrder;
use App\Models\ImeiService;

class ImeiOrdersController extends BaseOrdersController
{
    public function __construct()
    {
        $this->orderModel = ImeiOrder::class;
        $this->serviceModel = ImeiService::class;

        $this->routePrefix = 'admin.orders.imei';
        $this->title = 'IMEI Orders';
        $this->kind = 'imei';
    }
}
