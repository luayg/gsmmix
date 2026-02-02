<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ImeiOrder;
use App\Models\ServerOrder;
use App\Models\FileOrder;
use App\Services\Api\Dhru\DhruClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DhruOrdersController extends Controller
{
    protected function client(ApiProvider $p): DhruClient
    {
        return new DhruClient($p->url, $p->username ?? '', $p->api_key ?? '');
    }

    /* ===================== IMEI ===================== */

    public function placeImei(Request $r, ApiProvider $provider)
    {
        $data = $r->validate([
            'imei'            => 'required|digits_between:8,20', // بعض الخدمات لا تقيّد بـ 15 دائمًا
            'remote_service'  => 'required|integer',             // SERVICEID عند المزوّد
            'service_id'      => 'nullable|integer',             // الخدمة المحلية إن وُجدت
            'custom'          => 'nullable|array',               // حقول CUSTOMFIELD
            'price'           => 'nullable|numeric',             // سعرنا (اختياري)
            'order_price'     => 'nullable|numeric',             // سعر المزوّد (اختياري)
            'comments'        => 'nullable|string',
        ]);

        $c = $this->client($provider);

        $reqPayload = [
            'IMEI'   => $data['imei'],
            'ID'     => $data['remote_service'],
            'CUSTOM' => $data['custom'] ?? [],
        ];

        $res = $c->placeImeiOrder($data['imei'], (int)$data['remote_service'], $data['custom'] ?? []);

        $order = ImeiOrder::create([
            'device'      => $data['imei'],
            'remote_id'   => $res['SUCCESS'][0]['REFERENCEID'] ?? null,
            'status'      => 1, // in process مبدئيًا
            'order_price' => $data['order_price'] ?? 0,
            'price'       => $data['price'] ?? 0,
            'profit'      => ($data['price'] ?? 0) - ($data['order_price'] ?? 0),
            'request'     => json_encode($reqPayload),
            'response'    => json_encode($res),
            'comments'    => $data['comments'] ?? null,
            'user_id'     => Auth::id(),
            'email'       => Auth::user()->email ?? null,
            'service_id'  => $data['service_id'] ?? null,
            'supplier_id' => $provider->id,
            'api_order'   => 1,
            'processing'  => 1,

            // ✅ حفظ IP
            'ip'          => $r->ip(),
        ]);

        return response()->json(['ok'=>true,'id'=>$order->id,'remote'=>$order->remote_id,'raw'=>$res]);
    }

    public function checkImei(Request $r, ApiProvider $provider)
    {
        $data = $r->validate(['remote_id'=>'required|string']);
        $res  = $this->client($provider)->getImeiOrder($data['remote_id']);
        return response()->json(['ok'=>true,'raw'=>$res]);
    }

    /* ===================== SERVER ===================== */

    public function placeServer(Request $r, ApiProvider $provider)
    {
        $data = $r->validate([
            'remote_service'  => 'required|integer',
            'quantity'        => 'nullable|integer|min:1',
            'required'        => 'nullable|array',   // REQUIRED JSON
            'device'          => 'nullable|string',
            'service_id'      => 'nullable|integer',
            'price'           => 'nullable|numeric',
            'order_price'     => 'nullable|numeric',
            'comments'        => 'nullable|string',
        ]);

        $c   = $this->client($provider);
        $req = [
            'ID'       => $data['remote_service'],
            'QUANTITY' => $data['quantity'] ?? 1,
            'REQUIRED' => $data['required'] ?? [],
            'COMMENTS' => $data['comments'] ?? '',
        ];

        $res = $c->placeServerOrder(
            (int)$data['remote_service'],
            (int)($data['quantity'] ?? 1),
            $data['required'] ?? [],
            $data['comments'] ?? ''
        );

        $order = ServerOrder::create([
            'device'      => $data['device'] ?? null,
            'quantity'    => $data['quantity'] ?? 1,
            'remote_id'   => $res['SUCCESS'][0]['REFERENCEID'] ?? null,
            'status'      => 1,
            'order_price' => $data['order_price'] ?? 0,
            'price'       => $data['price'] ?? 0,
            'profit'      => ($data['price'] ?? 0) - ($data['order_price'] ?? 0),
            'request'     => json_encode($req),
            'response'    => json_encode($res),
            'comments'    => $data['comments'] ?? null,
            'user_id'     => Auth::id(),
            'email'       => Auth::user()->email ?? null,
            'service_id'  => $data['service_id'] ?? null,
            'supplier_id' => $provider->id,
            'api_order'   => 1,
            'processing'  => 1,

            // ✅ حفظ IP
            'ip'          => $r->ip(),
        ]);

        return response()->json(['ok'=>true,'id'=>$order->id,'remote'=>$order->remote_id,'raw'=>$res]);
    }

    public function checkServer(Request $r, ApiProvider $provider)
    {
        $data = $r->validate(['remote_id'=>'required|string']);
        $res  = $this->client($provider)->getServerOrder($data['remote_id']);
        return response()->json(['ok'=>true,'raw'=>$res]);
    }

    /* ===================== FILE ===================== */

    public function placeFile(Request $r, ApiProvider $provider)
    {
        $data = $r->validate([
            'remote_service'  => 'required|integer',
            'file'            => 'required|file|max:51200', // 50MB مثال
            'service_id'      => 'nullable|integer',
            'price'           => 'nullable|numeric',
            'order_price'     => 'nullable|numeric',
            'comments'        => 'nullable|string',
        ]);

        $file     = $r->file('file');
        $filename = $file->getClientOriginalName();
        $raw      = file_get_contents($file->getRealPath());

        $c   = $this->client($provider);
        $res = $c->placeFileOrder((int)$data['remote_service'], $filename, $raw, $data['comments'] ?? '');

        $order = FileOrder::create([
            'device'      => $filename,
            'remote_id'   => $res['SUCCESS'][0]['REFERENCEID'] ?? null,
            'status'      => 1,
            'order_price' => $data['order_price'] ?? 0,
            'price'       => $data['price'] ?? 0,
            'profit'      => ($data['price'] ?? 0) - ($data['order_price'] ?? 0),
            'request'     => json_encode(['ID'=>$data['remote_service'],'FILENAME'=>$filename]),
            'response'    => json_encode($res),
            'comments'    => $data['comments'] ?? null,
            'user_id'     => Auth::id(),
            'email'       => Auth::user()->email ?? null,
            'service_id'  => $data['service_id'] ?? null,
            'supplier_id' => $provider->id,
            'api_order'   => 1,
            'processing'  => 1,

            // ✅ حفظ IP
            'ip'          => $r->ip(),
        ]);

        return response()->json(['ok'=>true,'id'=>$order->id,'remote'=>$order->remote_id,'raw'=>$res]);
    }

    public function checkFile(Request $r, ApiProvider $provider)
    {
        $data = $r->validate(['remote_id'=>'required|string']);
        $res  = $this->client($provider)->getFileOrder($data['remote_id']);
        return response()->json(['ok'=>true,'raw'=>$res]);
    }
}
