<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\FileOrder;
use App\Models\FileService;

class FileOrdersController extends BaseOrdersController
{
    protected string $orderModel  = FileOrder::class;
    protected string $serviceModel = FileService::class;

    protected string $viewPrefix  = 'file';
    protected string $routePrefix = 'admin.orders.file';
}
