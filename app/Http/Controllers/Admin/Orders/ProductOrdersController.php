<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\ProductOrder;
use Illuminate\Http\Request;

class ProductOrdersController extends Controller
{
    public function index(Request $r)
    {
        $rows = ProductOrder::query()->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.orders.product.index', compact('rows'));
    }
}
