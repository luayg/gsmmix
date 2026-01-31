<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ServerOrder;
use App\Models\ServerService;

class ServerOrdersController extends BaseOrdersController
{
    protected string $model      = ServerOrder::class;
    protected string $kind       = 'server';
    protected string $viewFolder = 'admin.orders.server';
    protected string $routePrefix= 'admin.orders.server';

    protected function serviceModel(): ?string { return ServerService::class; }
    protected function deviceFieldLabel(): string { return 'Device / Email / Code'; }
}
