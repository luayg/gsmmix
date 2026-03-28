<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmmOrder extends Model
{
    protected $table = 'smm_orders';

    protected $fillable = [
        'device','quantity','remote_id','status','order_price','price','profit',
        'request','response','comments','user_id','email','service_id','supplier_id',
        'needs_verify','expired','approved','ip','api_order','params','processing','replied_at'
    ];

    protected $casts = [
        'needs_verify' => 'boolean',
        'expired'      => 'boolean',
        'approved'     => 'boolean',
        'api_order'    => 'boolean',
        'processing'   => 'boolean',
        'replied_at'   => 'datetime',

        'quantity'     => 'int',
        'price'        => 'float',
        'order_price'  => 'float',
        'profit'       => 'float',

        'request'      => 'array',
        'response'     => 'array',
        'params'       => 'array',
    ];

    public function provider()
    {
        return $this->belongsTo(ApiProvider::class, 'supplier_id');
    }

    public function service()
    {
        return $this->belongsTo(SmmService::class, 'service_id');
    }
}