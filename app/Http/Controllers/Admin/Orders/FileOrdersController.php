<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\FileOrder;
use App\Models\FileService;

class FileOrdersController extends BaseOrdersController
{
    public function __construct()
    {
        $this->orderModel = FileOrder::class;
        $this->serviceModel = FileService::class;

        $this->routePrefix = 'admin.orders.file';
        $this->title = 'File Orders';
        $this->kind = 'file';
    }
}
