<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\FileOrder;
use App\Models\FileService;

class FileOrdersController extends BaseOrdersController
{
    protected string $model      = FileOrder::class;
    protected string $kind       = 'file';
    protected string $viewFolder = 'admin.orders.file';
    protected string $routePrefix= 'admin.orders.file';

    protected function serviceModel(): ?string { return FileService::class; }
    protected function deviceFieldLabel(): string { return 'Device / Info'; }
}
