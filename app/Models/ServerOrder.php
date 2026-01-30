<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerOrder extends Model
{
    protected $fillable = [
        'device','quantity','remote_id','status','order_price','price','profit',
        'request','response','comments','user_id','email','service_id','supplier_id',
        'needs_verify','expired','approved','ip','api_order','params','processing','replied_at'
    ];

    protected $casts = [
        'needs_verify'=>'boolean','expired'=>'boolean','approved'=>'boolean',
        'api_order'=>'boolean','processing'=>'boolean','replied_at'=>'datetime','params'=>'array'
    ];

    public function user(){ return $this->belongsTo(User::class, 'user_id'); }
    public function provider(){ return $this->belongsTo(ApiProvider::class, 'supplier_id'); }
    public function service (){ return $this->belongsTo(ServerService::class, 'service_id'); }
}
