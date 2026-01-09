<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiProvider;
use App\Models\ImeiService;
use App\Models\FileService;
use App\Models\ServerService;
use App\Services\Api\Dhru\DhruClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DhruController extends Controller
{
    /** احصل على DhruClient من الـ provider */
    protected function client(ApiProvider $p): DhruClient
    {
        return new DhruClient($p->url, $p->username ?? '', $p->api_key ?? '');
    }

    /** عرض الرصيد وتحديثه */
    public function view(ApiProvider $provider)
    {
        $client = $this->client($provider);
        $res = $client->accountInfo();

        $balance = 0.0;
        if (!empty($res['SUCCESS'][0]['AccoutInfo']['creditraw'])) {
            $balance = (float) $res['SUCCESS'][0]['AccoutInfo']['creditraw'];
        }

        $provider->update(['balance' => $balance, 'synced' => true]);

        return view('admin.api.providers.view', compact('provider','res'));
    }

    /** قائمة خدمات IMEI (عن بُعد) مع زر "Clone" لإنشاء خدمة محلية */
    public function imeiServices(ApiProvider $provider, Request $r)
    {
        $res = $this->client($provider)->allServices();

        // نقوم بتصفية المجموعات/الخدمات من نوع IMEI فقط
        $groups = [];
        if (!empty($res['SUCCESS'][0]['LIST'])) {
            foreach ($res['SUCCESS'][0]['LIST'] as $grp) {
                if (($grp['GROUPTYPE'] ?? '') === 'IMEI') {
                    $groups[] = $grp;
                }
            }
        }

        return view('admin.api.providers.imei_services', compact('provider','groups'));
    }

    /** استنساخ خدمة IMEI محلية */
    public function cloneImeiService(ApiProvider $provider, Request $r)
    {
        $remoteId = (int) $r->input('remote_id');
        $name     = $r->input('name');
        $price    = (float) $r->input('price', 0);
        $time     = $r->input('time', '');

        // alias فريد
        $alias = Str::slug($name).'-'.$remoteId;

        $svc = ImeiService::firstOrCreate(
            ['supplier_id'=>$provider->id,'remote_id'=>$remoteId],
            [
                'alias' => $alias,
                'name'  => $name,
                'time'  => $time,
                'cost'  => $price,
                'profit'=> 0,
                'type'  => 'imei',
                'active'=> true,
            ]
        );

        return response()->json(['ok'=>true,'id'=>$svc->id]);
    }

    /** قائمة خدمات Server */
    public function serverServices(ApiProvider $provider)
    {
        $res = $this->client($provider)->allServices();
        $groups = [];
        if (!empty($res['SUCCESS'][0]['LIST'])) {
            foreach ($res['SUCCESS'][0]['LIST'] as $grp) {
                if (($grp['GROUPTYPE'] ?? '') === 'SERVER') {
                    $groups[] = $grp;
                }
            }
        }
        return view('admin.api.providers.server_services', compact('provider','groups'));
    }

    /** قائمة خدمات File */
    public function fileServices(ApiProvider $provider)
    {
        $res = $this->client($provider)->fileServices();
        $groups = $res['SUCCESS'][0]['LIST'] ?? [];
        return view('admin.api.providers.file_services', compact('provider','groups'));
    }
}
