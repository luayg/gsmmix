<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\FileOrder;
use App\Models\FileService;

class FileOrdersController extends BaseOrdersController
{
    protected string $orderModel   = FileOrder::class;
    protected string $serviceModel = FileService::class;

    protected string $kind        = 'file';
    protected string $title       = 'File Orders';
    protected string $routePrefix = 'admin.orders.file';

    protected function deviceLabel(): string
    {
        return 'Device / Serial';
    }
}
