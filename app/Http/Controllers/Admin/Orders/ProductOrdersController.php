<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;

class ProductOrdersController extends Controller
{
    public function index()
    {
        return view('admin.orders.product.index');
    }

    public function modalCreate()
    {
        return response('<div class="p-4">Product orders will be implemented later.</div>', 200);
    }
}
