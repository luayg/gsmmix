<?php

namespace App\Http\Controllers\Admin\Orders;

use Illuminate\Database\Eloquent\Model;

class ProductOrdersController extends BaseOrdersController
{
    // لو ما عندك موديل/جدول product_orders جاهز الآن، خليه بسيط بدون API
    protected string $model      = \App\Models\ProductOrder::class; // تأكد عندك موديل
    protected string $kind       = 'product';
    protected string $viewFolder = 'admin.orders.product';
    protected string $routePrefix= 'admin.orders.product';

    protected function serviceModel(): ?string { return null; }
    protected function deviceFieldLabel(): string { return 'Product'; }
}
