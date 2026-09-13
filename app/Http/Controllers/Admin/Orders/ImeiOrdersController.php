<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Admin\Orders\Concerns\HandlesOrderFinanceStatusUpdates;
use App\Models\ImeiOrder;
use App\Models\ImeiService;

class ImeiOrdersController extends BaseOrdersController
{
    use HandlesOrderFinanceStatusUpdates;

    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $orderModel   = ImeiOrder::class;

    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $serviceModel = ImeiService::class;

    protected string $kind        = 'imei';
    protected string $title       = 'IMEI Orders';
    protected string $routePrefix = 'admin.orders.imei';

    protected function deviceLabel(): string
    {
        return 'IMEI / SN';
    }
}
