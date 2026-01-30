<?php

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductOrdersController extends Controller
{
    public function index(Request $r)
    {
        $rows = DB::table('product_orders')->orderByDesc('id')->paginate(20)->withQueryString();
        return view('admin.orders.product.index', compact('rows'));
    }
}
