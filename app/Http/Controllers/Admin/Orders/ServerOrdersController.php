<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Admin\Orders\Concerns\HandlesOrderFinanceStatusUpdates;
use App\Models\ServerOrder;
use App\Models\ServerService;

class ServerOrdersController extends BaseOrdersController
{
    use HandlesOrderFinanceStatusUpdates;

    protected string $orderModel   = ServerOrder::class;
    protected string $serviceModel = ServerService::class;

    protected string $kind        = 'server';
    protected string $title       = 'Server Orders';
    protected string $routePrefix = 'admin.orders.server';

    protected function deviceLabel(): string
    {
        return 'Device / Email / Code';
    }

    protected function supportsQuantity(): bool
    {
        return true;
    }
}
