<?php

namespace App\Http\Controllers\Admin\Orders;

use Illuminate\Http\Request;

class ProductOrdersController extends BaseOrdersController
{
    // مؤقت: Product orders نظام منفصل لاحقاً
    // نخليه يشتغل بدون أخطاء
    protected string $orderModel   = \App\Models\ImeiOrder::class;  // placeholder
    protected string $serviceModel = \App\Models\ImeiService::class; // placeholder

    protected string $kind        = 'product';
    protected string $title       = 'Product Orders';
    protected string $routePrefix = 'admin.orders.product';

    public function index(Request $request)
    {
        return view("admin.orders.product.index", [
            'title'       => $this->title,
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'rows'        => collect([]),
            'providers'   => collect([]),
        ]);
    }

    public function modalCreate()
    {
        return view('admin.orders.modals.create', [
            'title'       => "Create {$this->title}",
            'kind'        => $this->kind,
            'routePrefix' => $this->routePrefix,
            'deviceLabel' => 'Product info',
            'supportsQty' => true,
            'users'       => \App\Models\User::query()->orderByDesc('id')->limit(200)->get(),
            'services'    => collect([]),
        ]);
    }
}
