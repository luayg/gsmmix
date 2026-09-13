<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Admin\Orders\Concerns\HandlesOrderFinanceStatusUpdates;
use App\Models\SmmOrder;
use App\Models\SmmService;

class SmmOrdersController extends BaseOrdersController
{
    use HandlesOrderFinanceStatusUpdates;

    protected string $orderModel   = SmmOrder::class;
    protected string $serviceModel = SmmService::class;

    protected string $kind        = 'smm';
    protected string $title       = 'SMM Orders';
    protected string $routePrefix = 'admin.orders.smm';

    protected function deviceLabel(): string
    {
        return 'Link / Username / Target';
    }

    protected function supportsQuantity(): bool
    {
        return true;
    }
}
