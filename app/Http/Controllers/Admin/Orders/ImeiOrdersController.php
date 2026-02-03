<?php

namespace App\Http\Controllers\Admin\Orders;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ImeiOrdersController extends Controller
{
    /**
     * صفحة IMEI Orders
     */
    public function index()
    {
        // مهم جداً: هذا المتغير يُستخدم داخل resources/views/admin/orders/_index.blade.php
        // إذا لم يُمرّر سيظهر خطأ Undefined variable $routePrefix
        $title = 'IMEI Orders';
        $routePrefix = 'admin.orders.imei';

        return view('admin.orders.imei.index', compact('title', 'routePrefix'));
    }

    /**
     * Modal: Create
     */
    public function modalCreate()
    {
        // إن كان عندك create modal مختلف اتركه كما هو
        return view('admin.orders.modals.create');
    }

    /**
     * Modal: View
     */
    public function modalView($id)
    {
        $row = $this->getOrderRow($id);
        abort_if(!$row, 404);

        return view('admin.orders.modals.view', compact('row'));
    }

    /**
     * Modal: Edit
     */
    public function modalEdit($id)
    {
        $order = $this->getOrderRow($id);
        abort_if(!$order, 404);

        return view('admin.orders.modals.edit', compact('order'));
    }

    /**
     * Update order from Edit modal
     */
    public function update(Request $request, $id)
    {
        $order = DB::table('imei_orders')->where('id', (int)$id)->first();
        abort_if(!$order, 404);

        $data = $request->validate([
            'status'         => ['nullable', 'string', 'max:50'],
            'comments'       => ['nullable', 'string'],
            // هذا هو "Provider reply (editable)" (HTML)
            'provider_reply' => ['nullable', 'string'],
        ]);

        // mapping للحقول الصحيحة في جدول imei_orders
        DB::table('imei_orders')
            ->where('id', (int)$id)
            ->update([
                'status'     => $data['status'] ?? $order->status,
                'comments'   => $data['comments'] ?? $order->comments,
                'reply_text' => $data['provider_reply'] ?? $order->reply_text,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true, 'msg' => 'Saved successfully']);
    }

    /**
     * Helper: جلب الطلب + أسماء الخدمة/المزوّد + تكلفة المعالجة
     * يعتمد على الجداول الموجودة في SQL: imei_orders, imei_services, api_providers
     */
    private function getOrderRow($id)
    {
        return DB::table('imei_orders as o')
            ->leftJoin('imei_services as s', 's.id', '=', 'o.service_id')
            ->leftJoin('api_providers as p', 'p.id', '=', 'o.supplier_id')
            ->select([
                'o.*',
                's.name as service_name',
                's.cost as api_processing_price',
                'p.name as provider_name',
            ])
            ->where('o.id', (int)$id)
            ->first();
    }
}
