<?php

namespace App\Http\Controllers\Admin\Orders;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ImeiOrdersController extends Controller
{
    /**
     * صفحة القائمة (إن كانت موجودة عندك سابقاً اتركها كما هي)
     * هذا Stub بسيط حتى لا يكسر المشروع إذا كان يعتمد عليها.
     */
    public function index()
    {
        return view('admin.orders.imei.index');
    }

    /**
     * فتح مودال Edit (AJAX)
     * IMPORTANT: هذا هو الذي كان يعطيك 500.
     */
    public function modalEdit($id)
    {
        // غيّر اسم الجدول لو جدولك مختلف
        $order = DB::table('orders')->where('id', $id)->first();

        if (!$order) {
            abort(404, 'Order not found');
        }

        // حاول قراءة result_items (لو مخزن JSON)
        $resultItems = [];
        if (!empty($order->result_items)) {
            $decoded = json_decode($order->result_items, true);
            if (is_array($decoded)) $resultItems = $decoded;
        }

        // صورة من داخل result_items إن وجدت
        $imageUrl = null;
        foreach ($resultItems as $it) {
            if (!is_array($it)) continue;
            if (($it['type'] ?? '') === 'image' && !empty($it['value'])) {
                $imageUrl = $it['value'];
                break;
            }
            if (!empty($it['image'])) {
                $imageUrl = $it['image'];
                break;
            }
        }

        // Provider reply: خزنها عندك غالباً في reply أو provider_reply
        // سنستخدم reply كـ editable HTML
        $replyHtml = $order->reply ?? '';

        // لو الرد جاي JSON (labels/values) نحوله HTML بسيط
        $maybeJson = json_decode($replyHtml, true);
        if (is_array($maybeJson)) {
            $tmp = '';
            foreach ($maybeJson as $row) {
                if (!is_array($row)) continue;
                $label = $row['label'] ?? '';
                $value = $row['value'] ?? '';
                if ($label === '' && $value === '') continue;
                $tmp .= '<div><strong>'.e($label).'</strong>: '.e($value).'</div>';
            }
            if ($tmp !== '') $replyHtml = $tmp;
        }

        return view('admin.orders.modals.edit', [
            'order'      => $order,
            'resultItems'=> $resultItems,
            'imageUrl'   => $imageUrl,
            'replyHtml'  => $replyHtml,
        ]);
    }

    /**
     * تحديث الطلب من مودال Edit
     * route: POST /admin/orders/imei/{id}
     */
    public function update(Request $request, $id)
    {
        // غيّر اسم الجدول لو مختلف
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) {
            return response()->json(['ok' => false, 'msg' => 'Order not found'], 404);
        }

        $status   = $request->input('status');
        $comments = $request->input('comments');
        $reply    = $request->input('reply'); // HTML من summernote

        DB::table('orders')->where('id', $id)->update([
            'status'     => $status,
            'comments'   => $comments,
            'reply'      => $reply,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'msg' => 'Saved successfully']);
    }
}
