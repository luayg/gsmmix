<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Models\ImeiOrder;
use App\Models\ImeiService;

class ImeiOrdersController extends BaseOrdersController
{
    protected string $model      = ImeiOrder::class;
    protected string $kind       = 'imei';
    protected string $viewFolder = 'admin.orders.imei';
    protected string $routePrefix= 'admin.orders.imei';

    protected function serviceModel(): ?string { return ImeiService::class; }
    protected function deviceFieldLabel(): string { return 'Device (IMEI/SN)'; }
}
